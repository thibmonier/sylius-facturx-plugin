<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Mapper\FrenchBusinessProcess;

final class FrenchBusinessProcessTest extends TestCase
{
    public function testTheCodeCombinesTheCategoryOfOperationAndThePaymentState(): void
    {
        self::assertSame('B1', FrenchBusinessProcess::code(ChannelSettingsInterface::OPERATION_GOODS, false));
        self::assertSame('B2', FrenchBusinessProcess::code(ChannelSettingsInterface::OPERATION_GOODS, true));
        self::assertSame('S1', FrenchBusinessProcess::code(ChannelSettingsInterface::OPERATION_SERVICES, false));
        self::assertSame('S2', FrenchBusinessProcess::code(ChannelSettingsInterface::OPERATION_SERVICES, true));
        self::assertSame('M1', FrenchBusinessProcess::code(ChannelSettingsInterface::OPERATION_MIXED, false));
        self::assertSame('M2', FrenchBusinessProcess::code(ChannelSettingsInterface::OPERATION_MIXED, true));
    }

    public function testItRejectsAnUnknownCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FrenchBusinessProcess::code('rentals', false);
    }
}
