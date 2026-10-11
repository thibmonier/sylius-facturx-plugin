<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Pdf;

use horstoeko\zugferd\ZugferdDocumentPdfMerger;

/**
 * Merges the XML into the PDF with horstoeko/zugferd (FPDI): the pages are imported in a new
 * PDF/A-3B document with an sRGB output intent, the "factur-x.xml" associated file and the XMP
 * metadata of the Factur-X extension schema (DocumentType INVOICE, ConformanceLevel EN 16931).
 */
final class FacturXPdfEmbedder implements FacturXPdfEmbedderInterface
{
    public function __construct(
        private readonly string $attachmentRelationship = 'Alternative',
        private readonly bool $deterministic = false,
    ) {
    }

    public function embed(string $pdfContent, string $xml): string
    {
        $merger = new ZugferdDocumentPdfMerger($xml, $pdfContent);
        $merger->setAttachmentRelationshipType($this->attachmentRelationship);
        $merger->setAdditionalCreatorTool('Sylius Factur-X Plugin');
        $merger->setDeterministicModeEnabled($this->deterministic);

        return $merger->generateDocument()->downloadString();
    }
}
