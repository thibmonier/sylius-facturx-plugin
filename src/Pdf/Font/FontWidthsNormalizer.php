<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Pdf\Font;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfBoolean;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfToken;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * Makes the glyph widths declared by a PDF (/W of CIDFontType2 fonts, /Widths of simple
 * TrueType fonts) match the advance widths of the embedded font programs, as PDF/A requires
 * (ISO 19005-3, 6.2.11.5).
 *
 * wkhtmltopdf (Qt) writes widths measured on a hinted font, about 0.7 % smaller than the font
 * program ones (e.g. 276 instead of 277.83 for the space of Arial). The corrected font
 * dictionaries are appended as an incremental update, which the PDF parser of the merge reads
 * like the original objects: the rest of the file is not touched.
 */
final class FontWidthsNormalizer
{
    /** Widths may differ by up to 1 unit (veraPDF tolerance). */
    private const TOLERANCE = 1.0;

    /** Fonts with more glyphs are left as they are (full CJK fonts, huge /W arrays). */
    private const MAX_GLYPHS = 20000;

    public function normalize(string $pdf): string
    {
        $parser = new PdfParser(StreamReader::createByString($pdf));
        $reader = new PdfReader($parser);

        /** @var array<int, true> $visited */
        $visited = [];
        /** @var array<int, PdfIndirectObject> $fixed */
        $fixed = [];
        for ($pageNumber = 1, $count = $reader->getPageCount(); $pageNumber <= $count; ++$pageNumber) {
            $resources = $reader->getPage($pageNumber)->getAttribute('Resources');
            $this->visitResources($parser, $resources instanceof PdfType ? $resources : null, $visited, $fixed, 0);
        }

        return [] === $fixed ? $pdf : $this->appendUpdate($pdf, $parser, $fixed);
    }

    /**
     * @param array<int, true> $visited
     * @param array<int, PdfIndirectObject> $fixed
     */
    private function visitResources(PdfParser $parser, ?PdfType $resources, array &$visited, array &$fixed, int $depth): void
    {
        if (!$resources instanceof \setasign\Fpdi\PdfParser\Type\PdfType || $depth > 10) {
            return;
        }

        $resources = PdfType::resolve($resources, $parser);
        if (!$resources instanceof PdfDictionary) {
            return;
        }

        $fonts = PdfValues::get($resources, 'Font', $parser);
        if ($fonts instanceof PdfDictionary) {
            foreach (PdfValues::entries($fonts) as $font) {
                $number = PdfValues::reference($font);
                if (null !== $number && !isset($visited[$number])) {
                    $visited[$number] = true;
                    $this->visitFont($parser, $number, $visited, $fixed);
                }
            }
        }

        // Form XObjects (e.g. pages imported in another PDF) have their own resources.
        $xObjects = PdfValues::get($resources, 'XObject', $parser);
        if ($xObjects instanceof PdfDictionary) {
            foreach (PdfValues::entries($xObjects) as $xObject) {
                $number = PdfValues::reference($xObject);
                if (null === $number || isset($visited[$number])) {
                    continue;
                }
                $visited[$number] = true;
                $stream = PdfType::resolve($xObject, $parser);
                $dictionary = $stream instanceof PdfStream ? PdfValues::streamDictionary($stream) : null;
                if ($dictionary instanceof \setasign\Fpdi\PdfParser\Type\PdfDictionary && 'Form' === PdfValues::name(PdfValues::raw($dictionary, 'Subtype'))) {
                    $this->visitResources($parser, PdfValues::raw($dictionary, 'Resources'), $visited, $fixed, $depth + 1);
                }
            }
        }
    }

    /**
     * @param array<int, true> $visited
     * @param array<int, PdfIndirectObject> $fixed
     */
    private function visitFont(PdfParser $parser, int $objectNumber, array &$visited, array &$fixed): void
    {
        $object = $parser->getIndirectObject($objectNumber);
        $font = $object->value;
        if (!$font instanceof PdfDictionary) {
            return;
        }

        $subtype = PdfValues::name(PdfValues::get($font, 'Subtype', $parser));
        if ('Type0' === $subtype) {
            $descendants = PdfValues::get($font, 'DescendantFonts', $parser);
            $descendant = $descendants instanceof PdfArray ? PdfValues::reference(PdfValues::items($descendants)[0] ?? null) : null;
            if (null !== $descendant && !isset($visited[$descendant])) {
                $visited[$descendant] = true;
                $this->fixCidFont($parser, $parser->getIndirectObject($descendant), $fixed);
            }

            return;
        }

        if ('TrueType' === $subtype) {
            $this->fixSimpleTrueTypeFont($parser, $object, $fixed);
        }
    }

