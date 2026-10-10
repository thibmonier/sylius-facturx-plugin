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

final class FacturXGenerator implements FacturXGeneratorInterface
{
    public function __construct(
        private readonly ChannelSettingsRepositoryInterface $channelSettingsRepository,
        private readonly CustomerIdentifiersRepositoryInterface $customerIdentifiersRepository,
        private readonly FacturXInvoiceMapperInterface $mapper,
        private readonly CiiXmlWriterInterface $xmlWriter,
        private readonly FacturXPdfEmbedderInterface $pdfEmbedder,
        private readonly bool $enabled = true,
    ) {
    }

    public function isEnabledFor(InvoiceInterface $invoice): bool
    {
        return null !== $this->findSettings($invoice);
    }

    public function buildDocument(InvoiceInterface $invoice): FacturXInvoice
    {
        $settings = $this->findSettings($invoice);
        if (null === $settings) {
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

        return null !== $settings && $settings->isEnabled() ? $settings : null;
    }
}
