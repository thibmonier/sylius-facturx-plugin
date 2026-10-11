<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\PaymentTerms;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTerms;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;

/** Per-order terms set by the tests (stands for a trade-credit plugin); inert otherwise. */
final class TestPaymentTermsProvider implements InvoicePaymentTermsProviderInterface
{
    /** @var array<string, InvoicePaymentTerms> */
    private array $terms = [];

    public function setTerms(string $orderNumber, InvoicePaymentTerms $terms): void
    {
        $this->terms[$orderNumber] = $terms;
    }

    public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): ?InvoicePaymentTerms
    {
        return $this->terms[(string) $invoice->order()->getNumber()] ?? null;
    }
}
