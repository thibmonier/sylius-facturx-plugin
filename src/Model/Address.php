<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** Postal address (BG-5, BG-8, BG-15). */
final readonly class Address
{
    public function __construct(
        public string $countryCode,
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $postcode = null,
        public ?string $city = null,
        public ?string $subdivision = null,
    ) {
    }
}
