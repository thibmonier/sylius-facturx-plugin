<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** Seller (BG-4) or buyer (BG-7). */
final readonly class Party
{
    public const SCHEME_SIREN = '0002';

    public const SCHEME_SIRET = '0009';

    public function __construct(
        public string $name,
        public Address $address,
        /** BT-30 / BT-47, SIREN (scheme 0002) */
        public ?string $legalRegistrationId = null,
        /** BT-29 / BT-46, SIRET (scheme 0009) */
        public ?string $globalId = null,
        /** BT-31 / BT-48 */
        public ?string $vatId = null,
        /** BT-33 */
        public ?string $legalInformation = null,
        /** BT-34 / BT-49 */
        public ?string $electronicAddress = null,
        public ?string $electronicAddressScheme = null,
    ) {
    }
}
