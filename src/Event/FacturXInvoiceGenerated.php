<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Event;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched as "tmonier_facturx.invoice_generated" every time a Factur-X PDF is built for an
 * invoice PDF of the Invoicing Plugin, before the Invoicing Plugin stores it.
 *
 * Extension point for connectors that transmit invoices to a "plateforme agréée" (PA): the
 * event is dispatched again if the PDF is ever regenerated (e.g. lost from the storage), so
 * listeners must be idempotent (use the invoice id).
 */
final class FacturXInvoiceGenerated extends Event
{
    public const NAME = 'tmonier_facturx.invoice_generated';

    public function __construct(
        private readonly InvoiceInterface $invoice,
        private readonly string $xml,
        private readonly string $pdfContent,
        private readonly string $pdfFilename,
    ) {
    }

    public function getInvoice(): InvoiceInterface
    {
        return $this->invoice;
    }

    /** The factur-x.xml content (CII, EN 16931 profile). */
    public function getXml(): string
    {
        return $this->xml;
    }

    /** The Factur-X PDF/A-3 content. */
    public function getPdfContent(): string
    {
        return $this->pdfContent;
    }

    /**
     * The file name under which the Invoicing Plugin stores the PDF in its "sylius_invoicing"
     * storage (resolve it with the PdfFileManager of sylius/pdf-generation-bundle once stored).
     */
    public function getPdfFilename(): string
    {
        return $this->pdfFilename;
    }
}
