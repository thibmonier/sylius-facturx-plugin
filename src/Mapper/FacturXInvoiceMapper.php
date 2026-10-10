<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\InvoicingPlugin\Entity\BillingDataInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Entity\LineItemInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Model\Address;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;
use Tmonier\SyliusFacturXPlugin\Model\InvoiceLine;
use Tmonier\SyliusFacturXPlugin\Model\Note;
use Tmonier\SyliusFacturXPlugin\Model\Party;
use Tmonier\SyliusFacturXPlugin\Model\PaymentInstructions;
use Tmonier\SyliusFacturXPlugin\Model\VatBreakdown;

/**
 * Maps an invoice of the Sylius Invoicing Plugin to the Factur-X EN 16931 data.
 *
 * The amounts are the ones of the invoice (and of its PDF): line net amounts and VAT amounts
 * come from the line items, which carry the taxes Sylius computed and rounded per unit. The VAT
 * breakdown sums them per category and rate, so that BT-117 may differ from BT-116 x rate by a
 * few cents, within the tolerance of rule BR-CO-17. The totals are checked against the invoice
 * total to the cent.
 */
final class FacturXInvoiceMapper implements FacturXInvoiceMapperInterface
{
    public const UNIT_CODE = 'C62';

    /** VAT point date code (BT-8, UNTDID 2005 in CII) when the seller pays the VAT on debits: date of invoice. */
    public const VAT_ON_DEBITS_DATE_CODE = '5';

    private const DEFAULT_EXEMPTION_REASONS = [
        'E' => 'Exempt from VAT',
        'O' => 'Not subject to VAT',
        'AE' => 'Reverse charge',
        'K' => 'Intra-community supply of goods',
        'G' => 'Export outside the EU',
    ];

    public function map(
        InvoiceInterface $invoice,
        ChannelSettingsInterface $settings,
        ?CustomerIdentifiersInterface $buyerIdentifiers = null,
    ): FacturXInvoice {
        $lines = $this->mapLines($invoice, $settings);
        $vatBreakdown = $this->buildVatBreakdown($lines, $settings);

        $lineTotal = array_sum(array_map(static fn (InvoiceLine $line): int => $line->netAmount, $lines));
        $taxTotal = array_sum(array_map(static fn (VatBreakdown $breakdown): int => $breakdown->taxAmount, $vatBreakdown));
        $grandTotal = $lineTotal + $taxTotal;
        if ($grandTotal !== $invoice->total()) {
            throw FacturXException::amountsDoNotReconcile($invoice->number(), $invoice->total(), $grandTotal);
        }

        $paid = InvoiceInterface::PAYMENT_STATE_COMPLETED === $invoice->paymentState();
        $prepaid = $paid ? $grandTotal : 0;
        $duePayable = $grandTotal - $prepaid;
        $issueDate = \DateTimeImmutable::createFromInterface($invoice->issuedAt());

        [$deliveryName, $deliveryAddress] = $this->mapDelivery($invoice);

        return new FacturXInvoice(
            number: $invoice->number(),
            issueDate: $issueDate,
            currencyCode: $invoice->currencyCode(),
            seller: $this->mapSeller($invoice, $settings),
            buyer: $this->mapBuyer($invoice->billingData(), $buyerIdentifiers),
            lines: $lines,
            vatBreakdown: $vatBreakdown,
            lineTotalAmount: $lineTotal,
            taxBasisTotalAmount: $lineTotal,
            taxTotalAmount: $taxTotal,
            grandTotalAmount: $grandTotal,
            prepaidAmount: $prepaid,
            duePayableAmount: $duePayable,
            businessProcess: FrenchBusinessProcess::code($settings->getOperationCategory(), $paid),
            salesOrderReference: $invoice->order()->getNumber(),
            deliveryName: $deliveryName,
            deliveryAddress: $deliveryAddress,
            notes: $this->mapNotes($settings),
            paymentInstructions: $this->mapPaymentInstructions($invoice, $settings),
            paymentTerms: $settings->getPaymentTerms(),
            dueDate: $duePayable > 0 ? $issueDate->modify(sprintf('+%d days', max(0, $settings->getPaymentDueDays()))) : null,
            vatPointDateCode: $settings->isVatOnDebits() ? self::VAT_ON_DEBITS_DATE_CODE : null,
        );
    }

