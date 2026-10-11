<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Pdf\Font;

/**
 * Glyph advance widths and character map of an embedded TrueType font program (FontFile2):
 * the minimum needed to check the widths that a PDF declares for the font.
 */
final class TrueTypeMetrics
{
    /**
     * @param list<int> $advances advance width of each glyph id, in font units
     * @param array<string, array<int, int>> $cmaps code => glyph id, keyed by "platform.encoding"
     */
    private function __construct(
        public readonly int $unitsPerEm,
        public readonly int $numGlyphs,
        private readonly array $advances,
        private readonly array $cmaps,
    ) {
    }

    /** @throws \InvalidArgumentException when the font program cannot be read */
    public static function fromFontProgram(string $font): self
    {
        $tables = self::tables($font);
        foreach (['head', 'hhea', 'hmtx', 'maxp'] as $required) {
            if (!isset($tables[$required])) {
                throw new \InvalidArgumentException(sprintf('The font program has no "%s" table.', $required));
            }
        }

        $unitsPerEm = self::uint16($font, $tables['head'][0] + 18);
        $numberOfHMetrics = self::uint16($font, $tables['hhea'][0] + 34);
        $numGlyphs = self::uint16($font, $tables['maxp'][0] + 4);
        if (0 === $unitsPerEm || 0 === $numberOfHMetrics) {
            throw new \InvalidArgumentException('The font program has no metrics.');
        }

        $advances = [];
        for ($glyph = 0; $glyph < $numberOfHMetrics; ++$glyph) {
            $advances[] = self::uint16($font, $tables['hmtx'][0] + 4 * $glyph);
        }

        return new self($unitsPerEm, max($numGlyphs, $numberOfHMetrics), $advances, isset($tables['cmap']) ? self::cmaps($font, $tables['cmap'][0]) : []);
    }

    /** Advance width of a glyph in thousandths of the text space unit (PDF glyph space). */
    public function width(int $glyph): ?float
    {
        if ($glyph < 0 || $glyph >= $this->numGlyphs) {
            return null;
        }

        // The glyphs after numberOfHMetrics have the advance of the last metric.
        $advance = $this->advances[min($glyph, count($this->advances) - 1)];

        return round($advance * 1000 / $this->unitsPerEm, 3);
    }

    /**
     * Glyph of a single-byte code of a simple TrueType font, following ISO 32000-1 9.6.6.4:
     * the (3,0) symbolic table (codes, or codes + 0xF000), then (1,0) Mac Roman, then (3,1)
     * Unicode for the ASCII codes. Null when the mapping cannot be resolved without the encoding.
     */
    public function glyphForCode(int $code): ?int
    {
        if (isset($this->cmaps['3.0'])) {
            return $this->cmaps['3.0'][0xF000 + $code] ?? $this->cmaps['3.0'][$code] ?? null;
        }
        if (isset($this->cmaps['1.0'][$code])) {
            return $this->cmaps['1.0'][$code];
        }
        if ($code < 0x80 && isset($this->cmaps['3.1'][$code])) {
            return $this->cmaps['3.1'][$code];
        }

        return null;
    }

    /** @return array<string, array{int, int}> tag => [offset, length] */
    private static function tables(string $font): array
    {
        if (strlen($font) < 12) {
            throw new \InvalidArgumentException('The font program is too short.');
        }

        $count = self::uint16($font, 4);
        $tables = [];
        for ($i = 0; $i < $count; ++$i) {
            $entry = 12 + 16 * $i;
            $offset = self::uint32($font, $entry + 8);
            $length = self::uint32($font, $entry + 12);
            if ($offset + $length > strlen($font)) {
                throw new \InvalidArgumentException('The font program is truncated.');
            }
            $tables[substr($font, $entry, 4)] = [$offset, $length];
        }

        return $tables;
    }

    /** @return array<string, array<int, int>> */
    private static function cmaps(string $font, int $offset): array
    {
        $cmaps = [];
        $count = self::uint16($font, $offset + 2);
        for ($i = 0; $i < $count; ++$i) {
            $record = $offset + 4 + 8 * $i;
            $key = self::uint16($font, $record) . '.' . self::uint16($font, $record + 2);
            if (!in_array($key, ['3.0', '1.0', '3.1'], true) || isset($cmaps[$key])) {
                continue;
            }

            $subtable = $offset + self::uint32($font, $record + 4);
            $map = match (self::uint16($font, $subtable)) {
                0 => self::cmapFormat0($font, $subtable),
                4 => self::cmapFormat4($font, $subtable),
                6 => self::cmapFormat6($font, $subtable),
                default => null,
            };
            if (null !== $map) {
                $cmaps[$key] = $map;
            }
        }

        return $cmaps;
    }

    /** @return array<int, int> */
    private static function cmapFormat0(string $font, int $offset): array
    {
        $map = [];
        for ($code = 0; $code < 256; ++$code) {
            $map[$code] = ord($font[$offset + 6 + $code] ?? "\0");
        }

        return $map;
    }

    /** @return array<int, int> */
    private static function cmapFormat4(string $font, int $offset): array
    {
        $segments = intdiv(self::uint16($font, $offset + 6), 2);
        $endCodes = $offset + 14;
        $startCodes = $endCodes + 2 * $segments + 2;
        $deltas = $startCodes + 2 * $segments;
        $rangeOffsets = $deltas + 2 * $segments;

        $map = [];
        for ($segment = 0; $segment < $segments; ++$segment) {
            $end = self::uint16($font, $endCodes + 2 * $segment);
            $start = self::uint16($font, $startCodes + 2 * $segment);
            $delta = self::uint16($font, $deltas + 2 * $segment);
            $rangeOffsetPosition = $rangeOffsets + 2 * $segment;
            $rangeOffset = self::uint16($font, $rangeOffsetPosition);
            if (0xFFFF === $start || $end < $start) {
                continue;
            }

            for ($code = $start; $code <= $end; ++$code) {
                if (0 === $rangeOffset) {
                    $glyph = ($code + $delta) & 0xFFFF;
                } else {
                    $position = $rangeOffsetPosition + $rangeOffset + 2 * ($code - $start);
                    if ($position + 2 > strlen($font)) {
                        continue;
                    }
                    $glyph = self::uint16($font, $position);
                    if (0 !== $glyph) {
                        $glyph = ($glyph + $delta) & 0xFFFF;
                    }
                }
                if (0 !== $glyph) {
                    $map[$code] = $glyph;
                }
            }
        }

        return $map;
    }

    /** @return array<int, int> */
    private static function cmapFormat6(string $font, int $offset): array
    {
        $first = self::uint16($font, $offset + 6);
        $count = self::uint16($font, $offset + 8);
        $map = [];
        for ($i = 0; $i < $count; ++$i) {
            $map[$first + $i] = self::uint16($font, $offset + 10 + 2 * $i);
        }

        return $map;
    }

    private static function uint16(string $data, int $offset): int
    {
        if ($offset < 0 || $offset + 2 > strlen($data)) {
            throw new \InvalidArgumentException('The font program is truncated.');
        }

        return (ord($data[$offset]) << 8) | ord($data[$offset + 1]);
    }

    private static function uint32(string $data, int $offset): int
    {
        return (self::uint16($data, $offset) << 16) | self::uint16($data, $offset + 2);
    }
}
