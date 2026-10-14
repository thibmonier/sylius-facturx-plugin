<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Entity;

use Sylius\Component\Core\Model\ChannelInterface;

class ChannelSettings implements ChannelSettingsInterface
{
    protected ?int $id = null;

    protected ?ChannelInterface $channel = null;

    protected bool $enabled = true;

    protected ?string $legalName = null;

    protected ?string $siren = null;

    protected ?string $siret = null;

    protected ?string $vatNumber = null;

    protected ?string $legalForm = null;

    protected ?string $tradeRegister = null;

    protected ?string $electronicAddress = null;

    protected ?string $electronicAddressScheme = null;

    protected ?string $paymentTerms = null;

    protected int $paymentDueDays = 0;

    protected ?string $iban = null;

    protected ?string $bic = null;

    protected string $operationCategory = self::OPERATION_GOODS;

    protected bool $vatOnDebits = false;

    protected string $zeroVatCategory = 'E';

    protected ?string $vatExemptionReason = null;

    protected ?string $vatExemptionReasonCode = null;

    protected ?string $latePaymentPenaltiesNote = null;

    protected ?string $recoveryIndemnityNote = null;

    protected ?string $earlyPaymentDiscountNote = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getPaymentDueDays(): int
    {
        return $this->paymentDueDays;
    }

    public function setPaymentDueDays(int $paymentDueDays): void
    {
        $this->paymentDueDays = $paymentDueDays;
    }

    public function getOperationCategory(): string
    {
        return $this->operationCategory;
    }

    public function setOperationCategory(string $operationCategory): void
    {
        $this->operationCategory = $operationCategory;
    }

    public function isVatOnDebits(): bool
    {
        return $this->vatOnDebits;
    }

    public function setVatOnDebits(bool $vatOnDebits): void
    {
        $this->vatOnDebits = $vatOnDebits;
    }

    public function getZeroVatCategory(): string
    {
        return $this->zeroVatCategory;
    }

    public function setZeroVatCategory(string $zeroVatCategory): void
    {
        $this->zeroVatCategory = $zeroVatCategory;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function setLegalName(?string $legalName): void
    {
        $this->legalName = $this->normalize($legalName);
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): void
    {
        $this->siren = $this->normalizeIdentifier($siren);
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): void
    {
        $this->siret = $this->normalizeIdentifier($siret);
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $this->normalizeIdentifier($vatNumber);
    }

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): void
    {
        $this->legalForm = $this->normalize($legalForm);
    }

    public function getTradeRegister(): ?string
    {
        return $this->tradeRegister;
    }

    public function setTradeRegister(?string $tradeRegister): void
    {
        $this->tradeRegister = $this->normalize($tradeRegister);
    }

    public function getElectronicAddress(): ?string
    {
        return $this->electronicAddress;
    }

    public function setElectronicAddress(?string $electronicAddress): void
    {
        $this->electronicAddress = $this->normalize($electronicAddress);
    }

    public function getElectronicAddressScheme(): ?string
    {
        return $this->electronicAddressScheme;
    }

    public function setElectronicAddressScheme(?string $electronicAddressScheme): void
    {
        $this->electronicAddressScheme = $this->normalizeIdentifier($electronicAddressScheme);
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): void
    {
        $this->paymentTerms = $this->normalize($paymentTerms);
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): void
    {
        $this->iban = $this->normalizeIdentifier($iban);
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): void
    {
        $this->bic = $this->normalizeIdentifier($bic);
    }

    public function getVatExemptionReason(): ?string
    {
        return $this->vatExemptionReason;
    }

    public function setVatExemptionReason(?string $vatExemptionReason): void
    {
        $this->vatExemptionReason = $this->normalize($vatExemptionReason);
    }

    public function getVatExemptionReasonCode(): ?string
    {
        return $this->vatExemptionReasonCode;
    }

    public function setVatExemptionReasonCode(?string $vatExemptionReasonCode): void
    {
        $this->vatExemptionReasonCode = $this->normalizeIdentifier($vatExemptionReasonCode);
    }

    public function getLatePaymentPenaltiesNote(): ?string
    {
        return $this->latePaymentPenaltiesNote;
    }

    public function setLatePaymentPenaltiesNote(?string $latePaymentPenaltiesNote): void
    {
        $this->latePaymentPenaltiesNote = $this->normalize($latePaymentPenaltiesNote);
    }

    public function getRecoveryIndemnityNote(): ?string
    {
        return $this->recoveryIndemnityNote;
    }

    public function setRecoveryIndemnityNote(?string $recoveryIndemnityNote): void
    {
        $this->recoveryIndemnityNote = $this->normalize($recoveryIndemnityNote);
    }

    public function getEarlyPaymentDiscountNote(): ?string
    {
        return $this->earlyPaymentDiscountNote;
    }

    public function setEarlyPaymentDiscountNote(?string $earlyPaymentDiscountNote): void
    {
        $this->earlyPaymentDiscountNote = $this->normalize($earlyPaymentDiscountNote);
    }

    /** Identifiers are stored without spaces and upper-cased: "fr 12 345 678 901" becomes "FR12345678901". */
    private function normalizeIdentifier(?string $value): ?string
    {
        return $this->normalize(null === $value ? null : strtoupper((string) preg_replace('/\s+/', '', $value)));
    }

    private function normalize(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
