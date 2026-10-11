<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Pdf\Font;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * Typed access to the values of the FPDI parser (its value properties are untyped).
 *
 * @internal
 */
final class PdfValues
{
    /** @return array<string, PdfType> */
    public static function entries(PdfDictionary $dictionary): array
    {
        $entries = [];
        foreach (is_array($dictionary->value) ? $dictionary->value : [] as $key => $value) {
            if ($value instanceof PdfType) {
                $entries[(string) $key] = $value;
            }
        }

        return $entries;
    }

    /** The entry as written (a reference stays a reference). */
    public static function raw(PdfDictionary $dictionary, string $key): ?PdfType
    {
        return self::entries($dictionary)[$key] ?? null;
    }

    /** The entry, references resolved. */
    public static function get(PdfDictionary $dictionary, string $key, PdfParser $parser): ?PdfType
    {
        $value = self::raw($dictionary, $key);

        return null === $value ? null : PdfType::resolve($value, $parser);
    }

    public static function set(PdfDictionary $dictionary, string $key, PdfType $value): void
    {
        $entries = self::entries($dictionary);
        $entries[$key] = $value;
        $dictionary->value = $entries;
    }

    public static function streamDictionary(PdfStream $stream): ?PdfDictionary
    {
        /** @var mixed $dictionary */
        $dictionary = $stream->value;

        return $dictionary instanceof PdfDictionary ? $dictionary : null;
    }

    /** @return list<PdfType> */
    public static function items(PdfArray $array): array
    {
        $items = [];
        /** @var mixed $values */
        $values = $array->value;
        foreach (is_array($values) ? $values : [] as $value) {
            if ($value instanceof PdfType) {
                $items[] = $value;
            }
        }

        return $items;
    }

    public static function name(?PdfType $value): ?string
    {
        return $value instanceof PdfName && is_string($value->value) ? $value->value : null;
    }

    public static function number(?PdfType $value): ?float
    {
        return $value instanceof PdfNumeric && is_numeric($value->value) ? (float) $value->value : null;
    }

    public static function reference(?PdfType $value): ?int
    {
        return $value instanceof PdfIndirectObjectReference && is_int($value->value) ? $value->value : null;
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
