<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Optional B2B identifiers of a customer (buyer of the Factur-X invoices), kept in the plugin
 * table so that the Sylius customer entity is not modified.
 */
interface CustomerIdentifiersInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    /** BT-44, used when the billing address has no company. */
    public function getCompanyName(): ?string;

    public function setCompanyName(?string $companyName): void;

    /** BT-47 (scheme 0002). */
    public function getSiren(): ?string;

    public function setSiren(?string $siren): void;

    /** BT-48 */
    public function getVatNumber(): ?string;

    public function setVatNumber(?string $vatNumber): void;

    /** BT-49 */
    public function getElectronicAddress(): ?string;

    public function setElectronicAddress(?string $electronicAddress): void;

    /** BT-49-1 (EAS code list) */
    public function getElectronicAddressScheme(): ?string;

    public function setElectronicAddressScheme(?string $electronicAddressScheme): void;

    public function isEmpty(): bool;
}
