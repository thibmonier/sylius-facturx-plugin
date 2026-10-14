<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Generator;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapperInterface;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;
use Tmonier\SyliusFacturXPlugin\Pdf\FacturXPdfEmbedderInterface;
use Tmonier\SyliusFacturXPlugin\Repository\ChannelSettingsRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Repository\CustomerIdentifiersRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Xml\CiiXmlWriterInterface;

final readonly class FacturXGenerator implements FacturXGeneratorInterface
{
    public function __construct(
        private ChannelSettingsRepositoryInterface $channelSettingsRepository,
        private CustomerIdentifiersRepositoryInterface $customerIdentifiersRepository,
        private FacturXInvoiceMapperInterface $mapper,
        private CiiXmlWriterInterface $xmlWriter,
        private FacturXPdfEmbedderInterface $pdfEmbedder,
        private bool $enabled = true,
    ) {
    }

    public function isEnabledFor(InvoiceInterface $invoice): bool
    {
        return $this->findSettings($invoice) instanceof \Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
    }

    public function buildDocument(InvoiceInterface $invoice): FacturXInvoice
    {
        $settings = $this->findSettings($invoice);
        if (!$settings instanceof \Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface) {
            throw FacturXException::notEnabledForChannel((string) $invoice->channel()->getCode());
        }

        $customer = $invoice->order()->getCustomer();
        $identifiers = $customer instanceof CustomerInterface ? $this->customerIdentifiersRepository->findOneByCustomer($customer) : null;

        return $this->mapper->map($invoice, $settings, $identifiers);
    }

    public function generateXml(InvoiceInterface $invoice): string
    {
        return $this->xmlWriter->write($this->buildDocument($invoice));
    }

    public function embedIntoPdf(string $pdfContent, string $xml): string
    {
        try {
            return $this->pdfEmbedder->embed($pdfContent, $xml);
        } catch (\Throwable $exception) {
            throw new FacturXException(sprintf('The XML cannot be embedded in the PDF: %s', $exception->getMessage()), 0, $exception);
        }
    }

    private function findSettings(InvoiceInterface $invoice): ?ChannelSettingsInterface
    {
        if (!$this->enabled) {
            return null;
        }

        $settings = $this->channelSettingsRepository->findOneByChannel($invoice->channel());

        return $settings instanceof \Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface && $settings->isEnabled() ? $settings : null;
    }
}