    /** @param array<int, PdfIndirectObject> $fixed */
    private function fixCidFont(PdfParser $parser, PdfIndirectObject $object, array &$fixed): void
    {
        $font = $object->value;
        if (!$font instanceof PdfDictionary || 'CIDFontType2' !== PdfValues::name(PdfValues::get($font, 'Subtype', $parser))) {
            return;
        }

        $metrics = $this->metrics($parser, $font);
        if (!$metrics instanceof \Tmonier\SyliusFacturXPlugin\Pdf\Font\TrueTypeMetrics || $metrics->numGlyphs > self::MAX_GLYPHS) {
            return;
        }

        // CID => glyph id: Identity by default, or a stream of 2-byte glyph ids.
        $map = PdfValues::get($font, 'CIDToGIDMap', $parser);
        $cidToGid = null;
        if ($map instanceof PdfStream) {
            $unpacked = unpack('n*', $map->getUnfilteredStream());
            $cidToGid = array_values(array_map(PdfValues::int(...), false === $unpacked ? [] : $unpacked));
        } elseif ($map instanceof \setasign\Fpdi\PdfParser\Type\PdfType && 'Identity' !== PdfValues::name($map)) {
            return;
        }

        $current = $this->cidWidths($parser, $font);
        $defaultWidth = PdfValues::number(PdfValues::get($font, 'DW', $parser)) ?? 1000.0;

        $widths = [];
        $changed = false;
        $cidCount = null === $cidToGid ? $metrics->numGlyphs : count($cidToGid);
        for ($cid = 0; $cid < $cidCount; ++$cid) {
            $width = $metrics->width(null === $cidToGid ? $cid : $cidToGid[$cid]);
            if (null === $width) {
                return;
            }
            $widths[] = PdfNumeric::create($width);
            if (abs(($current[$cid] ?? $defaultWidth) - $width) > self::TOLERANCE) {
                $changed = true;
            }
        }

        if ($changed) {
            PdfValues::set($font, 'W', PdfArray::create([PdfNumeric::create(0), PdfArray::create($widths)]));
            $fixed[PdfValues::int($object->objectNumber)] = $object;
        }
    }

    /** @param array<int, PdfIndirectObject> $fixed */
    private function fixSimpleTrueTypeFont(PdfParser $parser, PdfIndirectObject $object, array &$fixed): void
    {
        $font = $object->value;
        if (!$font instanceof PdfDictionary) {
            return;
        }

        $metrics = $this->metrics($parser, $font);
        $firstChar = PdfValues::number(PdfValues::get($font, 'FirstChar', $parser));
        $current = PdfValues::get($font, 'Widths', $parser);
        if (!$metrics instanceof \Tmonier\SyliusFacturXPlugin\Pdf\Font\TrueTypeMetrics || null === $firstChar || !$current instanceof PdfArray) {
            return;
        }

        $widths = [];
        $changed = false;
        foreach (PdfValues::items($current) as $index => $value) {
            $width = PdfValues::number(PdfType::resolve($value, $parser)) ?? 0.0;
            $glyph = $metrics->glyphForCode((int) $firstChar + $index);
            $expected = null === $glyph ? null : $metrics->width($glyph);
            if (null !== $expected && abs($expected - $width) > self::TOLERANCE) {
                $width = $expected;
                $changed = true;
            }
            $widths[] = PdfNumeric::create($width);
        }

        if ($changed) {
            PdfValues::set($font, 'Widths', PdfArray::create($widths));
            $fixed[PdfValues::int($object->objectNumber)] = $object;
        }
    }

