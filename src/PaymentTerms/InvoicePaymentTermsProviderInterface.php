<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\PaymentTerms;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * Per-invoice payment due date (BT-9) and terms (BT-20), e.g. the terms of an order paid on
 * account. Implementations are autoconfigured with the `tmonier_facturx.invoice_payment_terms_provider`
 * tag (attribute `priority`, highest first); the first one returning terms wins, the channel
 * settings (due days + terms text) apply otherwise.
 */
interface InvoicePaymentTermsProviderInterface
{
    /**
     * Null when the provider does not handle this invoice. When `$duePayableAmount` (cents) is
     * positive, terms without due date nor description are ignored (rule BR-CO-25); when it is
     * zero (paid invoice), the due date is dropped and only the description is kept.
     */
    public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): ?InvoicePaymentTerms;
}
