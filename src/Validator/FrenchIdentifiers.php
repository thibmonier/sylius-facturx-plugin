<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Validator;

/**
 * Checks of the French company identifiers (SIREN, SIRET) and of the intra-community VAT
 * number, whose French key is derived from the SIREN.
 */
final class FrenchIdentifiers
{
    /** SIRET of La Poste establishments do not follow the Luhn algorithm. */
    private const LA_POSTE_SIREN = '356000000';

    public static function isValidSiren(string $siren): bool
    {
        return 1 === preg_match('/^\d{9}$/', $siren) && self::luhn($siren);
    }

    public static function isValidSiret(string $siret): bool
    {
        if (1 !== preg_match('/^\d{14}$/', $siret)) {
            return false;
        }

        if (str_starts_with($siret, self::LA_POSTE_SIREN)) {
            return 0 === array_sum(str_split($siret)) % 5;
        }

        return self::isValidSiren(substr($siret, 0, 9)) && self::luhn($siret);
    }

    /**
     * Format of an EU VAT number (country prefix and 2 to 13 characters); the key of a French
     * number with a numeric key is checked against its SIREN.
     */
    public static function isValidVatNumber(string $vatNumber): bool
    {
        if (1 !== preg_match('/^[A-Z]{2}[0-9A-Z+*.]{2,13}$/', $vatNumber)) {
            return false;
        }

        if (!str_starts_with($vatNumber, 'FR')) {
            return true;
        }

        if (1 !== preg_match('/^FR([0-9A-HJ-NP-Z]{2})(\d{9})$/', $vatNumber, $matches)) {
            return false;
        }

        if (1 !== preg_match('/^\d{2}$/', $matches[1])) {
            return true; // alphanumeric keys of new companies cannot be checked
        }

        return (int) $matches[1] === self::frenchVatKey($matches[2]);
    }

    public static function frenchVatKey(string $siren): int
    {
        return (12 + 3 * ((int) $siren % 97)) % 97;
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $length = strlen($digits);
        for ($i = 0; $i < $length; ++$i) {
            $digit = (int) $digits[$length - 1 - $i];
            if (1 === $i % 2) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return 0 === $sum % 10;
    }
}
