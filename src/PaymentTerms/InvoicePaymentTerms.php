<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\PaymentTerms;

/** Payment due date (BT-9) and payment terms (BT-20) of one invoice. */
final readonly class InvoicePaymentTerms
{
    public ?\DateTimeImmutable $dueDate;

    public ?string $description;

    public function __construct(?\DateTimeInterface $dueDate = null, ?string $description = null)
    {
        $this->dueDate = $dueDate instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($dueDate) : null;
        $description = null === $description ? null : trim($description);
        $this->description = '' === $description ? null : $description;
    }

    /** Rule BR-CO-25: an invoice with an amount due needs a due date or payment terms. */
    public function isEmpty(): bool
    {
        return !$this->dueDate instanceof \DateTimeImmutable && null === $this->description;
    }
}
