<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Pdf;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Generator\InvoiceFileNameGeneratorInterface;
use Sylius\InvoicingPlugin\Generator\InvoicePdfFileGeneratorInterface;
use Sylius\InvoicingPlugin\Model\InvoicePdf;

/** Replaces the wkhtmltopdf rendering of the invoice when the binary is not installed. */
final class FakeInvoicePdfFileGenerator implements InvoicePdfFileGeneratorInterface
{
    public function __construct(private readonly InvoiceFileNameGeneratorInterface $fileNameGenerator)
    {
    }

    public function generate(InvoiceInterface $invoice): InvoicePdf
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->Cell(0, 10, sprintf('Invoice %s', $invoice->number()));

        return new InvoicePdf($this->fileNameGenerator->generateForPdf($invoice), $pdf->Output('S'));
    }
}
