<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\PaymentTerms;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * Asks the tagged providers by priority and falls back to the channel settings, so that an
 * invoice with an amount due always gets a due date or payment terms (BR-CO-25). Invoices with
 * nothing due never get a due date.
 */
final readonly class CompositeInvoicePaymentTermsProvider implements InvoicePaymentTermsProviderInterface
{
    /** @param iterable<InvoicePaymentTermsProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
        private InvoicePaymentTermsProviderInterface $fallback = new ChannelSettingsPaymentTermsProvider(),
    ) {
    }

    public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): InvoicePaymentTerms
    {
        foreach ($this->providers as $provider) {
            $terms = $provider->provide($invoice, $settings, $duePayableAmount);
            if (null !== $terms && ($duePayableAmount <= 0 || !$terms->isEmpty())) {
                return $this->withoutDueDateWhenNothingIsDue($terms, $duePayableAmount);
            }
        }

        return $this->withoutDueDateWhenNothingIsDue($this->fallback->provide($invoice, $settings, $duePayableAmount) ?? new InvoicePaymentTerms(), $duePayableAmount);
    }

    /** BT-9 is only written for invoices with an amount due (paid invoices keep the terms text). */
    private function withoutDueDateWhenNothingIsDue(InvoicePaymentTerms $terms, int $duePayableAmount): InvoicePaymentTerms
    {
        return $duePayableAmount <= 0 && $terms->dueDate instanceof \DateTimeImmutable ? new InvoicePaymentTerms(null, $terms->description) : $terms;
    }
}
