<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Sylius\InvoicingPlugin\Generator\InvoicePdfFileGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;

/**
 * The invoice rendered by wkhtmltopdf with the Invoicing Plugin template (embedded TrueType
 * fonts, whose widths wkhtmltopdf writes wrong). Runs only when WKHTMLTOPDF_PATH points to an
 * executable wkhtmltopdf (the CI "Factur-X validation" job).
 */
final class RealInvoicePdfTest extends WebTestCase
{
    use FacturXAssertionsTrait;
    use FacturXTestTrait;

    protected function setUp(): void
    {
        $binary = $_SERVER['WKHTMLTOPDF_PATH'] ?? getenv('WKHTMLTOPDF_PATH');
        if (!is_string($binary) || !is_executable($binary) || filter_var($_SERVER['TEST_FACTURX_FAKE_PDF'] ?? false, \FILTER_VALIDATE_BOOLEAN)) {
            self::markTestSkipped('WKHTMLTOPDF_PATH does not point to an executable wkhtmltopdf.');
        }

        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheInvoiceRenderedByWkhtmltopdfBecomesAConformantFacturXPdf(): void
    {
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));

        /** @var InvoicePdfFileGeneratorInterface $generator */
        $generator = self::getContainer()->get('sylius_invoicing.generator.invoice_pdf_file');
        $pdf = $generator->generate($invoice)->content();

        self::assertMatchesRegularExpression('#/FontFile2\s+\d+ 0 R#', $pdf, 'Embedded TrueType fonts: not the FPDF placeholder');
        $xml = self::assertFacturXPdf($pdf);
        self::assertValidAgainstXsd($xml);
        self::assertEn16931BusinessRules($xml);
        self::keepForValidation('factur-x-wkhtmltopdf.pdf', $pdf);
    }
}
