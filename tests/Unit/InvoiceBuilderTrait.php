<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit;

use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Order;
use Sylius\InvoicingPlugin\Entity\BillingData;
use Sylius\InvoicingPlugin\Entity\Invoice;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceShopBillingData;
use Sylius\InvoicingPlugin\Entity\LineItem;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettings;

/**
 * Builds invoices of the Invoicing Plugin in memory, with line items shaped like the ones its
 * converters create (net amounts and per-unit rounded taxes from Sylius).
 */
trait InvoiceBuilderTrait
{
    /**
     * Line item of the Invoicing Plugin: the subtotal is the net amount of the line after
     * discounts, the total is subtotal + taxTotal.
     */
    private static function lineItem(
        string $name,
        int $quantity,
        int $unitNetPrice,
        int $discountedUnitNetPrice,
        int $taxTotal,
        ?string $taxRate,
        ?int $subtotal = null,
        ?string $variantCode = null,
    ): LineItem {
        $subtotal ??= $quantity * $discountedUnitNetPrice;

        return new LineItem($name, $quantity, $unitNetPrice, $discountedUnitNetPrice, $subtotal, $taxTotal, $subtotal + $taxTotal, null, $variantCode, $taxRate);
    }

    /** @param list<LineItem> $lineItems */
    private static function invoice(
        array $lineItems,
        string $paymentState = InvoiceInterface::PAYMENT_STATE_COMPLETED,
        ?int $total = null,
        ?BillingData $billingData = null,
        ?Address $shippingAddress = null,
    ): Invoice {
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setName('Web store');

        $order = new Order();
        $order->setNumber('000000042');
        $order->setChannel($channel);
        $order->setShippingAddress($shippingAddress ?? self::address('12 rue de la Paix', '75002', 'Paris', 'FR'));

        $shopBillingData = new InvoiceShopBillingData();
        $shopBillingData->setCompany('Boutique Exemple SAS');
        $shopBillingData->setTaxId('FR40303265045');
        $shopBillingData->setStreet('1 avenue des Champs-Élysées');
        $shopBillingData->setPostcode('75008');
        $shopBillingData->setCity('Paris');
        $shopBillingData->setCountryCode('FR');

        $total ??= array_sum(array_map(static fn (LineItem $lineItem): int => $lineItem->total(), $lineItems));

        return new Invoice(
            'b6f2a7a4-5d1c-4c4e-8a0b-2f6f0e2a9c11',
            '2026/10/000001',
            $order,
            new \DateTimeImmutable('2026-10-10 14:30:00'),
            $billingData ?? new BillingData('Jeanne', 'Martin', 'FR', '12 rue de la Paix', 'Paris', '75002', null, null, 'Martin Conseil SARL'),
            'EUR',
            'fr_FR',
            $total,
            new ArrayCollection($lineItems),
            new ArrayCollection(),
            $channel,
            $paymentState,
            $shopBillingData,
        );
    }

    private static function address(string $street, string $postcode, string $city, string $countryCode): Address
    {
        $address = new Address();
        $address->setFirstName('Jeanne');
        $address->setLastName('Martin');
        $address->setStreet($street);
        $address->setPostcode($postcode);
        $address->setCity($city);
        $address->setCountryCode($countryCode);

        return $address;
    }

    private static function settings(): ChannelSettings
    {
        $settings = new ChannelSettings();
        $settings->setSiren('303265045');
        $settings->setSiret('30326504500011');
        $settings->setLegalForm('SAS au capital de 10 000 €');
        $settings->setTradeRegister('RCS Paris 303 265 045');
        $settings->setPaymentDueDays(30);
        $settings->setPaymentTerms('Paiement à 30 jours');
        $settings->setIban('FR7630006000011234567890189');
        $settings->setBic('AGRIFRPP');
        $settings->setLatePaymentPenaltiesNote('Tout retard de paiement engendre une pénalité calculée sur la base de trois fois le taux d\'intérêt légal.');
        $settings->setRecoveryIndemnityNote('Indemnité forfaitaire pour frais de recouvrement : 40 € (art. L441-10 du Code de commerce).');
        $settings->setEarlyPaymentDiscountNote('Pas d\'escompte pour paiement anticipé.');

        return $settings;
    }

    /** Three lines at 20 % (two T-shirts, shipping) and one at 5.5 % (a book). */
    private static function multiRateInvoice(string $paymentState = InvoiceInterface::PAYMENT_STATE_COMPLETED): Invoice
    {
        return self::invoice([
            self::lineItem('T-shirt', 2, 1000, 1000, 400, '20%', variantCode: 'TSHIRT-M'),
            self::lineItem('Book', 1, 1500, 1500, 83, '5.5%', variantCode: 'BOOK'),
            self::lineItem('UPS', 1, 500, 500, 100, '20%'),
        ], $paymentState);
    }
}
