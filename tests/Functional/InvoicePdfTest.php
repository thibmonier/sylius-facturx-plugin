<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Sylius\InvoicingPlugin\Generator\InvoicePdfFileGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;
use Tmonier\SyliusFacturXPlugin\Event\FacturXInvoiceGenerated;

final class InvoicePdfTest extends WebTestCase
{
    use FacturXAssertionsTrait;
    use FacturXTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheAdminDownloadServesAFacturXPdf(): void
    {
        $this->loginAdmin();
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));
        $this->removeStoredPdf($invoice);

        $this->client->request('GET', sprintf('/admin/invoices/%s/download', $invoice->id()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        $pdf = (string) $this->client->getResponse()->getContent();
        $xml = self::assertFacturXPdf($pdf);
        self::assertValidAgainstXsd($xml);
        self::assertEn16931BusinessRules($xml);
        self::assertSame($invoice->number(), self::xmlValue($xml, '//rsm:ExchangedDocument/ram:ID'));
        self::keepForValidation('factur-x.pdf', $pdf);
    }

    public function testTheShopDownloadServesTheSameFacturXPdf(): void
    {
        $invoice = $this->findInvoice(withShopUser: true);
        $this->enableFacturX($this->channelOf($invoice));
        $this->removeStoredPdf($invoice);
        $this->client->loginUser($this->shopUserOf($invoice), 'shop');

        $this->client->request('GET', sprintf('/en_US/invoices/%s/download', $invoice->id()));

        self::assertResponseIsSuccessful();
        self::assertFacturXPdf((string) $this->client->getResponse()->getContent());
    }

    public function testThePdfStaysPlainWhenFacturXIsNotEnabled(): void
    {
        $invoice = $this->findInvoice();

        /** @var InvoicePdfFileGeneratorInterface $generator */
        $generator = self::getContainer()->get('sylius_invoicing.generator.invoice_pdf_file');
        $pdf = $generator->generate($invoice)->content();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringNotContainsString('factur-x.xml', $pdf);
    }

    public function testTheEventCarriesTheGeneratedFiles(): void
    {
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));
        /** @var list<FacturXInvoiceGenerated> $events */
        $events = [];
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(FacturXInvoiceGenerated::NAME, static function (FacturXInvoiceGenerated $event) use (&$events): void {
            $events[] = $event;
        });

        /** @var InvoicePdfFileGeneratorInterface $generator */
        $generator = self::getContainer()->get('sylius_invoicing.generator.invoice_pdf_file');
        $invoicePdf = $generator->generate($invoice);

        self::assertCount(1, $events);
        self::assertSame($invoice, $events[0]->getInvoice());
        self::assertSame($invoicePdf->content(), $events[0]->getPdfContent());
        self::assertSame($invoicePdf->filename(), $events[0]->getPdfFilename());
        self::assertSame(self::assertFacturXPdf($invoicePdf->content()), $events[0]->getXml());
    }

    public function testIncompleteSellerDataKeepsThePlainPdf(): void
    {
        $invoice = $this->findInvoice();
        $settings = $this->enableFacturX($this->channelOf($invoice));
        $settings->setSiren(null);
        $settings->setVatNumber(null);
        $invoice->shopBillingData()->setTaxId(null);
        $this->entityManager()->flush();

        /** @var InvoicePdfFileGeneratorInterface $generator */
        $generator = self::getContainer()->get('sylius_invoicing.generator.invoice_pdf_file');
        $pdf = $generator->generate($invoice)->content();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringNotContainsString('factur-x.xml', $pdf);
    }
}
