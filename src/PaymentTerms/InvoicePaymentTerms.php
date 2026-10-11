<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\PaymentTerms;

/** Payment due date (BT-9) and payment terms (BT-20) of one invoice. */
final class InvoicePaymentTerms
{
    public readonly ?\DateTimeImmutable $dueDate;

    public readonly ?string $description;

    public function __construct(?\DateTimeInterface $dueDate = null, ?string $description = null)
    {
        $this->dueDate = null === $dueDate ? null : \DateTimeImmutable::createFromInterface($dueDate);
        $description = null === $description ? null : trim($description);
        $this->description = '' === $description ? null : $description;
    }

    /** Rule BR-CO-25: an invoice with an amount due needs a due date or payment terms. */
    public function isEmpty(): bool
    {
        return null === $this->dueDate && null === $this->description;
    }
}
