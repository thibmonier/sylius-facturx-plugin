<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Mapper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusFacturXPlugin\Mapper\TaxRateParser;

final class TaxRateParserTest extends TestCase
{
    /** @return iterable<array{?string, ?string}> */
    public static function labels(): iterable
    {
        yield [null, null];
        yield ['20%', '20'];
        yield ['5.5%', '5.5'];
        yield ['5,5 %', '5.5'];
        yield ['2.1%', '2.1'];
        yield ['7.000000000000001%', '7'];
        yield ['19.6%', '19.6'];
        yield ['8.5%', '8.5'];
        yield ['0%', '0'];
        yield ['0.9%', '0.9'];
    }

    #[DataProvider('labels')]
    public function testItParsesTheRateLabels(?string $label, ?string $expected): void
    {
        self::assertSame($expected, TaxRateParser::parse($label));
    }

    public function testItRejectsALabelThatIsNotAPercentage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TaxRateParser::parse('VAT');
    }

    public function testZeroRates(): void
    {
        self::assertTrue(TaxRateParser::isZero(null));
        self::assertTrue(TaxRateParser::isZero('0'));
        self::assertFalse(TaxRateParser::isZero('0.9'));
    }
}