    /** @return list<InvoiceLine> */
    private function mapLines(InvoiceInterface $invoice, ChannelSettingsInterface $settings): array
    {
        $lines = [];
        $position = 0;

        /** @var LineItemInterface $lineItem */
        foreach ($invoice->lineItems() as $lineItem) {
            $rate = TaxRateParser::parse($lineItem->taxRate());
            if (TaxRateParser::isZero($rate) && 0 === $lineItem->taxTotal()) {
                $category = $settings->getZeroVatCategory();
                $rate = 'O' === $category ? null : '0';
            } else {
                $category = 'S';
                $rate ??= '0';
            }

            $netUnitPrice = $lineItem->discountedUnitNetPrice();
            $grossUnitPrice = $lineItem->unitPrice() > $netUnitPrice ? $lineItem->unitPrice() : null;
            $variantName = $lineItem->variantName();

            $lines[] = new InvoiceLine(
                id: (string) ++$position,
                name: $lineItem->name(),
                quantity: $lineItem->quantity(),
                netUnitPrice: $netUnitPrice,
                netAmount: $lineItem->subtotal(),
                vatCategory: $category,
                vatRate: $rate,
                vatAmount: $lineItem->taxTotal(),
                grossUnitPrice: $grossUnitPrice,
                description: null !== $variantName && '' !== $variantName && $variantName !== $lineItem->name() ? $variantName : null,
                sellerItemId: $lineItem->variantCode(),
            );
        }

        return $lines;
    }

    /**
     * @param list<InvoiceLine> $lines
     *
     * @return list<VatBreakdown>
     */
    private function buildVatBreakdown(array $lines, ChannelSettingsInterface $settings): array
    {
        /** @var array<string, array{category: string, rate: ?string, taxable: int, tax: int}> $groups */
        $groups = [];
        foreach ($lines as $line) {
            $key = $line->vatCategory . '|' . ($line->vatRate ?? '');
            $groups[$key] ??= ['category' => $line->vatCategory, 'rate' => $line->vatRate, 'taxable' => 0, 'tax' => 0];
            $groups[$key]['taxable'] += $line->netAmount;
            $groups[$key]['tax'] += $line->vatAmount;
        }

        $breakdown = [];
        foreach ($groups as $group) {
            $exempted = !in_array($group['category'], ['S', 'Z'], true);
            $breakdown[] = new VatBreakdown(
                category: $group['category'],
                rate: $group['rate'],
                taxableAmount: $group['taxable'],
                taxAmount: $group['tax'],
                exemptionReason: $exempted ? ($settings->getVatExemptionReason() ?? self::DEFAULT_EXEMPTION_REASONS[$group['category']] ?? null) : null,
                exemptionReasonCode: $exempted ? $settings->getVatExemptionReasonCode() : null,
            );
        }

        return $breakdown;
    }

    private function mapSeller(InvoiceInterface $invoice, ChannelSettingsInterface $settings): Party
    {
        $shopBillingData = $invoice->shopBillingData();

        $name = $settings->getLegalName() ?? self::nonEmpty($shopBillingData->getCompany()) ?? self::nonEmpty($invoice->channel()->getName());
        if (null === $name) {
            throw FacturXException::missingSellerData('no legal name');
        }

        $countryCode = self::nonEmpty($shopBillingData->getCountryCode());
        if (null === $countryCode) {
            throw FacturXException::missingSellerData('no country in the shop billing data of the channel');
        }

        $vatId = $settings->getVatNumber() ?? self::nonEmpty($shopBillingData->getTaxId());
        if (null === $settings->getSiren() && null === $settings->getSiret() && null === $vatId) {
            throw FacturXException::missingSellerData('no SIREN, SIRET or VAT number (rule BR-CO-26)');
        }

        $legalInformation = implode(' - ', array_filter([$settings->getLegalForm(), $settings->getTradeRegister()]));

        return new Party(
            name: $name,
            address: new Address(
                countryCode: $countryCode,
                line1: self::nonEmpty($shopBillingData->getStreet()),
                postcode: self::nonEmpty($shopBillingData->getPostcode()),
                city: self::nonEmpty($shopBillingData->getCity()),
            ),
            legalRegistrationId: $settings->getSiren(),
            globalId: $settings->getSiret(),
            vatId: $vatId,
            legalInformation: '' === $legalInformation ? null : $legalInformation,
            electronicAddress: $settings->getElectronicAddress(),
            electronicAddressScheme: null === $settings->getElectronicAddress() ? null : $settings->getElectronicAddressScheme(),
        );
    }

