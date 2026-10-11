<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use PHPUnit\Framework\Assert;
use Tmonier\SyliusFacturXPlugin\Pdf\Font\FontWidthsNormalizer;

/**
 * Checks of a generated Factur-X document without Java tooling: XSD of the Factur-X EN 16931
 * profile (shipped by horstoeko/zugferd), the main EN 16931 calculation rules re-computed from
 * the XML, and the PDF/A-3 structure (associated file, XMP metadata).
 *
 * The full schematron (XSLT 2) needs Saxon: CI runs the Mustang validator on the files that the
 * functional tests write into etc/build/ (see .github/workflows/build.yaml).
 */
trait FacturXAssertionsTrait
{
    private const XSD = __DIR__ . '/../vendor/horstoeko/zugferd/src/schema/FACTUR-X_EN16931.xsd';

    private static function assertValidAgainstXsd(string $xml): void
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $document->schemaValidate(self::XSD);
        $errors = array_map(static fn (\LibXMLError $error): string => sprintf('line %d: %s', $error->line, trim($error->message)), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        Assert::assertTrue($valid, "The XML does not match the Factur-X EN 16931 XSD:\n" . implode("\n", $errors));
    }

    private static function xpath(string $xml): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        return $xpath;
    }

    private static function xmlValue(string $xml, string $expression): ?string
    {
        $nodes = self::xpath($xml)->query($expression);
        if (false === $nodes || 0 === $nodes->length) {
            return null;
        }

        return self::text($nodes->item(0));
    }

    /** @return list<string> */
    private static function xmlValues(string $xml, string $expression): array
    {
        $values = [];
        foreach (self::xpath($xml)->query($expression) ?: [] as $node) {
            $values[] = (string) self::text($node);
        }

        return $values;
    }

    /**
     * Mandatory data (BR-01 to BR-16, BR-CO-26) and calculation rules (BR-CO-10, 13, 14, 15, 16,
     * 17, BR-S-08, BR-DEC) of EN 16931, computed in cents.
     */
    private static function assertEn16931BusinessRules(string $xml): void
    {
        $settlement = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement';
        $agreement = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement';
        $summation = $settlement . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation';

        Assert::assertSame('urn:cen.eu:en16931:2017', self::xmlValue($xml, '//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'), 'BR-01');
        Assert::assertNotEmpty(self::xmlValue($xml, '//rsm:ExchangedDocument/ram:ID'), 'BR-02');
        Assert::assertMatchesRegularExpression('/^\d{8}$/', (string) self::xmlValue($xml, '//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString'), 'BR-03');
        Assert::assertContains(self::xmlValue($xml, '//rsm:ExchangedDocument/ram:TypeCode'), ['380', '381', '384', '386', '389'], 'BR-04');
        Assert::assertMatchesRegularExpression('/^[A-Z]{3}$/', (string) self::xmlValue($xml, $settlement . '/ram:InvoiceCurrencyCode'), 'BR-05');
        Assert::assertNotEmpty(self::xmlValue($xml, $agreement . '/ram:SellerTradeParty/ram:Name'), 'BR-06');
        Assert::assertNotEmpty(self::xmlValue($xml, $agreement . '/ram:BuyerTradeParty/ram:Name'), 'BR-07');
        Assert::assertNotEmpty(self::xmlValue($xml, $agreement . '/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID'), 'BR-09');
        Assert::assertNotEmpty(self::xmlValue($xml, $agreement . '/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:CountryID'), 'BR-11');
        Assert::assertTrue(
            null !== self::xmlValue($xml, $agreement . '/ram:SellerTradeParty/ram:ID') ||
            null !== self::xmlValue($xml, $agreement . '/ram:SellerTradeParty/ram:GlobalID') ||
            null !== self::xmlValue($xml, $agreement . '/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID') ||
            null !== self::xmlValue($xml, $agreement . '/ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'),
            'BR-CO-26',
        );

        $emptyElements = self::xpath($xml)->query('//*[not(*) and not(@*) and normalize-space(.) = ""]');
        Assert::assertSame(0, false === $emptyElements ? -1 : $emptyElements->length, 'PEPPOL-EN16931-R008: no empty elements');

        foreach (self::xmlValues($xml, '//ram:LineTotalAmount|//ram:BasisAmount|//ram:CalculatedAmount|//ram:TaxBasisTotalAmount|//ram:TaxTotalAmount|//ram:GrandTotalAmount|//ram:TotalPrepaidAmount|//ram:DuePayableAmount') as $amount) {
            Assert::assertMatchesRegularExpression('/^-?\d+(\.\d{1,2})?$/', $amount, 'BR-DEC: at most 2 decimals');
        }

        $lineNets = array_map(self::cents(...), self::xmlValues($xml, '//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
        Assert::assertNotEmpty($lineNets, 'BR-16: at least one line');

        $lineTotal = self::cents((string) self::xmlValue($xml, $summation . '/ram:LineTotalAmount'));
        $allowances = self::cents(self::xmlValue($xml, $summation . '/ram:AllowanceTotalAmount') ?? '0');
        $charges = self::cents(self::xmlValue($xml, $summation . '/ram:ChargeTotalAmount') ?? '0');
        $taxBasis = self::cents((string) self::xmlValue($xml, $summation . '/ram:TaxBasisTotalAmount'));
        $taxTotal = self::cents((string) self::xmlValue($xml, $summation . '/ram:TaxTotalAmount'));
        $grandTotal = self::cents((string) self::xmlValue($xml, $summation . '/ram:GrandTotalAmount'));
        $prepaid = self::cents(self::xmlValue($xml, $summation . '/ram:TotalPrepaidAmount') ?? '0');
        $rounding = self::cents(self::xmlValue($xml, $summation . '/ram:RoundingAmount') ?? '0');
        $due = self::cents((string) self::xmlValue($xml, $summation . '/ram:DuePayableAmount'));

        Assert::assertSame(array_sum($lineNets), $lineTotal, 'BR-CO-10: sum of the line net amounts');
        Assert::assertSame($lineTotal - $allowances + $charges, $taxBasis, 'BR-CO-13: total without VAT');
        Assert::assertSame($taxBasis + $taxTotal, $grandTotal, 'BR-CO-15: total with VAT');
        Assert::assertSame($grandTotal - $prepaid + $rounding, $due, 'BR-CO-16: amount due');

        // VAT breakdown: one per category and rate, matching the lines (BR-S-08 and the like).
        $xpath = self::xpath($xml);
        $linesByGroup = [];
        foreach ($xpath->query('//ram:IncludedSupplyChainTradeLineItem') ?: [] as $line) {
            \assert($line instanceof \DOMNode);
            $category = self::evaluate($xpath, 'string(ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode)', $line);
            $rate = self::evaluate($xpath, 'string(ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent)', $line);
            $key = $category . '|' . ('' === $rate ? '' : (string) (float) $rate);
            $linesByGroup[$key] = ($linesByGroup[$key] ?? 0) + self::cents(self::evaluate($xpath, 'string(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount)', $line));
        }

        $breakdownTaxes = 0;
        $groups = [];
        foreach ($xpath->query($settlement . '/ram:ApplicableTradeTax') ?: [] as $tax) {
            \assert($tax instanceof \DOMNode);
            $category = self::evaluate($xpath, 'string(ram:CategoryCode)', $tax);
            $rate = self::evaluate($xpath, 'string(ram:RateApplicablePercent)', $tax);
            $basis = self::cents(self::evaluate($xpath, 'string(ram:BasisAmount)', $tax));
            $calculated = self::cents(self::evaluate($xpath, 'string(ram:CalculatedAmount)', $tax));
            $key = $category . '|' . ('' === $rate ? '' : (string) (float) $rate);

            Assert::assertArrayNotHasKey($key, $groups, sprintf('One VAT breakdown per category and rate (%s)', $key));
            $groups[$key] = true;
            Assert::assertSame($linesByGroup[$key] ?? null, $basis, sprintf('BR-%s-08: taxable amount of %s', $category, $key));
            if ('' !== $rate) {
                $expected = (int) round($basis * (float) $rate / 100);
                Assert::assertLessThanOrEqual(100, abs($expected - $calculated), sprintf('BR-CO-17: VAT amount of %s', $key));
            }
            if (in_array($category, ['S', 'Z'], true)) {
                Assert::assertSame('', self::evaluate($xpath, 'string(ram:ExemptionReason)', $tax), sprintf('BR-%s-10', $category));
            } else {
                Assert::assertSame(0, $calculated, sprintf('BR-%s-09: no VAT for the category %s', $category, $category));
                Assert::assertTrue(
                    '' !== self::evaluate($xpath, 'string(ram:ExemptionReason)', $tax) || '' !== self::evaluate($xpath, 'string(ram:ExemptionReasonCode)', $tax),
                    sprintf('BR-%s-10: exemption reason of the category %s', $category, $category),
                );
            }
            $breakdownTaxes += $calculated;
        }

        Assert::assertSame(array_keys($linesByGroup), array_keys($groups), 'Every VAT category and rate of the lines has a breakdown');
        Assert::assertSame($breakdownTaxes, $taxTotal, 'BR-CO-14: sum of the VAT breakdowns');
    }

    /**
     * Checks the PDF/A-3 Factur-X structure and returns the embedded factur-x.xml.
     */
    private static function assertFacturXPdf(string $pdf, string $expectedRelationship = 'Alternative'): string
    {
        Assert::assertStringStartsWith('%PDF-', $pdf);
        Assert::assertMatchesRegularExpression('#/AFRelationship\s*/' . $expectedRelationship . '\b#', $pdf, 'AFRelationship of the associated file');
        Assert::assertMatchesRegularExpression('#/Type\s*/Catalog.*?/AF\s*\[?\s*\d+ 0 R#s', $pdf, 'Associated file listed in the catalog (/AF)');
        Assert::assertMatchesRegularExpression('#/Subtype\s*/text\#2Fxml#', $pdf, 'MIME type of the embedded file');
        Assert::assertMatchesRegularExpression('#/OutputIntents\s*\[#', $pdf, 'Output intent');

        $xmp = self::xmpMetadata($pdf);
        Assert::assertSame('3', self::xmpValue($xmp, '//pdfaid:part'), 'PDF/A part');
        Assert::assertSame('B', self::xmpValue($xmp, '//pdfaid:conformance'), 'PDF/A conformance');
        Assert::assertSame('INVOICE', self::xmpValue($xmp, '//fx:DocumentType'), 'fx:DocumentType');
        Assert::assertSame('factur-x.xml', self::xmpValue($xmp, '//fx:DocumentFileName'), 'fx:DocumentFileName');
        Assert::assertSame('EN 16931', self::xmpValue($xmp, '//fx:ConformanceLevel'), 'fx:ConformanceLevel');
        Assert::assertSame('1.0', self::xmpValue($xmp, '//fx:Version'), 'fx:Version');
        Assert::assertContains('urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#', self::xmpValues($xmp, '//pdfaSchema:namespaceURI'), 'PDF/A extension schema of Factur-X');

        Assert::assertSame($pdf, (new FontWidthsNormalizer())->normalize($pdf), 'PDF/A 6.2.11.5: the glyph widths match the embedded font programs');

        $xml = ZugferdDocumentPdfReader::getXmlFromContent($pdf);
        Assert::assertStringContainsString('urn:cen.eu:en16931:2017', $xml);

        return $xml;
    }

    private static function xmpMetadata(string $pdf): \DOMXPath
    {
        Assert::assertSame(1, preg_match('#<x:xmpmeta.*?</x:xmpmeta>#s', $pdf, $matches), 'Uncompressed XMP metadata stream');

        $document = new \DOMDocument();
        Assert::assertTrue($document->loadXML($matches[0] ?? ''), 'Well-formed XMP');
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('pdfaid', 'http://www.aiim.org/pdfa/ns/id/');
        $xpath->registerNamespace('fx', 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#');
        $xpath->registerNamespace('pdfaSchema', 'http://www.aiim.org/pdfa/ns/schema#');

        return $xpath;
    }

    private static function xmpValue(\DOMXPath $xmp, string $expression): ?string
    {
        return self::xmpValues($xmp, $expression . '|' . str_replace('//', '//@', $expression))[0] ?? null;
    }

    /** @return list<string> */
    private static function xmpValues(\DOMXPath $xmp, string $expression): array
    {
        $values = [];
        foreach ($xmp->query($expression) ?: [] as $node) {
            $values[] = (string) self::text($node);
        }

        return $values;
    }

    private static function text(\DOMNode|\DOMNameSpaceNode|null $node): ?string
    {
        return $node instanceof \DOMNode ? trim($node->textContent) : null;
    }

    private static function evaluate(\DOMXPath $xpath, string $expression, \DOMNode $context): string
    {
        $value = $xpath->evaluate($expression, $context);
        Assert::assertIsString($value);

        return trim($value);
    }

    private static function cents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
