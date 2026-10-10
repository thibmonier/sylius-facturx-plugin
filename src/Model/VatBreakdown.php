<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** VAT breakdown (BG-23), one per VAT category and rate. */
final class VatBreakdown
{
    public function __construct(
        /** BT-118 */
        public readonly string $category,
        /** BT-119, null for the "not subject to VAT" category */
        public readonly ?string $rate,
        /** BT-116 */
        public readonly int $taxableAmount,
        /** BT-117 */
        public readonly int $taxAmount,
        /** BT-120 */
        public readonly ?string $exemptionReason = null,
        /** BT-121 */
        public readonly ?string $exemptionReasonCode = null,
    ) {
    }
}
