<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Exception;

/** A Factur-X document cannot be generated for an invoice. */
final class FacturXException extends \RuntimeException
{
    public static function notEnabledForChannel(string $channelCode): self
    {
        return new self(sprintf('Factur-X is not enabled for the channel "%s": configure it in the channel Factur-X settings.', $channelCode));
    }

    public static function missingSellerData(string $what): self
    {
        return new self(sprintf('The seller data of the invoice is incomplete: %s.', $what));
    }

    public static function amountsDoNotReconcile(string $invoiceNumber, int $expected, int $actual): self
    {
        return new self(sprintf(
            'The lines of the invoice "%s" do not add up to its total (%d expected, %d computed): the invoice has amounts that are not carried by its lines.',
            $invoiceNumber,
            $expected,
            $actual,
        ));
    }
}
