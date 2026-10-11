<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Pdf;

use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tmonier\SyliusFacturXPlugin\Pdf\Font\FontWidthsNormalizer;

/**
 * Merges the XML into the PDF with horstoeko/zugferd (FPDI): the pages are imported in a new
 * PDF/A-3B document with an sRGB output intent, the "factur-x.xml" associated file and the XMP
 * metadata of the Factur-X extension schema (DocumentType INVOICE, ConformanceLevel EN 16931).
 *
 * The glyph widths of the source PDF are corrected first (PDF/A rule 6.2.11.5): when that is not
 * possible, the source PDF is merged as it is and the problem is logged.
 */
final class FacturXPdfEmbedder implements FacturXPdfEmbedderInterface
{
    public function __construct(
        private readonly string $attachmentRelationship = 'Alternative',
        private readonly bool $deterministic = false,
        private readonly FontWidthsNormalizer $fontWidthsNormalizer = new FontWidthsNormalizer(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function embed(string $pdfContent, string $xml): string
    {
        try {
            $pdfContent = $this->fontWidthsNormalizer->normalize($pdfContent);
        } catch (\Throwable $exception) {
            $this->logger->warning('The glyph widths of the invoice PDF cannot be checked, the PDF may not be PDF/A conformant: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }

        $merger = new ZugferdDocumentPdfMerger($xml, $pdfContent);
        $merger->setAttachmentRelationshipType($this->attachmentRelationship);
        $merger->setAdditionalCreatorTool('Sylius Factur-X Plugin');
        $merger->setDeterministicModeEnabled($this->deterministic);

        return $merger->generateDocument()->downloadString();
    }
}
