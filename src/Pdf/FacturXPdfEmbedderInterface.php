<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Pdf;

interface FacturXPdfEmbedderInterface
{
    public const ATTACHMENT_FILENAME = 'factur-x.xml';

    /**
     * Returns a PDF/A-3 copy of the PDF with the XML embedded as "factur-x.xml" and the
     * Factur-X XMP metadata.
     */
    public function embed(string $pdfContent, string $xml): string;
}
