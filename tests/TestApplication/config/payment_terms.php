<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tests\Tmonier\SyliusFacturXPlugin\PaymentTerms\TestPaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\DependencyInjection\TmonierSyliusFacturXExtension;

// Per-order payment terms driven by the tests (InvoicePaymentTermsTest).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_facturx.test.payment_terms_provider', TestPaymentTermsProvider::class)
        ->public()
        ->tag(TmonierSyliusFacturXExtension::TAG_INVOICE_PAYMENT_TERMS_PROVIDER, ['priority' => 10])
    ;
};