    private function metrics(PdfParser $parser, PdfDictionary $font): ?TrueTypeMetrics
    {
        $descriptor = PdfValues::get($font, 'FontDescriptor', $parser);
        $program = $descriptor instanceof PdfDictionary ? PdfValues::get($descriptor, 'FontFile2', $parser) : null;
        if (!$program instanceof PdfStream) {
            return null;
        }

        try {
            return TrueTypeMetrics::fromFontProgram($program->getUnfilteredStream());
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<int, float> CID => width declared by /W */
    private function cidWidths(PdfParser $parser, PdfDictionary $font): array
    {
        $array = PdfValues::get($font, 'W', $parser);
        if (!$array instanceof PdfArray) {
            return [];
        }

        $entries = array_map(static fn (PdfType $entry): PdfType => PdfType::resolve($entry, $parser), PdfValues::items($array));
        $widths = [];
        for ($i = 0, $count = count($entries); $i < $count;) {
            $first = PdfValues::number($entries[$i]);
            $next = $entries[$i + 1] ?? null;
            if (null === $first || null === $next) {
                break;
            }
            if ($next instanceof PdfArray) {
                foreach (PdfValues::items($next) as $offset => $width) {
                    $widths[(int) $first + $offset] = PdfValues::number(PdfType::resolve($width, $parser)) ?? 0.0;
                }
                $i += 2;

                continue;
            }

            $last = PdfValues::number($next);
            $width = PdfValues::number($entries[$i + 2] ?? null);
            if (null === $last || null === $width) {
                break;
            }
            for ($cid = (int) $first; $cid <= (int) $last; ++$cid) {
                $widths[$cid] = $width;
            }
            $i += 3;
        }

        return $widths;
    }

    /** @param non-empty-array<int, PdfIndirectObject> $objects */
    private function appendUpdate(string $pdf, PdfParser $parser, array $objects): string
    {
        if (1 !== preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $matches)) {
            throw new \InvalidArgumentException('The PDF has no startxref.');
        }
        $previousXref = (int) $matches[1];

        $update = "\n";
        $offsets = [];
        ksort($objects);
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf) + strlen($update);
            $update .= sprintf("%d %d obj\n%s\nendobj\n", $number, PdfValues::int($object->generationNumber), self::serialize($object->value instanceof PdfType ? $object->value : new PdfNull()));
        }

        $xrefOffset = strlen($pdf) + strlen($update);
        $update .= "xref\n";
        foreach ($offsets as $number => $offset) {
            $update .= sprintf("%d 1\n%010d %05d n \n", $number, $offset, PdfValues::int($objects[$number]->generationNumber));
        }

        $trailer = $parser->getCrossReference()->getTrailer();
        $entries = array_diff_key(PdfValues::entries($trailer), ['Prev' => true, 'XRefStm' => true, 'Size' => true]);
        $size = max((int) (PdfValues::number(PdfValues::raw($trailer, 'Size')) ?? 0), max([0, ...array_keys($objects)]) + 1);
        $entries['Size'] = PdfNumeric::create($size);
        $entries['Prev'] = PdfNumeric::create($previousXref);

        $update .= 'trailer' . "\n" . self::serialize(PdfDictionary::create($entries)) . "\nstartxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf . $update;
    }

    private static function serialize(PdfType $value): string
    {
        return match (true) {
            $value instanceof PdfNumeric => is_int($value->value) ? (string) $value->value : rtrim(rtrim(sprintf('%.5F', PdfValues::number($value) ?? 0.0), '0'), '.'),
            $value instanceof PdfName, $value instanceof PdfString, $value instanceof PdfHexString, $value instanceof PdfToken => self::scalar($value),
            $value instanceof PdfBoolean => true === $value->value ? 'true' : 'false',
            $value instanceof PdfArray => '[' . implode(' ', array_map(self::serialize(...), PdfValues::items($value))) . ']',
            $value instanceof PdfDictionary => '<<' . implode('', array_map(
                static fn (string $key, PdfType $entry): string => '/' . $key . ' ' . self::serialize($entry) . ' ',
                array_keys(PdfValues::entries($value)),
                array_values(PdfValues::entries($value)),
            )) . '>>',
            $value instanceof PdfIndirectObjectReference => sprintf('%d %d R', PdfValues::int($value->value), PdfValues::int($value->generationNumber)),
            $value instanceof PdfNull => 'null',
            default => throw new \InvalidArgumentException(sprintf('Cannot write a %s in a font dictionary.', $value::class)),
        };
    }

    /** Names and strings keep the escaped form read by the parser. */
    private static function scalar(PdfName|PdfString|PdfHexString|PdfToken $value): string
    {
        $raw = is_scalar($value->value) ? (string) $value->value : '';

        return match (true) {
            $value instanceof PdfName => '/' . $raw,
            $value instanceof PdfString => '(' . $raw . ')',
            $value instanceof PdfHexString => '<' . $raw . '>',
            default => $raw,
        };
    }
}
