<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;

interface FacturXInvoiceMapperInterface
{
    /** @throws FacturXException */
    public function map(
        InvoiceInterface $invoice,
        ChannelSettingsInterface $settings,
        ?CustomerIdentifiersInterface $buyerIdentifiers = null,
    ): FacturXInvoice;
}
