<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

/** @internal */
final class StringHelper
{
    public static function nonEmpty(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    public static function normalize(?string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim((string) $value)));
    }
}
