<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** Payment instructions (BG-16) for a credit transfer (BG-17). */
final class PaymentInstructions
{
    public const SEPA_CREDIT_TRANSFER = '58';

    public function __construct(
        /** BT-81 (UNTDID 4461) */
        public readonly string $typeCode,
        /** BT-84 */
        public readonly ?string $iban = null,
        /** BT-85 */
        public readonly ?string $accountName = null,
        /** BT-86 */
        public readonly ?string $bic = null,
        /** BT-83 */
        public readonly ?string $remittanceInformation = null,
    ) {
    }
}
