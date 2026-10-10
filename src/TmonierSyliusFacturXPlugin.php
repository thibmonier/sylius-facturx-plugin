<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmonier\SyliusFacturXPlugin\DependencyInjection\TmonierSyliusFacturXExtension;

final class TmonierSyliusFacturXPlugin extends Bundle
{
    use SyliusPluginTrait;

    /**
     * The naming convention of Sylius plugins would give "tmonier_sylius_factur_x": the
     * configuration key is "tmonier_sylius_facturx" instead.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new TmonierSyliusFacturXExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
