<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Entity\LineItemInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Model\InvoiceLine;
use Tmonier\SyliusFacturXPlugin\Model\VatBreakdown;

/** @internal */
final readonly class InvoiceLineMapper
{
    private const DEFAULT_EXEMPTION_REASONS = [
        'E' => 'Exempt from VAT',
        'O' => 'Not subject to VAT',
        'AE' => 'Reverse charge',
        'K' => 'Intra-community supply of goods',
        'G' => 'Export outside the EU',
    ];

    public function __construct(
        private bool $dppLinkEnabled = false,
        private ?PassportUrlResolverInterface $passportUrlResolver = null,
    ) {
    }

    /** @return list<InvoiceLine> */
    public function mapLines(InvoiceInterface $invoice, ChannelSettingsInterface $settings): array
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
            $variantCode = $lineItem->variantCode();

            $documentReference = null;
            if ($this->dppLinkEnabled && null !== $this->passportUrlResolver && null !== $variantCode) {
                $documentReference = $this->passportUrlResolver->resolve($variantCode);
            }

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
                sellerItemId: $variantCode,
                documentReference: $documentReference,
            );
        }

        return $lines;
    }

    /**
     * @param list<InvoiceLine> $lines
     *
     * @return list<VatBreakdown>
     */
    public function buildVatBreakdown(array $lines, ChannelSettingsInterface $settings): array
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
}
