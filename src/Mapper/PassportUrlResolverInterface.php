<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Mapper;

/**
 * Resolves the digital product passport URL for a product code.
 *
 * This interface decouples the Factur-X plugin from the Digital Product Passport plugin.
 * When the DPP plugin is installed and the option is enabled, an implementation of this
 * interface is injected into the mapper to enrich invoice lines with passport URLs.
 */
interface PassportUrlResolverInterface
{
    /**
     * Resolves the digital product passport URL for the given product code.
     *
     * @param string $productCode The product variant code
     *
     * @return string|null The passport URL, or null if no passport exists for this product
     */
    public function resolve(string $productCode): ?string;
}
