<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Generator\InvoicePdfFileGeneratorInterface;
use Sylius\InvoicingPlugin\Model\InvoicePdf;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Tmonier\SyliusFacturXPlugin\Unit\InvoiceBuilderTrait;
use Tmonier\SyliusFacturXPlugin\Event\FacturXInvoiceGenerated;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXInvoicePdfFileGenerator;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;

final class FacturXInvoicePdfFileGeneratorTest extends TestCase
{
    use InvoiceBuilderTrait;

    /** @var list<string> */
    private array $logs = [];

    /** @var list<FacturXInvoiceGenerated> */
    private array $events = [];

    public function testItReplacesThePdfByTheFacturXPdfAndDispatchesTheEvent(): void
    {
        $invoice = self::multiRateInvoice();

        $pdf = $this->generator(new StubFacturXGenerator())->generate($invoice);

        self::assertSame('2026_10_000001.pdf', $pdf->filename());
        self::assertSame('facturx(<xml/>)(%PDF original)', $pdf->content());
        self::assertSame('/var/invoices/2026_10_000001.pdf', $pdf->fullPath());
        self::assertCount(1, $this->events);
        self::assertSame($invoice, $this->events[0]->getInvoice());
        self::assertSame('<xml/>', $this->events[0]->getXml());
        self::assertSame('facturx(<xml/>)(%PDF original)', $this->events[0]->getPdfContent());
        self::assertSame('2026_10_000001.pdf', $this->events[0]->getPdfFilename());
        self::assertSame([], $this->logs);
    }

    public function testItKeepsThePlainPdfWhenFacturXIsDisabledForTheChannel(): void
    {
        $pdf = $this->generator(new StubFacturXGenerator(enabled: false))->generate(self::multiRateInvoice());

        self::assertSame('%PDF original', $pdf->content());
        self::assertSame([], $this->events);
    }

    public function testAFailureKeepsThePlainPdfAndIsLogged(): void
    {
        $pdf = $this->generator(new StubFacturXGenerator(failure: 'no SIREN'))->generate(self::multiRateInvoice());

        self::assertSame('%PDF original', $pdf->content());
        self::assertSame([], $this->events);
        self::assertCount(1, $this->logs);
        self::assertStringContainsString('no SIREN', $this->logs[0]);
    }

    public function testAFailingListenerDoesNotPreventTheInvoice(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(FacturXInvoiceGenerated::NAME, static function (): void {
            throw new \RuntimeException('The PA is down');
        });

        $pdf = $this->generator(new StubFacturXGenerator(), $dispatcher)->generate(self::multiRateInvoice());

        self::assertSame('facturx(<xml/>)(%PDF original)', $pdf->content());
        self::assertCount(1, $this->logs);
        self::assertStringContainsString('The PA is down', $this->logs[0]);
    }

    private function generator(FacturXGeneratorInterface $facturXGenerator, ?EventDispatcher $dispatcher = null): FacturXInvoicePdfFileGenerator
    {
        $decorated = new class() implements InvoicePdfFileGeneratorInterface {
            public function generate(InvoiceInterface $invoice): InvoicePdf
            {
                $pdf = new InvoicePdf('2026_10_000001.pdf', '%PDF original');
                $pdf->setFullPath('/var/invoices/2026_10_000001.pdf');

                return $pdf;
            }
        };

        $dispatcher ??= new EventDispatcher();
        $dispatcher->addListener(FacturXInvoiceGenerated::NAME, function (FacturXInvoiceGenerated $event): void {
            $this->events[] = $event;
        });

        $logs = &$this->logs;
        $logger = new class($logs) extends AbstractLogger {
            /** @param list<string> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = strtr((string) $message, ['{number}' => (string) ($context['number'] ?? ''), '{message}' => (string) ($context['message'] ?? '')]);
            }
        };

        return new FacturXInvoicePdfFileGenerator($decorated, $facturXGenerator, $dispatcher, $logger);
    }
}

final class StubFacturXGenerator implements FacturXGeneratorInterface
{
    public function __construct(private readonly bool $enabled = true, private readonly ?string $failure = null)
    {
    }

    public function isEnabledFor(InvoiceInterface $invoice): bool
    {
        return $this->enabled;
    }

    public function buildDocument(InvoiceInterface $invoice): FacturXInvoice
    {
        throw new \LogicException('Not used.');
    }

    public function generateXml(InvoiceInterface $invoice): string
    {
        if (null !== $this->failure) {
            throw FacturXException::missingSellerData($this->failure);
        }

        return '<xml/>';
    }

    public function embedIntoPdf(string $pdfContent, string $xml): string
    {
        return sprintf('facturx(%s)(%s)', $xml, $pdfContent);
    }
}
