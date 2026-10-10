<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Entity;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Seller legal data of a channel used in the Factur-X invoices (the plugin does not modify the
 * Sylius channel entity).
 */
interface ChannelSettingsInterface extends ResourceInterface
{
    public const OPERATION_GOODS = 'goods';

    public const OPERATION_SERVICES = 'services';

    public const OPERATION_MIXED = 'mixed';

    public const OPERATION_CATEGORIES = [self::OPERATION_GOODS, self::OPERATION_SERVICES, self::OPERATION_MIXED];

    /** VAT categories (UNTDID 5305) usable for invoice lines without VAT. */
    public const ZERO_VAT_CATEGORIES = ['E', 'Z', 'O', 'AE', 'K', 'G'];

    public function getId(): ?int;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): void;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    /** BT-27, defaults to the company of the channel shop billing data. */
    public function getLegalName(): ?string;

    public function setLegalName(?string $legalName): void;

    /** BT-30 (scheme 0002). */
    public function getSiren(): ?string;

    public function setSiren(?string $siren): void;

    /** BT-29 (scheme 0009). */
    public function getSiret(): ?string;

    public function setSiret(?string $siret): void;

    /** BT-31, defaults to the tax ID of the channel shop billing data. */
    public function getVatNumber(): ?string;

    public function setVatNumber(?string $vatNumber): void;

    /** Legal form and share capital, part of BT-33. */
    public function getLegalForm(): ?string;

    public function setLegalForm(?string $legalForm): void;

    /** Trade register mention (RCS / RM), part of BT-33. */
    public function getTradeRegister(): ?string;

    public function setTradeRegister(?string $tradeRegister): void;

    /** BT-34 */
    public function getElectronicAddress(): ?string;

    public function setElectronicAddress(?string $electronicAddress): void;

    /** BT-34-1 (EAS code list) */
    public function getElectronicAddressScheme(): ?string;

    public function setElectronicAddressScheme(?string $electronicAddressScheme): void;

    /** BT-20 */
    public function getPaymentTerms(): ?string;

    public function setPaymentTerms(?string $paymentTerms): void;

    /** Number of days after the issue date for the payment due date (BT-9) of unpaid invoices. */
    public function getPaymentDueDays(): int;

    public function setPaymentDueDays(int $paymentDueDays): void;

    /** BT-84 */
    public function getIban(): ?string;

    public function setIban(?string $iban): void;

    /** BT-86 */
    public function getBic(): ?string;

    public function setBic(?string $bic): void;

    /** One of the OPERATION_* constants (BT-23 French business process). */
    public function getOperationCategory(): string;

    public function setOperationCategory(string $operationCategory): void;

    /** The seller opted for paying the VAT on debits ("TVA d'après les débits"). */
    public function isVatOnDebits(): bool;

    public function setVatOnDebits(bool $vatOnDebits): void;

    /** VAT category of the lines without VAT, one of ZERO_VAT_CATEGORIES. */
    public function getZeroVatCategory(): string;

    public function setZeroVatCategory(string $zeroVatCategory): void;

    /** BT-120 */
    public function getVatExemptionReason(): ?string;

    public function setVatExemptionReason(?string $vatExemptionReason): void;

    /** BT-121 (VATEX code list) */
    public function getVatExemptionReasonCode(): ?string;

    public function setVatExemptionReasonCode(?string $vatExemptionReasonCode): void;

    /** Late payment penalties note (BT-22, subject PMD). */
    public function getLatePaymentPenaltiesNote(): ?string;

    public function setLatePaymentPenaltiesNote(?string $latePaymentPenaltiesNote): void;

    /** Fixed recovery indemnity note (BT-22, subject PMT). */
    public function getRecoveryIndemnityNote(): ?string;

    public function setRecoveryIndemnityNote(?string $recoveryIndemnityNote): void;

    /** Early payment discount note (BT-22, subject AAB). */
    public function getEarlyPaymentDiscountNote(): ?string;

    public function setEarlyPaymentDiscountNote(?string $earlyPaymentDiscountNote): void;
}
