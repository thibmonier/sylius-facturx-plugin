<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * "Cadre de facturation" of the French e-invoicing reform, carried in BT-23 (business process
 * type): one letter for the category of operation (B goods, S services, M mixed) and one digit
 * for the case (1 invoice to be paid, 2 invoice already paid).
 *
 * @see SPEC.md, section "French mandatory mentions"
 */
final class FrenchBusinessProcess
{
    private const PREFIXES = [
        ChannelSettingsInterface::OPERATION_GOODS => 'B',
        ChannelSettingsInterface::OPERATION_SERVICES => 'S',
        ChannelSettingsInterface::OPERATION_MIXED => 'M',
    ];

    public static function code(string $operationCategory, bool $alreadyPaid): string
    {
        $prefix = self::PREFIXES[$operationCategory] ?? throw new \InvalidArgumentException(sprintf('Unknown category of operation "%s".', $operationCategory));

        return $prefix . ($alreadyPaid ? '2' : '1');
    }
}
