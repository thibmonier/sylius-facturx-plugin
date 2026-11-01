<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tmonier\SyliusFacturXPlugin\Model\Address;

/** @internal */
final readonly class DeliveryMapper
{
    /**
     * The delivery address (BG-13) is only given when it differs from the billing address, as
     * the French reform requires.
     *
     * @return array{?string, ?Address}
     */
    public function mapDelivery(InvoiceInterface $invoice): array
    {
        $shippingAddress = $invoice->order()->getShippingAddress();
        if (!$shippingAddress instanceof AddressInterface || null === StringHelper::nonEmpty($shippingAddress->getCountryCode())) {
            return [null, null];
        }

        $billingData = $invoice->billingData();
        $sameAddress = StringHelper::normalize($shippingAddress->getStreet()) === StringHelper::normalize($billingData->street()) &&
            StringHelper::normalize($shippingAddress->getPostcode()) === StringHelper::normalize($billingData->postcode()) &&
            StringHelper::normalize($shippingAddress->getCity()) === StringHelper::normalize($billingData->city()) &&
            $shippingAddress->getCountryCode() === $billingData->countryCode();
        if ($sameAddress) {
            return [null, null];
        }

        $name = StringHelper::nonEmpty($shippingAddress->getCompany()) ?? StringHelper::nonEmpty(trim(sprintf('%s %s', $shippingAddress->getFirstName() ?? '', $shippingAddress->getLastName() ?? '')));

        return [$name, new Address(
            countryCode: (string) $shippingAddress->getCountryCode(),
            line1: StringHelper::nonEmpty($shippingAddress->getStreet()),
            postcode: StringHelper::nonEmpty($shippingAddress->getPostcode()),
            city: StringHelper::nonEmpty($shippingAddress->getCity()),
            subdivision: StringHelper::nonEmpty($shippingAddress->getProvinceName()) ?? StringHelper::nonEmpty($shippingAddress->getProvinceCode()),
        )];
    }

    /**
     * Actual delivery date (BT-72): the last shipping date of the order, nothing when no shipment
     * is shipped yet.
     */
    public function deliveryDate(InvoiceInterface $invoice): ?\DateTimeImmutable
    {
        $shippedAt = null;
        foreach ($invoice->order()->getShipments() as $shipment) {
            $date = $shipment->getShippedAt();
            if (null !== $date && (null === $shippedAt || $date > $shippedAt)) {
                $shippedAt = $date;
            }
        }

        return null === $shippedAt ? null : \DateTimeImmutable::createFromInterface($shippedAt);
    }
}
