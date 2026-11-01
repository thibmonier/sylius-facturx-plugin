<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;
use Tmonier\SyliusFacturXPlugin\Model\InvoiceLine;
use Tmonier\SyliusFacturXPlugin\Model\Note;
use Tmonier\SyliusFacturXPlugin\Model\PaymentInstructions;
use Tmonier\SyliusFacturXPlugin\Model\VatBreakdown;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\CompositeInvoicePaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;

/**
 * Maps an invoice of the Sylius Invoicing Plugin to the Factur-X EN 16931 data.
 *
 * The amounts are the ones of the invoice (and of its PDF): line net amounts and VAT amounts
 * come from the line items, which carry the taxes Sylius computed and rounded per unit. The VAT
 * breakdown sums them per category and rate, so that BT-117 may differ from BT-116 x rate by a
 * few cents, within the tolerance of rule BR-CO-17. The totals are checked against the invoice
 * total to the cent.
 */
final readonly class FacturXInvoiceMapper implements FacturXInvoiceMapperInterface
{
    public const UNIT_CODE = 'C62';

    /** VAT point date code (BT-8, UNTDID 2005 in CII) when the seller pays the VAT on debits: date of invoice. */
    public const VAT_ON_DEBITS_DATE_CODE = '5';

    private InvoicePaymentTermsProviderInterface $paymentTermsProvider;

    private InvoiceLineMapper $lineMapper;

    private PartyMapper $partyMapper;

    private DeliveryMapper $deliveryMapper;

    /** Without provider, the channel settings give the due date and payment terms (behaviour of 1.0). */
    public function __construct(
        ?InvoicePaymentTermsProviderInterface $paymentTermsProvider = null,
        bool $dppLinkEnabled = false,
        ?PassportUrlResolverInterface $passportUrlResolver = null,
    ) {
        $this->paymentTermsProvider = $paymentTermsProvider instanceof CompositeInvoicePaymentTermsProvider
            ? $paymentTermsProvider
            : new CompositeInvoicePaymentTermsProvider($paymentTermsProvider instanceof InvoicePaymentTermsProviderInterface ? [$paymentTermsProvider] : []);
        $this->lineMapper = new InvoiceLineMapper($dppLinkEnabled, $passportUrlResolver);
        $this->partyMapper = new PartyMapper();
        $this->deliveryMapper = new DeliveryMapper();
    }

    public function map(
        InvoiceInterface $invoice,
        ChannelSettingsInterface $settings,
        ?CustomerIdentifiersInterface $buyerIdentifiers = null,
    ): FacturXInvoice {
        $lines = $this->lineMapper->mapLines($invoice, $settings);
        $vatBreakdown = $this->lineMapper->buildVatBreakdown($lines, $settings);

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

        [$deliveryName, $deliveryAddress] = $this->deliveryMapper->mapDelivery($invoice);
        $paymentTerms = $this->paymentTermsProvider->provide($invoice, $settings, $duePayable);

        return new FacturXInvoice(
            number: $invoice->number(),
            issueDate: $issueDate,
            currencyCode: $invoice->currencyCode(),
            seller: $this->partyMapper->mapSeller($invoice, $settings),
            buyer: $this->partyMapper->mapBuyer($invoice->billingData(), $buyerIdentifiers),
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
            deliveryDate: $this->deliveryMapper->deliveryDate($invoice),
            notes: $this->mapNotes($settings),
            paymentInstructions: $this->mapPaymentInstructions($invoice, $settings),
            paymentTerms: $paymentTerms->description,
            dueDate: $paymentTerms->dueDate,
            vatPointDateCode: $settings->isVatOnDebits() ? self::VAT_ON_DEBITS_DATE_CODE : null,
        );
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
}
