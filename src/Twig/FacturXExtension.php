<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Twig;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;
use Tmonier\SyliusFacturXPlugin\Repository\ChannelSettingsRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Repository\CustomerIdentifiersRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FacturXExtension extends AbstractExtension
{
    public function __construct(
        private readonly FacturXGeneratorInterface $facturXGenerator,
        private readonly ChannelSettingsRepositoryInterface $channelSettingsRepository,
        private readonly CustomerIdentifiersRepositoryInterface $customerIdentifiersRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_facturx_enabled_for_invoice', $this->isEnabledForInvoice(...)),
            new TwigFunction('tmonier_facturx_channel_settings', $this->getChannelSettings(...)),
            new TwigFunction('tmonier_facturx_customer_identifiers', $this->getCustomerIdentifiers(...)),
        ];
    }

    public function isEnabledForInvoice(mixed $invoice): bool
    {
        return $invoice instanceof InvoiceInterface && $this->facturXGenerator->isEnabledFor($invoice);
    }

    public function getChannelSettings(mixed $channel): ?ChannelSettingsInterface
    {
        return $channel instanceof ChannelInterface ? $this->channelSettingsRepository->findOneByChannel($channel) : null;
    }

    public function getCustomerIdentifiers(mixed $customer): ?CustomerIdentifiersInterface
    {
        return $customer instanceof CustomerInterface ? $this->customerIdentifiersRepository->findOneByCustomer($customer) : null;
    }
}
