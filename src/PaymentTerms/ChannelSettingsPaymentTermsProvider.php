<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\PaymentTerms;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * Default terms: the channel settings. Due date = issue date + payment due days when an amount
 * is due (none for a paid invoice), terms text of the channel.
 */
final class ChannelSettingsPaymentTermsProvider implements InvoicePaymentTermsProviderInterface
{
    public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): InvoicePaymentTerms
    {
        $dueDate = $duePayableAmount > 0
            ? \DateTimeImmutable::createFromInterface($invoice->issuedAt())->modify(sprintf('+%d days', max(0, $settings->getPaymentDueDays())))
            : null;

        return new InvoicePaymentTerms($dueDate, $settings->getPaymentTerms());
    }
}
