<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** Postal address (BG-5, BG-8, BG-15). */
final class Address
{
    public function __construct(
        public readonly string $countryCode,
        public readonly ?string $line1 = null,
        public readonly ?string $line2 = null,
        public readonly ?string $postcode = null,
        public readonly ?string $city = null,
        public readonly ?string $subdivision = null,
    ) {
    }
}
