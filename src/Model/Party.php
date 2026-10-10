<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** Seller (BG-4) or buyer (BG-7). */
final class Party
{
    public const SCHEME_SIREN = '0002';

    public const SCHEME_SIRET = '0009';

    public function __construct(
        public readonly string $name,
        public readonly Address $address,
        /** BT-30 / BT-47, SIREN (scheme 0002) */
        public readonly ?string $legalRegistrationId = null,
        /** BT-29 / BT-46, SIRET (scheme 0009) */
        public readonly ?string $globalId = null,
        /** BT-31 / BT-48 */
        public readonly ?string $vatId = null,
        /** BT-33 */
        public readonly ?string $legalInformation = null,
        /** BT-34 / BT-49 */
        public readonly ?string $electronicAddress = null,
        public readonly ?string $electronicAddressScheme = null,
    ) {
    }
}
