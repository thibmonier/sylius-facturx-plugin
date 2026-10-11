<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusFacturXPlugin\Command\RegenerateInvoicePdfsCommand;
use Tmonier\SyliusFacturXPlugin\DependencyInjection\TmonierSyliusFacturXExtension;
use Tmonier\SyliusFacturXPlugin\Controller\Admin\FacturXController;
use Tmonier\SyliusFacturXPlugin\Form\Type\ChannelSettingsType;
use Tmonier\SyliusFacturXPlugin\Form\Type\CustomerIdentifiersType;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGenerator;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXInvoicePdfFileGenerator;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapper;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapperInterface;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\ChannelSettingsPaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\CompositeInvoicePaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;
use Tmonier\SyliusFacturXPlugin\Pdf\FacturXPdfEmbedder;
use Tmonier\SyliusFacturXPlugin\Pdf\Font\FontWidthsNormalizer;
use Tmonier\SyliusFacturXPlugin\Pdf\FacturXPdfEmbedderInterface;
use Tmonier\SyliusFacturXPlugin\Repository\ChannelSettingsRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Repository\CustomerIdentifiersRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Twig\FacturXExtension;
use Tmonier\SyliusFacturXPlugin\Validator\Constraints\CompanyIdentifierValidator;
use Tmonier\SyliusFacturXPlugin\Xml\CiiXmlWriter;
use Tmonier\SyliusFacturXPlugin\Xml\CiiXmlWriterInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(ChannelSettingsRepositoryInterface::class, 'tmonier_facturx.repository.channel_settings');
    $services->alias(CustomerIdentifiersRepositoryInterface::class, 'tmonier_facturx.repository.customer_identifiers');

    // Factur-X document
    // Due date (BT-9) and payment terms (BT-20): tagged providers by priority, then the channel settings.
    $services->set('tmonier_facturx.payment_terms_provider.channel_settings', ChannelSettingsPaymentTermsProvider::class);
    $services
        ->set('tmonier_facturx.payment_terms_provider', CompositeInvoicePaymentTermsProvider::class)
        ->args([
            tagged_iterator(TmonierSyliusFacturXExtension::TAG_INVOICE_PAYMENT_TERMS_PROVIDER),
            service('tmonier_facturx.payment_terms_provider.channel_settings'),
        ])
    ;
    $services->alias(InvoicePaymentTermsProviderInterface::class, 'tmonier_facturx.payment_terms_provider');

    $services
        ->set('tmonier_facturx.mapper.invoice', FacturXInvoiceMapper::class)
        ->args([service('tmonier_facturx.payment_terms_provider')])
    ;
    $services->alias(FacturXInvoiceMapperInterface::class, 'tmonier_facturx.mapper.invoice');

    $services->set('tmonier_facturx.xml.writer', CiiXmlWriter::class);
    $services->alias(CiiXmlWriterInterface::class, 'tmonier_facturx.xml.writer');

    $services->set('tmonier_facturx.pdf.font_widths_normalizer', FontWidthsNormalizer::class);

    $services
        ->set('tmonier_facturx.pdf.embedder', FacturXPdfEmbedder::class)
        ->args([
            param('tmonier_facturx.attachment_relationship'),
            false,
            service('tmonier_facturx.pdf.font_widths_normalizer'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'tmonier_facturx'])
    ;
    $services->alias(FacturXPdfEmbedderInterface::class, 'tmonier_facturx.pdf.embedder');

    $services
        ->set('tmonier_facturx.generator', FacturXGenerator::class)
        ->args([
            service('tmonier_facturx.repository.channel_settings'),
            service('tmonier_facturx.repository.customer_identifiers'),
            service('tmonier_facturx.mapper.invoice'),
            service('tmonier_facturx.xml.writer'),
            service('tmonier_facturx.pdf.embedder'),
            param('tmonier_facturx.enabled'),
        ])
    ;
    $services->alias(FacturXGeneratorInterface::class, 'tmonier_facturx.generator');

    // The Invoicing Plugin renders the PDF, the decorator turns it into a Factur-X PDF before
    // it is stored, so that the admin and shop downloads serve it.
    $services
        ->set('tmonier_facturx.generator.invoice_pdf_file', FacturXInvoicePdfFileGenerator::class)
        ->decorate('sylius_invoicing.generator.invoice_pdf_file')
        ->args([
            service('.inner'),
            service('tmonier_facturx.generator'),
            service('event_dispatcher'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'tmonier_facturx'])
    ;

    $services
        ->set('tmonier_facturx.command.regenerate_pdfs', RegenerateInvoicePdfsCommand::class)
        ->args([
            service('sylius_invoicing.repository.invoice'),
            service('sylius_invoicing.generator.invoice_pdf_file'),
            service('sylius_invoicing.manager.invoice_file'),
            service('tmonier_facturx.generator'),
        ])
        ->tag('console.command')
    ;

    // Back office
    $services
        ->set('tmonier_facturx.form.type.channel_settings', ChannelSettingsType::class)
        ->args([param('tmonier_facturx.model.channel_settings.class')])
        ->tag('form.type')
    ;

    $services
        ->set('tmonier_facturx.form.type.customer_identifiers', CustomerIdentifiersType::class)
        ->args([param('tmonier_facturx.model.customer_identifiers.class')])
        ->tag('form.type')
    ;

    $services
        ->set('tmonier_facturx.validator.company_identifier', CompanyIdentifierValidator::class)
        ->tag('validator.constraint_validator')
    ;

    $services
        ->set('tmonier_facturx.controller.admin', FacturXController::class)
        ->args([
            service('sylius.repository.channel'),
            service('sylius.repository.customer'),
            service('sylius_invoicing.repository.invoice'),
            service('tmonier_facturx.repository.channel_settings'),
            service('tmonier_facturx.factory.channel_settings'),
            service('tmonier_facturx.repository.customer_identifiers'),
            service('tmonier_facturx.factory.customer_identifiers'),
            service('tmonier_facturx.generator'),
            service('doctrine.orm.entity_manager'),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;

    $services
        ->set('tmonier_facturx.twig.extension', FacturXExtension::class)
        ->args([
            service('tmonier_facturx.generator'),
            service('tmonier_facturx.repository.channel_settings'),
            service('tmonier_facturx.repository.customer_identifiers'),
        ])
        ->tag('twig.extension')
    ;
};
