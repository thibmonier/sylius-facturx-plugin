<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/**
 * Invoice line (BG-25). Amounts are integers in the minor unit of the currency, like in Sylius.
 */
final class InvoiceLine
{
    public function __construct(
        /** BT-126 */
        public readonly string $id,
        /** BT-153 */
        public readonly string $name,
        /** BT-129, unit C62 */
        public readonly int $quantity,
        /** BT-146, net price after the item price discount */
        public readonly int $netUnitPrice,
        /** BT-131 */
        public readonly int $netAmount,
        /** BT-151 */
        public readonly string $vatCategory,
        /** BT-152, decimal string ("20", "5.5"), null for the "not subject to VAT" category */
        public readonly ?string $vatRate,
        /** VAT amount of the line, used for the VAT breakdown only (not part of a CII line). */
        public readonly int $vatAmount,
        /** BT-148, price before the item price discount (BT-147 = gross - net) */
        public readonly ?int $grossUnitPrice = null,
        /** BT-154 */
        public readonly ?string $description = null,
        /** BT-155 */
        public readonly ?string $sellerItemId = null,
    ) {
    }

    public function unitDiscount(): int
    {
        return null === $this->grossUnitPrice ? 0 : $this->grossUnitPrice - $this->netUnitPrice;
    }
}
