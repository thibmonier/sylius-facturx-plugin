<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusFacturXPlugin\Model\InvoiceLine;

final class InvoiceLineTest extends TestCase
{
    public function testItCanBeCreatedWithADocumentReference(): void
    {
        $line = new InvoiceLine(
            id: '1',
            name: 'Product',
            quantity: 1,
            netUnitPrice: 1000,
            netAmount: 1000,
            vatCategory: 'S',
            vatRate: '20',
            vatAmount: 200,
            documentReference: 'https://example.com/passport/ABC123',
        );

        self::assertSame('https://example.com/passport/ABC123', $line->documentReference);
    }

    public function testDocumentReferenceDefaultsToNull(): void
    {
        $line = new InvoiceLine(
            id: '1',
            name: 'Product',
            quantity: 1,
            netUnitPrice: 1000,
            netAmount: 1000,
            vatCategory: 'S',
            vatRate: '20',
            vatAmount: 200,
        );

        self::assertNull($line->documentReference);
    }

    public function testUnitDiscountCalculation(): void
    {
        $lineWithDiscount = new InvoiceLine(
            id: '1',
            name: 'Product',
            quantity: 1,
            netUnitPrice: 900,
            netAmount: 900,
            vatCategory: 'S',
            vatRate: '20',
            vatAmount: 180,
            grossUnitPrice: 1000,
        );

        self::assertSame(100, $lineWithDiscount->unitDiscount());

        $lineWithoutDiscount = new InvoiceLine(
            id: '1',
            name: 'Product',
            quantity: 1,
            netUnitPrice: 1000,
            netAmount: 1000,
            vatCategory: 'S',
            vatRate: '20',
            vatAmount: 200,
        );

        self::assertSame(0, $lineWithoutDiscount->unitDiscount());
    }
}
