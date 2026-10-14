<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;

class CustomerIdentifiers implements CustomerIdentifiersInterface
{
    protected ?int $id = null;

    protected ?CustomerInterface $customer = null;

    protected ?string $companyName = null;

    protected ?string $siren = null;

    protected ?string $vatNumber = null;

    protected ?string $electronicAddress = null;

    protected ?string $electronicAddressScheme = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): void
    {
        $companyName = null === $companyName ? null : trim($companyName);
        $this->companyName = '' === $companyName ? null : $companyName;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): void
    {
        $this->siren = $this->normalizeIdentifier($siren);
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $this->normalizeIdentifier($vatNumber);
    }

    public function getElectronicAddress(): ?string
    {
        return $this->electronicAddress;
    }

    public function setElectronicAddress(?string $electronicAddress): void
    {
        $electronicAddress = null === $electronicAddress ? null : trim($electronicAddress);
        $this->electronicAddress = '' === $electronicAddress ? null : $electronicAddress;
    }

    public function getElectronicAddressScheme(): ?string
    {
        return $this->electronicAddressScheme;
    }

    public function setElectronicAddressScheme(?string $electronicAddressScheme): void
    {
        $this->electronicAddressScheme = $this->normalizeIdentifier($electronicAddressScheme);
    }

    public function isEmpty(): bool
    {
        return null === $this->companyName && null === $this->siren && null === $this->vatNumber && null === $this->electronicAddress;
    }

    private function normalizeIdentifier(?string $value): ?string
    {
        $value = null === $value ? null : strtoupper((string) preg_replace('/\s+/', '', $value));

        return '' === $value ? null : $value;
    }
}
