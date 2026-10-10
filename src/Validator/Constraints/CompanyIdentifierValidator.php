<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusFacturXPlugin\Validator\FrenchIdentifiers;

final class CompanyIdentifierValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CompanyIdentifier) {
            throw new UnexpectedTypeException($constraint, CompanyIdentifier::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $normalized = strtoupper((string) preg_replace('/\s+/', '', $value));
        $valid = match ($constraint->type) {
            CompanyIdentifier::TYPE_SIREN => FrenchIdentifiers::isValidSiren($normalized),
            CompanyIdentifier::TYPE_SIRET => FrenchIdentifiers::isValidSiret($normalized),
            CompanyIdentifier::TYPE_VAT_NUMBER => FrenchIdentifiers::isValidVatNumber($normalized),
            default => throw new \InvalidArgumentException(sprintf('Unknown identifier type "%s".', $constraint->type)),
        };

        if (!$valid) {
            $this->context->buildViolation($constraint->getMessage())->addViolation()
            ;
        }
    }
}
