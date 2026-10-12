<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\PaymentTerms;

use PHPUnit\Framework\TestCase;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tests\Tmonier\SyliusFacturXPlugin\Unit\InvoiceBuilderTrait;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\ChannelSettingsPaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\CompositeInvoicePaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTerms;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;

final class InvoicePaymentTermsProviderTest extends TestCase
{
    use InvoiceBuilderTrait;

    public function testTermsAreTrimmedAndEmptyTermsAreDetected(): void
    {
        $terms = new InvoicePaymentTerms(new \DateTime('2026-12-31 10:00:00'), "  Net 60 days \n");

        self::assertSame('2026-12-31', $terms->dueDate?->format('Y-m-d'));
        self::assertSame('Net 60 days', $terms->description);
        self::assertFalse($terms->isEmpty());
        self::assertTrue((new InvoicePaymentTerms(null, '   '))->isEmpty());
        self::assertNull((new InvoicePaymentTerms(null, '   '))->description);
    }

    public function testTheChannelSettingsGiveTheDueDaysAndTheTermsText(): void
    {
        $provider = new ChannelSettingsPaymentTermsProvider();
        $invoice = self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING);

        $due = $provider->provide($invoice, self::settings(), 4583);
        $paid = $provider->provide($invoice, self::settings(), 0);

        self::assertSame('2026-11-09', $due->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 30 jours', $due->description);
        self::assertNull($paid->dueDate);
        self::assertSame('Paiement à 30 jours', $paid->description);
    }

    public function testTheFirstProviderReturningTermsWins(): void
    {
        $composite = new CompositeInvoicePaymentTermsProvider([
            self::provider(null),
            self::provider(new InvoicePaymentTerms(new \DateTimeImmutable('2026-12-31'), 'Net 60 days')),
            self::provider(new InvoicePaymentTerms(new \DateTimeImmutable('2027-01-31'), 'Net 90 days')),
        ]);

        $terms = $composite->provide(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), self::settings(), 4583);

        self::assertSame('2026-12-31', $terms->dueDate?->format('Y-m-d'));
        self::assertSame('Net 60 days', $terms->description);
    }

    public function testTheChannelSettingsApplyWhenNoProviderHandlesTheInvoice(): void
    {
        $composite = new CompositeInvoicePaymentTermsProvider([self::provider(null)]);

        $terms = $composite->provide(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), self::settings(), 4583);

        self::assertSame('2026-11-09', $terms->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 30 jours', $terms->description);
    }

    public function testEmptyTermsAreIgnoredWhenAnAmountIsDueBR_CO_25(): void
    {
        $composite = new CompositeInvoicePaymentTermsProvider([self::provider(new InvoicePaymentTerms())]);
        $invoice = self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING);

        $due = $composite->provide($invoice, self::settings(), 4583);
        $paid = $composite->provide($invoice, self::settings(), 0);

        self::assertFalse($due->isEmpty());
        self::assertSame('2026-11-09', $due->dueDate?->format('Y-m-d'));
        self::assertTrue($paid->isEmpty());
    }

    public function testAPaidInvoiceNeverGetsADueDate(): void
    {
        $composite = new CompositeInvoicePaymentTermsProvider([self::provider(new InvoicePaymentTerms(new \DateTimeImmutable('2026-12-31'), 'Paid on account'))]);

        $terms = $composite->provide(self::multiRateInvoice(), self::settings(), 0);

        self::assertNull($terms->dueDate);
        self::assertSame('Paid on account', $terms->description);
    }

    private static function provider(?InvoicePaymentTerms $terms): InvoicePaymentTermsProviderInterface
    {
        return new class($terms) implements InvoicePaymentTermsProviderInterface {
            public function __construct(private readonly ?InvoicePaymentTerms $terms)
            {
            }

            public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): ?InvoicePaymentTerms
            {
                return $this->terms;
            }
        };
    }
}
