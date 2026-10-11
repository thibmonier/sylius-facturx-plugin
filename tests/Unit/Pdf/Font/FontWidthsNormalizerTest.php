<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Pdf\Font;

use PHPUnit\Framework\TestCase;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfType;
use Tmonier\SyliusFacturXPlugin\Pdf\Font\FontWidthsNormalizer;
use Tmonier\SyliusFacturXPlugin\Pdf\Font\PdfValues;
use Tmonier\SyliusFacturXPlugin\Pdf\Font\TrueTypeMetrics;

final class FontWidthsNormalizerTest extends TestCase
{
    use SyntheticPdfTrait;

    /** Advances of Arial (2048 units per em): .notdef, space, digit, uppercase W. */
    private const ADVANCES = [1536, 569, 1139, 1933];

    public function testTheMetricsAreReadFromTheFontProgram(): void
    {
        $metrics = TrueTypeMetrics::fromFontProgram(self::trueTypeFont(self::ADVANCES, cmap: [0x20 => 1, 0x30 => 2]));

        self::assertSame(2048, $metrics->unitsPerEm);
        self::assertSame(4, $metrics->numGlyphs);
        self::assertSame(750.0, $metrics->width(0));
        self::assertSame(277.832, $metrics->width(1));
        self::assertSame(556.152, $metrics->width(2));
        self::assertNull($metrics->width(4));
        self::assertSame(2, $metrics->glyphForCode(0x30));
        self::assertNull($metrics->glyphForCode(0x31));
    }

    public function testInvalidFontProgramsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TrueTypeMetrics::fromFontProgram('not a font');
    }

    public function testItRewritesTheWidthsOfACidFontWrittenByWkhtmltopdf(): void
    {
        // Qt writes the widths of the hinted font: 744 276 552 965 instead of 750 277.83 556.15 943.85.
        $pdf = self::cidFontPdf('[0 [744 276 552 965]]', self::trueTypeFont(self::ADVANCES));

        $normalized = (new FontWidthsNormalizer())->normalize($pdf);

        self::assertSame($pdf, substr($normalized, 0, strlen($pdf)), 'The original bytes are kept (incremental update)');
        self::assertSame([[0, [750, 277.832, 556.152, 943.848]]], $this->cidWidths($normalized, 5));
        self::assertSame($normalized, (new FontWidthsNormalizer())->normalize($normalized), 'Idempotent');
    }

    public function testItAlsoFixesWidthsInheritedFromTheDefaultWidthAndRanges(): void
    {
        $pdf = self::cidFontPdf('[0 1 744 3 [965]]', self::trueTypeFont(self::ADVANCES), ' /DW 1000');

        $normalized = (new FontWidthsNormalizer())->normalize($pdf);

        self::assertSame([[0, [750, 277.832, 556.152, 943.848]]], $this->cidWidths($normalized, 5));
    }

    public function testCorrectWidthsAreLeftUntouched(): void
    {
        $pdf = self::cidFontPdf('[0 [750 277.83 556 943.85]]', self::trueTypeFont(self::ADVANCES));

        self::assertSame($pdf, (new FontWidthsNormalizer())->normalize($pdf), 'Differences up to 1 unit are tolerated');
    }

    public function testItRewritesTheWidthsOfASimpleTrueTypeFont(): void
    {
        $font = self::trueTypeFont(self::ADVANCES, cmap: [0xF020 => 1, 0xF030 => 2, 0xF057 => 3], cmapEncoding: 0);
        $pdf = self::pdf([
            4 => '<< /Type /Font /Subtype /TrueType /BaseFont /Test /FirstChar 32 /LastChar 34 /Widths [276 600 500] /FontDescriptor 6 0 R >>',
            6 => '<< /Type /FontDescriptor /FontName /Test /Flags 4 /FontBBox [0 0 1000 1000] /ItalicAngle 0 /Ascent 900 /Descent -200 /CapHeight 700 /StemV 80 /FontFile2 7 0 R >>',
            7 => self::stream($font),
        ], '<< /Font << /F1 4 0 R >> >>');

        $normalized = (new FontWidthsNormalizer())->normalize($pdf);

        // Code 32 is the space (glyph 1); codes 33 and 34 have no glyph in the font: kept.
        $widths = PdfValues::get($this->dictionary($normalized, 4), 'Widths', $this->parser($normalized));
        self::assertInstanceOf(PdfArray::class, $widths);
        self::assertSame([277.832, 600, 500], array_map(static fn (PdfType $width): mixed => $width->value, PdfValues::items($widths)));
    }

    public function testItFollowsTheFontsOfFormXObjects(): void
    {
        $pdf = self::pdf([
            4 => '<< /Type /Font /Subtype /Type0 /BaseFont /Test /Encoding /Identity-H /DescendantFonts [5 0 R] >>',
            5 => '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Test /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R /W [0 [744 276 552 965]] >>',
            6 => '<< /Type /FontDescriptor /FontName /Test /Flags 4 /FontBBox [0 0 1000 1000] /ItalicAngle 0 /Ascent 900 /Descent -200 /CapHeight 700 /StemV 80 /FontFile2 7 0 R >>',
            7 => self::stream(self::trueTypeFont(self::ADVANCES)),
            8 => "<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] /Resources << /Font << /F1 4 0 R >> >> /Length 0 >>\nstream\n\nendstream",
        ], '<< /XObject << /X1 8 0 R >> >>');

        $normalized = (new FontWidthsNormalizer())->normalize($pdf);

        self::assertSame([[0, [750, 277.832, 556.152, 943.848]]], $this->cidWidths($normalized, 5));
    }

    public function testAPdfWithoutEmbeddedTrueTypeFontsIsUnchanged(): void
    {
        $pdf = self::pdf([4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'], '<< /Font << /F1 4 0 R >> >>');

        self::assertSame($pdf, (new FontWidthsNormalizer())->normalize($pdf));
    }

    /** @return list<mixed> */
    private function cidWidths(string $pdf, int $objectNumber): array
    {
        $parser = $this->parser($pdf);
        $widths = PdfValues::get($this->dictionary($pdf, $objectNumber), 'W', $parser);
        self::assertInstanceOf(PdfArray::class, $widths);

        $result = [];
        $entries = PdfValues::items($widths);
        for ($i = 0; $i < count($entries); $i += 2) {
            $first = $entries[$i];
            $list = $entries[$i + 1];
            self::assertInstanceOf(PdfNumeric::class, $first);
            self::assertInstanceOf(PdfArray::class, $list);
            $result[] = [$first->value, array_map(static fn (PdfType $width): mixed => $width->value, PdfValues::items($list))];
        }

        return $result;
    }

    private function dictionary(string $pdf, int $objectNumber): PdfDictionary
    {
        $dictionary = $this->parser($pdf)->getIndirectObject($objectNumber)->value;
        self::assertInstanceOf(PdfDictionary::class, $dictionary);

        return $dictionary;
    }

    private function parser(string $pdf): PdfParser
    {
        return new PdfParser(StreamReader::createByString($pdf));
    }
}
