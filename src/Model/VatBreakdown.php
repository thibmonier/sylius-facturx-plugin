<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** VAT breakdown (BG-23), one per VAT category and rate. */
final readonly class VatBreakdown
{
    public function __construct(
        /** BT-118 */
        public string $category,
        /** BT-119, null for the "not subject to VAT" category */
        public ?string $rate,
        /** BT-116 */
        public int $taxableAmount,
        /** BT-117 */
        public int $taxAmount,
        /** BT-120 */
        public ?string $exemptionReason = null,
        /** BT-121 */
        public ?string $exemptionReasonCode = null,
    ) {
    }
}
