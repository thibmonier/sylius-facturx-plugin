<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Xml;

use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;

interface CiiXmlWriterInterface
{
    /** The factur-x.xml content. */
    public function write(FacturXInvoice $invoice): string;
}
