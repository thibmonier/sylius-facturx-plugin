<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

/**
 * Parses the tax rate label of the invoicing plugin line items ("20%", "5.5%", or
 * "7.000000000000001%" because the label is built from a float multiplication).
 */
final class TaxRateParser
{
    /**
     * Decimal string with at most 2 decimals and without trailing zeros ("20", "5.5"), or null
     * when the line has no tax rate.
     */
    public static function parse(?string $label): ?string
    {
        if (null === $label) {
            return null;
        }

        $value = trim(str_replace([' ', '%', ','], ['', '', '.'], $label));
        if ('' === $value || !is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('The tax rate "%s" is not a percentage.', $label));
        }

        $rate = number_format(round((float) $value, 2), 2, '.', '');

        return rtrim(rtrim($rate, '0'), '.');
    }

    public static function isZero(?string $rate): bool
    {
        return null === $rate || 0.0 === (float) $rate;
    }
}
