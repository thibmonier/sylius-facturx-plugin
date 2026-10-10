<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Generator;

use Psr\Log\LoggerInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Generator\InvoicePdfFileGeneratorInterface;
use Sylius\InvoicingPlugin\Model\InvoicePdf;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tmonier\SyliusFacturXPlugin\Event\FacturXInvoiceGenerated;

/**
 * Decorates the PDF generator of the Invoicing Plugin: the PDF it renders becomes a Factur-X
 * PDF/A-3 before being stored, so the existing admin and shop download URLs serve it.
 *
 * A failure never prevents the invoice from being created: it is logged and the original PDF
 * is kept (the XML can still be downloaded from the admin once the problem is fixed).
 */
final class FacturXInvoicePdfFileGenerator implements InvoicePdfFileGeneratorInterface
{
    public function __construct(
        private readonly InvoicePdfFileGeneratorInterface $decorated,
        private readonly FacturXGeneratorInterface $facturXGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(InvoiceInterface $invoice): InvoicePdf
    {
        $invoicePdf = $this->decorated->generate($invoice);
        if (!$this->facturXGenerator->isEnabledFor($invoice)) {
            return $invoicePdf;
        }

        try {
            $xml = $this->facturXGenerator->generateXml($invoice);
            $pdfContent = $this->facturXGenerator->embedIntoPdf($invoicePdf->content(), $xml);
        } catch (\Throwable $exception) {
            $this->logger->error('The Factur-X PDF of the invoice "{number}" cannot be generated, the plain PDF is kept: {message}', [
                'number' => $invoice->number(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $invoicePdf;
        }

        $facturXPdf = new InvoicePdf($invoicePdf->filename(), $pdfContent);
        $facturXPdf->setFullPath($invoicePdf->fullPath());

        try {
            $this->eventDispatcher->dispatch(
                new FacturXInvoiceGenerated($invoice, $xml, $pdfContent, $invoicePdf->filename()),
                FacturXInvoiceGenerated::NAME,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('A listener of the Factur-X event of the invoice "{number}" failed: {message}', [
                'number' => $invoice->number(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }

        return $facturXPdf;
    }
}
