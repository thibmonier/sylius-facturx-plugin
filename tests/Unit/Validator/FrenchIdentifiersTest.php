<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tmonier\SyliusFacturXPlugin\Validator\Constraints\CompanyIdentifier;
use Tmonier\SyliusFacturXPlugin\Validator\FrenchIdentifiers;

final class FrenchIdentifiersTest extends TestCase
{
    public function testSiren(): void
    {
        self::assertTrue(FrenchIdentifiers::isValidSiren('552081317'));
        self::assertTrue(FrenchIdentifiers::isValidSiren('732829320'));
        self::assertFalse(FrenchIdentifiers::isValidSiren('552081318'), 'Luhn');
        self::assertFalse(FrenchIdentifiers::isValidSiren('55208131'));
        self::assertFalse(FrenchIdentifiers::isValidSiren('55208131A'));
    }

    public function testSiret(): void
    {
        self::assertTrue(FrenchIdentifiers::isValidSiret('55208131700018'));
        self::assertFalse(FrenchIdentifiers::isValidSiret('55208131700019'), 'Luhn');
        self::assertFalse(FrenchIdentifiers::isValidSiret('55208131800018'), 'SIREN part');
        self::assertTrue(FrenchIdentifiers::isValidSiret('35600000000042'), 'La Poste: sum of the digits multiple of 5');
        self::assertFalse(FrenchIdentifiers::isValidSiret('5520813170001'));
    }

    public function testVatNumber(): void
    {
        self::assertTrue(FrenchIdentifiers::isValidVatNumber('FR03552081317'));
        self::assertTrue(FrenchIdentifiers::isValidVatNumber('FR40303265045'));
        self::assertFalse(FrenchIdentifiers::isValidVatNumber('FR04552081317'), 'French key');
        self::assertTrue(FrenchIdentifiers::isValidVatNumber('FRAB552081317'), 'Alphanumeric keys are not checked');
        self::assertFalse(FrenchIdentifiers::isValidVatNumber('FR0355208131'));
        self::assertTrue(FrenchIdentifiers::isValidVatNumber('DE123456789'));
        self::assertFalse(FrenchIdentifiers::isValidVatNumber('123456789'));
    }

    public function testTheConstraintNormalizesTheSpaces(): void
    {
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate('552 081 317', new CompanyIdentifier(CompanyIdentifier::TYPE_SIREN)));
        self::assertCount(0, $validator->validate('fr 03 552081317', new CompanyIdentifier(CompanyIdentifier::TYPE_VAT_NUMBER)));
        self::assertCount(0, $validator->validate(null, new CompanyIdentifier(CompanyIdentifier::TYPE_SIRET)));

        $violations = $validator->validate('552081318', new CompanyIdentifier(CompanyIdentifier::TYPE_SIREN));
        self::assertCount(1, $violations);
        self::assertSame('tmonier_facturx.siren.invalid', $violations->get(0)->getMessage());
    }
}
