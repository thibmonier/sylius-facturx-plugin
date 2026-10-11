<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Tmonier\SyliusFacturXPlugin\Pdf\FakeInvoicePdfFileGenerator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Test application only (dev and test environments): when WKHTMLTOPDF_PATH does not point to
 * an executable wkhtmltopdf, the invoice PDF is a one-page FPDF placeholder ("Invoice <number>")
 * instead of the Invoicing Plugin template, so that the Factur-X pipeline runs anywhere. The
 * decision is taken when the container is compiled: clear the cache after changing the variable.
 * TEST_FACTURX_FAKE_PDF=1 forces the placeholder.
 */
return static function (ContainerConfigurator $container): void {
    if (!in_array($container->env(), ['dev', 'test'], true)) {
        return;
    }

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
