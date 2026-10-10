<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/**
 * Data of a Factur-X EN 16931 invoice, independent of the XML syntax. Amounts are integers in
 * the minor unit of the currency (cents), like in Sylius; the XML writer formats them.
 */
final class FacturXInvoice
{
    public const TYPE_COMMERCIAL_INVOICE = '380';

    /**
     * @param list<InvoiceLine> $lines
     * @param list<VatBreakdown> $vatBreakdown
     * @param list<Note> $notes
     */
    public function __construct(
        /** BT-1 */
        public readonly string $number,
        /** BT-2 */
        public readonly \DateTimeImmutable $issueDate,
        /** BT-5 */
        public readonly string $currencyCode,
        public readonly Party $seller,
        public readonly Party $buyer,
        public readonly array $lines,
        public readonly array $vatBreakdown,
        /** BT-106 */
        public readonly int $lineTotalAmount,
        /** BT-109 */
        public readonly int $taxBasisTotalAmount,
        /** BT-110 */
        public readonly int $taxTotalAmount,
        /** BT-112 */
        public readonly int $grandTotalAmount,
        /** BT-113 */
        public readonly int $prepaidAmount,
        /** BT-115 */
        public readonly int $duePayableAmount,
        /** BT-3 */
        public readonly string $typeCode = self::TYPE_COMMERCIAL_INVOICE,
        /** BT-23 */
        public readonly ?string $businessProcess = null,
        /** BT-14 */
        public readonly ?string $salesOrderReference = null,
        /** BT-70 */
        public readonly ?string $deliveryName = null,
        /** BG-15 */
        public readonly ?Address $deliveryAddress = null,
        /** BT-72 */
        public readonly ?\DateTimeImmutable $deliveryDate = null,
        public readonly array $notes = [],
        public readonly ?PaymentInstructions $paymentInstructions = null,
        /** BT-20 */
        public readonly ?string $paymentTerms = null,
        /** BT-9 */
        public readonly ?\DateTimeImmutable $dueDate = null,
        /** BT-8, VAT point date code (UNTDID 2475 in CII) */
        public readonly ?string $vatPointDateCode = null,
    ) {
    }
}
