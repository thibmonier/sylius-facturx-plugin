<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Pdf;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;
use Tests\Tmonier\SyliusFacturXPlugin\Unit\InvoiceBuilderTrait;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapper;
use Tmonier\SyliusFacturXPlugin\Pdf\FacturXPdfEmbedder;
use Tmonier\SyliusFacturXPlugin\Xml\CiiXmlWriter;

final class FacturXPdfEmbedderTest extends TestCase
{
    use FacturXAssertionsTrait;
    use InvoiceBuilderTrait;

    public function testItEmbedsTheXmlInAPdfA3WithTheFacturXMetadata(): void
    {
        $xml = $this->xml();

        $pdf = (new FacturXPdfEmbedder())->embed(self::sourcePdf(), $xml);

        self::assertSame($xml, self::assertFacturXPdf($pdf));
        self::assertStringContainsString('/F (factur-x.xml)', $pdf);
        self::assertStringContainsString('Sylius Factur-X Plugin', $pdf);
    }

    public function testTheAttachmentRelationshipIsConfigurable(): void
    {
        $pdf = (new FacturXPdfEmbedder('Data'))->embed(self::sourcePdf(), $this->xml());

        self::assertFacturXPdf($pdf, 'Data');
    }

    public function testTheDeterministicModeGivesTheSameBytes(): void
    {
        $embedder = new FacturXPdfEmbedder('Alternative', true);
        $xml = $this->xml();
        $source = self::sourcePdf();

        self::assertSame(md5($embedder->embed($source, $xml)), md5($embedder->embed($source, $xml)));
    }

    public function testItKeepsThePagesOfTheSourcePdf(): void
    {
        $source = new \FPDF();
        $source->SetFont('Helvetica', '', 12);
        foreach ([1, 2, 3] as $page) {
            $source->AddPage();
            $source->Cell(0, 10, sprintf('Page %d', $page));
        }

        $pdf = (new FacturXPdfEmbedder())->embed($source->Output('S'), $this->xml());

        self::assertSame(3, preg_match_all('#/Type\s*/Page\b#', $pdf));
    }

    private function xml(): string
    {
        return (new CiiXmlWriter())->write((new FacturXInvoiceMapper())->map(self::multiRateInvoice(), self::settings()));
    }

    private static function sourcePdf(): string
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->Cell(0, 10, 'Invoice 2026/10/000001');

        return $pdf->Output('S');
    }
}
