<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;
use Tests\Tmonier\SyliusFacturXPlugin\PaymentTerms\TestPaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\CompositeInvoicePaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTerms;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;

/** Per-order due date (BT-9) and payment terms (BT-20) through a tagged provider (test application). */
final class InvoicePaymentTermsTest extends WebTestCase
{
    use FacturXAssertionsTrait;
    use FacturXTestTrait;

    private const TERMS = '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms';

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheProvidersAreWired(): void
    {
        self::assertInstanceOf(CompositeInvoicePaymentTermsProvider::class, self::getContainer()->get(InvoicePaymentTermsProviderInterface::class));
    }

    public function testATaggedProviderGivesTheTermsOfAnOrder(): void
    {
        $invoice = $this->findInvoice();
        $this->enableFacturX($this->channelOf($invoice));
        $this->provider()->setTerms((string) $invoice->order()->getNumber(), new InvoicePaymentTerms(
            new \DateTimeImmutable('2027-01-31'),
            'Payment on account: 45 days end of month. Late payment penalties: 3 times the legal interest rate; recovery indemnity: 40 EUR.',
        ));

        $xml = $this->generator()->generateXml($invoice);

        self::assertValidAgainstXsd($xml);
        self::assertEn16931BusinessRules($xml);
        self::assertSame('20270131', self::xmlValue($xml, self::TERMS . '/ram:DueDateDateTime/udt:DateTimeString'));
        self::assertStringStartsWith('Payment on account: 45 days end of month.', (string) self::xmlValue($xml, self::TERMS . '/ram:Description'));
    }

    public function testOtherOrdersKeepTheChannelSettings(): void
    {
        $invoice = $this->findInvoice();
        $settings = $this->enableFacturX($this->channelOf($invoice));
        $settings->setPaymentTerms('Payment within 30 days.');
        $this->entityManager()->flush();
        $this->provider()->setTerms('another order', new InvoicePaymentTerms(new \DateTimeImmutable('2027-01-31'), 'Not this one'));

        $xml = $this->generator()->generateXml($invoice);

        self::assertValidAgainstXsd($xml);
        self::assertSame('Payment within 30 days.', self::xmlValue($xml, self::TERMS . '/ram:Description'));
        $expectedDueDate = InvoiceInterface::PAYMENT_STATE_COMPLETED === $invoice->paymentState()
            ? null
            : \DateTimeImmutable::createFromInterface($invoice->issuedAt())->modify('+30 days')->format('Ymd');
        self::assertSame($expectedDueDate, self::xmlValue($xml, self::TERMS . '/ram:DueDateDateTime/udt:DateTimeString'));
    }

    private function provider(): TestPaymentTermsProvider
    {
        $provider = self::getContainer()->get('tmonier_facturx.test.payment_terms_provider');
        self::assertInstanceOf(TestPaymentTermsProvider::class, $provider);

        return $provider;
    }

    private function generator(): FacturXGeneratorInterface
    {
        $generator = self::getContainer()->get('tmonier_facturx.generator');
        self::assertInstanceOf(FacturXGeneratorInterface::class, $generator);

        return $generator;
    }
}
