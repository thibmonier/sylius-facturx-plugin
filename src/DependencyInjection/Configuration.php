<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettings;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiers;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Repository\ChannelSettingsRepository;
use Tmonier\SyliusFacturXPlugin\Repository\CustomerIdentifiersRepository;

final class Configuration implements ConfigurationInterface
{
    public const PROFILE_EN16931 = 'en16931';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_facturx');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Embed the Factur-X XML in the invoice PDFs of the channels whose Factur-X settings are enabled.')
                    ->defaultTrue()
                ->end()
                ->enumNode('profile')
                    ->info('Factur-X profile. Only EN 16931 (COMFORT) is supported.')
                    ->values([self::PROFILE_EN16931])
                    ->defaultValue(self::PROFILE_EN16931)
                ->end()
                ->enumNode('attachment_relationship')
                    ->info('AFRelationship of the embedded factur-x.xml file ("Alternative" is accepted in France and required in Germany for EN 16931).')
                    ->values(['Data', 'Alternative', 'Source'])
                    ->defaultValue('Alternative')
                ->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($this->resourceNode('channel_settings', ChannelSettings::class, ChannelSettingsInterface::class, ChannelSettingsRepository::class))
                        ->append($this->resourceNode('customer_identifiers', CustomerIdentifiers::class, CustomerIdentifiersInterface::class, CustomerIdentifiersRepository::class))
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function resourceNode(string $name, string $model, string $interface, string $repository): ArrayNodeDefinition
    {
        $node = (new TreeBuilder($name))->getRootNode();
        \assert($node instanceof ArrayNodeDefinition);

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue($model)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue($interface)->cannotBeEmpty()->end()
                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                        ->scalarNode('repository')->defaultValue($repository)->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