    private function mapBuyer(BillingDataInterface $billingData, ?CustomerIdentifiersInterface $identifiers): Party
    {
        $name = self::nonEmpty($billingData->company())
            ?? $identifiers?->getCompanyName()
            ?? trim($billingData->firstName() . ' ' . $billingData->lastName());

        return new Party(
            name: $name,
            address: new Address(
                countryCode: $billingData->countryCode(),
                line1: self::nonEmpty($billingData->street()),
                postcode: self::nonEmpty($billingData->postcode()),
                city: self::nonEmpty($billingData->city()),
                subdivision: self::nonEmpty($billingData->provinceName()) ?? self::nonEmpty($billingData->provinceCode()),
            ),
            legalRegistrationId: $identifiers?->getSiren(),
            vatId: $identifiers?->getVatNumber(),
            electronicAddress: $identifiers?->getElectronicAddress(),
            electronicAddressScheme: null === $identifiers?->getElectronicAddress() ? null : $identifiers->getElectronicAddressScheme(),
        );
    }

    /**
     * The delivery address (BG-13) is only given when it differs from the billing address, as
     * the French reform requires.
     *
     * @return array{?string, ?Address}
     */
    private function mapDelivery(InvoiceInterface $invoice): array
    {
        $shippingAddress = $invoice->order()->getShippingAddress();
        if (!$shippingAddress instanceof AddressInterface || null === self::nonEmpty($shippingAddress->getCountryCode())) {
            return [null, null];
        }

        $billingData = $invoice->billingData();
        $sameAddress = self::normalize($shippingAddress->getStreet()) === self::normalize($billingData->street())
            && self::normalize($shippingAddress->getPostcode()) === self::normalize($billingData->postcode())
            && self::normalize($shippingAddress->getCity()) === self::normalize($billingData->city())
            && $shippingAddress->getCountryCode() === $billingData->countryCode();
        if ($sameAddress) {
            return [null, null];
        }

        $name = self::nonEmpty($shippingAddress->getCompany()) ?? self::nonEmpty(trim(sprintf('%s %s', $shippingAddress->getFirstName() ?? '', $shippingAddress->getLastName() ?? '')));

        return [$name, new Address(
            countryCode: (string) $shippingAddress->getCountryCode(),
            line1: self::nonEmpty($shippingAddress->getStreet()),
            postcode: self::nonEmpty($shippingAddress->getPostcode()),
            city: self::nonEmpty($shippingAddress->getCity()),
            subdivision: self::nonEmpty($shippingAddress->getProvinceName()) ?? self::nonEmpty($shippingAddress->getProvinceCode()),
        )];
    }

    /** @return list<Note> */
    private function mapNotes(ChannelSettingsInterface $settings): array
    {
        $notes = [];
        foreach ([
            'PMD' => $settings->getLatePaymentPenaltiesNote(),
            'PMT' => $settings->getRecoveryIndemnityNote(),
            'AAB' => $settings->getEarlyPaymentDiscountNote(),
        ] as $subjectCode => $content) {
            if (null !== $content) {
                $notes[] = new Note($content, $subjectCode);
            }
        }

        return $notes;
    }

    private function mapPaymentInstructions(InvoiceInterface $invoice, ChannelSettingsInterface $settings): ?PaymentInstructions
    {
        if (null === $settings->getIban()) {
            return null;
        }

        return new PaymentInstructions(
            typeCode: PaymentInstructions::SEPA_CREDIT_TRANSFER,
            iban: $settings->getIban(),
            bic: $settings->getBic(),
            remittanceInformation: $invoice->number(),
        );
    }

    private static function nonEmpty(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    private static function normalize(?string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim((string) $value)));
    }
}
