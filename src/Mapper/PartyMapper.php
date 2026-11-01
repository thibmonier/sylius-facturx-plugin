<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Sylius\InvoicingPlugin\Entity\BillingDataInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Model\Address;
use Tmonier\SyliusFacturXPlugin\Model\Party;

/** @internal */
final readonly class PartyMapper
{
    public function mapSeller(InvoiceInterface $invoice, ChannelSettingsInterface $settings): Party
    {
        $shopBillingData = $invoice->shopBillingData();

        $name = $settings->getLegalName() ?? StringHelper::nonEmpty($shopBillingData->getCompany()) ?? StringHelper::nonEmpty($invoice->channel()->getName());
        if (null === $name) {
            throw FacturXException::missingSellerData('no legal name');
        }

        $countryCode = StringHelper::nonEmpty($shopBillingData->getCountryCode());
        if (null === $countryCode) {
            throw FacturXException::missingSellerData('no country in the shop billing data of the channel');
        }

        $vatId = $settings->getVatNumber() ?? StringHelper::nonEmpty($shopBillingData->getTaxId());
        if (null === $settings->getSiren() && null === $settings->getSiret() && null === $vatId) {
            throw FacturXException::missingSellerData('no SIREN, SIRET or VAT number (rule BR-CO-26)');
        }

        $legalInformation = implode(' - ', array_filter([$settings->getLegalForm(), $settings->getTradeRegister()]));

        return new Party(
            name: $name,
            address: new Address(
                countryCode: $countryCode,
                line1: StringHelper::nonEmpty($shopBillingData->getStreet()),
                postcode: StringHelper::nonEmpty($shopBillingData->getPostcode()),
                city: StringHelper::nonEmpty($shopBillingData->getCity()),
            ),
            legalRegistrationId: $settings->getSiren(),
            globalId: $settings->getSiret(),
            vatId: $vatId,
            legalInformation: '' === $legalInformation ? null : $legalInformation,
            electronicAddress: $settings->getElectronicAddress(),
            electronicAddressScheme: null === $settings->getElectronicAddress() ? null : $settings->getElectronicAddressScheme(),
        );
    }

    public function mapBuyer(BillingDataInterface $billingData, ?CustomerIdentifiersInterface $identifiers): Party
    {
        $name = StringHelper::nonEmpty($billingData->company())
            ?? $identifiers?->getCompanyName()
            ?? trim($billingData->firstName() . ' ' . $billingData->lastName());

        return new Party(
            name: $name,
            address: new Address(
                countryCode: $billingData->countryCode(),
                line1: StringHelper::nonEmpty($billingData->street()),
                postcode: StringHelper::nonEmpty($billingData->postcode()),
                city: StringHelper::nonEmpty($billingData->city()),
                subdivision: StringHelper::nonEmpty($billingData->provinceName()) ?? StringHelper::nonEmpty($billingData->provinceCode()),
            ),
            legalRegistrationId: $identifiers?->getSiren(),
            vatId: $identifiers?->getVatNumber(),
            electronicAddress: $identifiers?->getElectronicAddress(),
            electronicAddressScheme: null === $identifiers?->getElectronicAddress() ? null : $identifiers->getElectronicAddressScheme(),
        );
    }
}
