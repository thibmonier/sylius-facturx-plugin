<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

class ChannelSettingsRepository extends EntityRepository implements ChannelSettingsRepositoryInterface
{
    public function findOneByChannel(ChannelInterface $channel): ?ChannelSettingsInterface
    {
        /** @var ChannelSettingsInterface|null $settings */
        $settings = $this->findOneBy(['channel' => $channel]);

        return $settings;
    }
}
