<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class CompanyIdentifier extends Constraint
{
    public const TYPE_SIREN = 'siren';

    public const TYPE_SIRET = 'siret';

    public const TYPE_VAT_NUMBER = 'vat_number';

    public string $type = self::TYPE_SIREN;

    public ?string $message = null;

    /** @param list<string>|null $groups */
    public function __construct(?string $type = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->type = $type ?? $this->type;
        $this->message = $message ?? $this->message;
    }

    public function getMessage(): string
    {
        return $this->message ?? sprintf('tmonier_facturx.%s.invalid', $this->type);
    }
}
