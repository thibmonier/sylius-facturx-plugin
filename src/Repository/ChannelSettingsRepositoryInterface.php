<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Repository;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * @extends RepositoryInterface<ChannelSettingsInterface>
 */
interface ChannelSettingsRepositoryInterface extends RepositoryInterface
{
    public function findOneByChannel(ChannelInterface $channel): ?ChannelSettingsInterface;
}
