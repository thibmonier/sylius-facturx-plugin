<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\PaymentTerms;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * Asks the tagged providers by priority and falls back to the channel settings, so that an
 * invoice with an amount due always gets a due date or payment terms (BR-CO-25).
 */
final class CompositeInvoicePaymentTermsProvider implements InvoicePaymentTermsProviderInterface
{
    /** @param iterable<InvoicePaymentTermsProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
        private readonly InvoicePaymentTermsProviderInterface $fallback = new ChannelSettingsPaymentTermsProvider(),
    ) {
    }

    public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): InvoicePaymentTerms
    {
        foreach ($this->providers as $provider) {
            $terms = $provider->provide($invoice, $settings, $duePayableAmount);
            if (null !== $terms && ($duePayableAmount <= 0 || !$terms->isEmpty())) {
                return $terms;
            }
        }

        return $this->fallback->provide($invoice, $settings, $duePayableAmount) ?? new InvoicePaymentTerms();
    }
}
