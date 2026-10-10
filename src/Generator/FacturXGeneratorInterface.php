<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Generator;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;

interface FacturXGeneratorInterface
{
    /** Factur-X is enabled globally and in the settings of the invoice channel. */
    public function isEnabledFor(InvoiceInterface $invoice): bool;

    /** @throws FacturXException */
    public function buildDocument(InvoiceInterface $invoice): FacturXInvoice;

    /**
     * The factur-x.xml content of the invoice.
     *
     * @throws FacturXException
     */
    public function generateXml(InvoiceInterface $invoice): string;

    /**
     * The PDF/A-3 with the embedded factur-x.xml.
     *
     * @throws FacturXException
     */
    public function embedIntoPdf(string $pdfContent, string $xml): string;
}
