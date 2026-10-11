<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Pdf\Font;

/**
 * Builds tiny TrueType font programs (only the tables needed for the metrics) and PDFs
 * embedding them, so that the tests need no font file.
 */
trait SyntheticPdfTrait
{
    /**
     * @param list<int> $advances advance width per glyph id, in font units
     * @param array<int, int> $cmap code => glyph id, written as a (3,0) or (3,1) format 4 table
     */
    private static function trueTypeFont(array $advances, int $unitsPerEm = 2048, array $cmap = [], int $cmapEncoding = 1): string
    {
        $head = str_repeat("\0", 18) . pack('n', $unitsPerEm) . str_repeat("\0", 34);
        $hhea = str_repeat("\0", 34) . pack('n', count($advances));
        $maxp = pack('Nn', 0x00005000, count($advances));
        $hmtx = '';
        foreach ($advances as $advance) {
            $hmtx .= pack('nn', $advance, 0);
        }

        $tables = ['head' => $head, 'hhea' => $hhea, 'hmtx' => $hmtx, 'maxp' => $maxp];
        if ([] !== $cmap) {
            // Format 4, one segment per code plus the final 0xFFFF segment.
            ksort($cmap);
            $segments = count($cmap) + 1;
            $ends = $starts = $deltas = $ranges = '';
            foreach ($cmap as $code => $glyph) {
                $ends .= pack('n', $code);
                $starts .= pack('n', $code);
                $deltas .= pack('n', ($glyph - $code) & 0xFFFF);
                $ranges .= pack('n', 0);
            }
            $ends .= pack('n', 0xFFFF);
            $starts .= pack('n', 0xFFFF);
            $deltas .= pack('n', 1);
            $ranges .= pack('n', 0);
            $body = pack('n', 2 * $segments) . pack('nnn', 0, 0, 0) . $ends . pack('n', 0) . $starts . $deltas . $ranges;
            $subtable = pack('nnn', 4, 6 + strlen($body), 0) . $body;
            $tables['cmap'] = pack('nn', 0, 1) . pack('nnN', 3, $cmapEncoding, 12) . $subtable;
        }

        $font = pack('Nnnnn', 0x00010000, count($tables), 0, 0, 0);
        $offset = 12 + 16 * count($tables);
        $directory = $data = '';
        foreach ($tables as $tag => $table) {
            $directory .= $tag . pack('NNN', 0, $offset + strlen($data), strlen($table));
            $data .= $table . str_repeat("\0", (4 - strlen($table) % 4) % 4);
        }

        return $font . $directory . $data;
    }

    /**
     * One-page PDF 1.4 with a classic cross-reference table.
     *
     * @param array<int, string> $objects object number => body (1 = catalog, 2 = pages, 3 = page)
     */
    private static function pdf(array $objects, string $pageResources): string
    {
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $pageResources . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $number, $body);
        }

        $size = max(array_keys($objects)) + 1;
        $xref = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", $size);
        for ($number = 1; $number < $size; ++$number) {
            $pdf .= isset($offsets[$number]) ? sprintf("%010d 00000 n \n", $offsets[$number]) : "0000000000 65535 f \n";
        }

        return $pdf . sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", $size, $xref);
    }

    private static function stream(string $data, bool $compress = true): string
    {
        if ($compress) {
            $data = (string) gzcompress($data);

            return sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($data), $data);
        }

        return sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($data), $data);
    }

    /** Type0 font (object 4) with a CIDFontType2 descendant (5), its descriptor (6) and program (7). */
    private static function cidFontPdf(string $widths, string $font, string $extra = ''): string
    {
        return self::pdf([
            4 => '<< /Type /Font /Subtype /Type0 /BaseFont /Test /Encoding /Identity-H /DescendantFonts [5 0 R] >>',
            5 => '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Test /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R /CIDToGIDMap /Identity /W ' . $widths . $extra . ' >>',
            6 => '<< /Type /FontDescriptor /FontName /Test /Flags 4 /FontBBox [0 0 1000 1000] /ItalicAngle 0 /Ascent 900 /Descent -200 /CapHeight 700 /StemV 80 /FontFile2 7 0 R >>',
            7 => self::stream($font),
        ], '<< /Font << /F1 4 0 R >> >>');
    }
}
