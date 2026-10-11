<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;

final class AdminInvoiceXmlTest extends WebTestCase
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

    public function testEveryInvoiceOfTheFixturesGivesAValidXml(): void
    {
        $invoices = $this->findAllInvoices();
        self::assertNotEmpty($invoices);
        foreach ($invoices as $invoice) {
            $this->enableFacturX($this->channelOf($invoice));
        }

        /** @var FacturXGeneratorInterface $generator */
        $generator = self::getContainer()->get('tmonier_facturx.generator');
        foreach ($invoices as $invoice) {
            $xml = $generator->generateXml($invoice);

            self::assertValidAgainstXsd($xml);
            self::assertEn16931BusinessRules($xml);
            self::assertSame(sprintf('%.2f', $invoice->total() / 100), self::xmlValue($xml, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'), $invoice->number());
            self::assertSame($invoice->number(), self::xmlValue($xml, '//rsm:ExchangedDocument/ram:ID'));
        }
    }

    public function testTheAdminDownloadsTheXmlOfAnInvoice(): void
    {
        $this->loginAdmin();
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));

        $this->client->request('GET', sprintf('/admin/invoices/%s/factur-x.xml', $invoice->id()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');
        self::assertStringStartsWith('attachment;', $disposition);
        self::assertStringContainsString(sprintf('filename=%s-factur-x.xml', str_replace('/', '_', $invoice->number())), $disposition);

        $xml = (string) $this->client->getResponse()->getContent();
        self::assertValidAgainstXsd($xml);
        self::assertEn16931BusinessRules($xml);
        self::assertSame('732829320', self::xmlValue($xml, '//ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
        self::keepForValidation('factur-x.xml', $xml);
    }

    public function testTheDownloadIsRefusedWhenFacturXIsNotEnabledForTheChannel(): void
    {
        $this->loginAdmin();
        $invoice = $this->findInvoice();

        $this->client->request('GET', sprintf('/admin/invoices/%s/factur-x.xml', $invoice->id()));

        self::assertResponseRedirects(sprintf('/admin/invoices/%s', $invoice->id()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="error"]', 'The Factur-X XML cannot be generated');
        self::assertSelectorNotExists('[data-test-download-facturx-xml]');
    }

    public function testTheInvoiceAndOrderPagesLinkToTheXml(): void
    {
        $this->loginAdmin();
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));
        $url = sprintf('/admin/invoices/%s/factur-x.xml', $invoice->id());

        $this->client->request('GET', sprintf('/admin/invoices/%s', $invoice->id()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('[data-test-download-facturx-xml][href="%s"]', $url));

        $this->client->request('GET', sprintf('/admin/orders/%d', $this->idOf($invoice->order())));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('[data-test-download-facturx-xml][href="%s"]', $url));

        // Grid action (the first page of the grid does not necessarily list this invoice).
        $this->client->request('GET', '/admin/invoices/');
        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('#href="/admin/invoices/[0-9a-f-]+/factur-x\.xml"#', (string) $this->client->getResponse()->getContent());
    }

    public function testTheXmlDownloadNeedsAnAdmin(): void
    {
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));

        $this->client->request('GET', sprintf('/admin/invoices/%s/factur-x.xml', $invoice->id()));

        self::assertResponseRedirects();
        self::assertStringEndsWith('/admin/login', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
