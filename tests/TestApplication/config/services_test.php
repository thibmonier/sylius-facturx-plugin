<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Tmonier\SyliusFacturXPlugin\Pdf\FakeInvoicePdfFileGenerator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    // Without wkhtmltopdf (local runs), the invoice PDF is a small FPDF document: the Factur-X
    // decorator still embeds the XML into it. CI installs wkhtmltopdf and renders the real PDF.
    $binary = $_SERVER['WKHTMLTOPDF_PATH'] ?? $_ENV['WKHTMLTOPDF_PATH'] ?? '/usr/local/bin/wkhtmltopdf';
    if (is_string($binary) && is_executable($binary) && !filter_var($_SERVER['TEST_FACTURX_FAKE_PDF'] ?? false, \FILTER_VALIDATE_BOOLEAN)) {
        return;
    }

    $container->services()
        ->set('tests.tmonier_facturx.fake_invoice_pdf_file_generator', FakeInvoicePdfFileGenerator::class)
        ->decorate('sylius_invoicing.generator.invoice_pdf_file', null, 10)
        ->args([service('sylius_invoicing.generator.invoice_file_name')])
    ;
};
