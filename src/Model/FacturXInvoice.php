<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/**
 * Data of a Factur-X EN 16931 invoice, independent of the XML syntax. Amounts are integers in
 * the minor unit of the currency (cents), like in Sylius; the XML writer formats them.
 */
final readonly class FacturXInvoice
{
    public const TYPE_COMMERCIAL_INVOICE = '380';

    /**
     * @param list<InvoiceLine> $lines
     * @param list<VatBreakdown> $vatBreakdown
     * @param list<Note> $notes
     */
    public function __construct(
        /** BT-1 */
        public string $number,
        /** BT-2 */
        public \DateTimeImmutable $issueDate,
        /** BT-5 */
        public string $currencyCode,
        public Party $seller,
        public Party $buyer,
        public array $lines,
        public array $vatBreakdown,
        /** BT-106 */
        public int $lineTotalAmount,
        /** BT-109 */
        public int $taxBasisTotalAmount,
        /** BT-110 */
        public int $taxTotalAmount,
        /** BT-112 */
        public int $grandTotalAmount,
        /** BT-113 */
        public int $prepaidAmount,
        /** BT-115 */
        public int $duePayableAmount,
        /** BT-3 */
        public string $typeCode = self::TYPE_COMMERCIAL_INVOICE,
        /** BT-23 */
        public ?string $businessProcess = null,
        /** BT-14 */
        public ?string $salesOrderReference = null,
        /** BT-70 */
        public ?string $deliveryName = null,
        /** BG-15 */
        public ?Address $deliveryAddress = null,
        /** BT-72 */
        public ?\DateTimeImmutable $deliveryDate = null,
        public array $notes = [],
        public ?PaymentInstructions $paymentInstructions = null,
        /** BT-20 */
        public ?string $paymentTerms = null,
        /** BT-9 */
        public ?\DateTimeImmutable $dueDate = null,
        /** BT-8, VAT point date code (UNTDID 2475 in CII) */
        public ?string $vatPointDateCode = null,
    ) {
    }
}
