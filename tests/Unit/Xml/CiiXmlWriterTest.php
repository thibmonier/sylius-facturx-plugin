<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Xml;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;
use Tests\Tmonier\SyliusFacturXPlugin\Unit\InvoiceBuilderTrait;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettings;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiers;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapper;
use Tmonier\SyliusFacturXPlugin\Xml\CiiXmlWriter;

final class CiiXmlWriterTest extends TestCase
{
    use FacturXAssertionsTrait;
    use InvoiceBuilderTrait;

    private const SETTLEMENT = '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement';

    private const SELLER = '//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty';

    private const BUYER = '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty';

    /** @return iterable<string, array{InvoiceInterface, ?ChannelSettings}> */
    public static function invoices(): iterable
    {
        yield 'multi-rate, paid' => [self::multiRateInvoice(), null];
        yield 'multi-rate, unpaid' => [self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), null];
        yield 'discounts and free shipping' => [self::invoice([
            self::lineItem('Mug', 3, 1000, 900, 540, '20%'),
            self::lineItem('UPS', 1, 500, 0, 0, '20%'),
        ]), null];
        yield 'per-unit rounding' => [self::invoice([self::lineItem('Pen', 3, 833, 833, 501, '20%')]), null];

        $exempt = self::settings();
        $exempt->setZeroVatCategory('E');
        $exempt->setVatExemptionReason('TVA non applicable, art. 293 B du CGI');
        $exempt->setVatExemptionReasonCode('VATEX-FR-FRANCHISE');
        yield 'VAT exempt' => [self::invoice([self::lineItem('Lesson', 2, 3000, 3000, 0, null)]), $exempt];

        $notSubject = self::settings();
        $notSubject->setZeroVatCategory('O');
        yield 'not subject to VAT' => [self::invoice([self::lineItem('Donation', 1, 2000, 2000, 0, null)]), $notSubject];

        $debits = self::settings();
        $debits->setVatOnDebits(true);
        yield 'VAT on debits, delivery address' => [self::invoice(
            [self::lineItem('Mug', 1, 1000, 1000, 200, '20%'), self::lineItem('Book', 1, 1500, 1500, 83, '5.5%')],
            InvoiceInterface::PAYMENT_STATE_PENDING,
            shippingAddress: self::address('3 place Bellecour', '69002', 'Lyon', 'FR'),
        ), $debits];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invoices')]
    public function testTheXmlIsValidAgainstTheXsdAndTheEn16931Rules(InvoiceInterface $invoice, ?ChannelSettings $settings): void
    {
        $xml = $this->write($invoice, $settings);

        self::assertValidAgainstXsd($xml);
        self::assertEn16931BusinessRules($xml);
    }

    public function testItWritesTheProfileAndTheFrenchBusinessProcess(): void
    {
        $xml = $this->write(self::multiRateInvoice());

        self::assertSame('urn:cen.eu:en16931:2017', self::xmlValue($xml, '//ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('B2', self::xmlValue($xml, '//ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('20261010', self::xmlValue($xml, '//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString[@format="102"]'));
    }

    public function testItWritesTheLegalIdentifiersWithTheirSchemes(): void
    {
        $identifiers = new CustomerIdentifiers();
        $identifiers->setSiren('552081317');
        $identifiers->setVatNumber('FR03552081317');
        $identifiers->setElectronicAddress('552081317');
        $identifiers->setElectronicAddressScheme('0225');

        $xml = $this->write(self::multiRateInvoice(), null, $identifiers);

        self::assertSame('303265045', self::xmlValue($xml, self::SELLER . '/ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
        self::assertSame('30326504500011', self::xmlValue($xml, self::SELLER . '/ram:GlobalID[@schemeID="0009"]'));
        self::assertSame('FR40303265045', self::xmlValue($xml, self::SELLER . '/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        self::assertSame('SAS au capital de 10 000 € - RCS Paris 303 265 045', self::xmlValue($xml, self::SELLER . '/ram:Description'));
        self::assertSame('552081317', self::xmlValue($xml, self::BUYER . '/ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
        self::assertSame('FR03552081317', self::xmlValue($xml, self::BUYER . '/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        self::assertSame('552081317', self::xmlValue($xml, self::BUYER . '/ram:URIUniversalCommunication/ram:URIID[@schemeID="0225"]'));
    }

    public function testItWritesTheAmountsWithTwoDecimals(): void
    {
        $xml = $this->write(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING));
        $summation = self::SETTLEMENT . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation';

        self::assertSame('40.00', self::xmlValue($xml, $summation . '/ram:LineTotalAmount'));
        self::assertSame('40.00', self::xmlValue($xml, $summation . '/ram:TaxBasisTotalAmount'));
        self::assertSame('5.83', self::xmlValue($xml, $summation . '/ram:TaxTotalAmount[@currencyID="EUR"]'));
        self::assertSame('45.83', self::xmlValue($xml, $summation . '/ram:GrandTotalAmount'));
        self::assertSame('45.83', self::xmlValue($xml, $summation . '/ram:DuePayableAmount'));
        self::assertSame(['5.00', '0.83'], self::xmlValues($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        self::assertSame(['20.00', '5.50'], self::xmlValues($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
        self::assertSame('20261109', self::xmlValue($xml, self::SETTLEMENT . '/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString'));
    }

    public function testItWritesTheItemDiscountAsAGrossPriceAllowance(): void
    {
        $xml = $this->write(self::invoice([self::lineItem('Mug', 3, 1000, 900, 540, '20%')]));
        $agreement = '//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement';

        self::assertSame('10.00', self::xmlValue($xml, $agreement . '/ram:GrossPriceProductTradePrice/ram:ChargeAmount'));
        self::assertSame('false', self::xmlValue($xml, $agreement . '/ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator'));
        self::assertSame('1.00', self::xmlValue($xml, $agreement . '/ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ActualAmount'));
        self::assertSame('9.00', self::xmlValue($xml, $agreement . '/ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        self::assertSame('27.00', self::xmlValue($xml, '//ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
    }

    public function testItWritesTheVatOnDebitsCodeInEveryBreakdown(): void
    {
        $settings = self::settings();
        $settings->setVatOnDebits(true);

        $xml = $this->write(self::multiRateInvoice(), $settings);

        self::assertSame(['5', '5'], self::xmlValues($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:DueDateTypeCode'));
        self::assertSame([], self::xmlValues($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:TaxPointDate'));
    }

    public function testItWritesTheExemptionReason(): void
    {
        $settings = self::settings();
        $settings->setZeroVatCategory('E');
        $settings->setVatExemptionReason('TVA non applicable, art. 293 B du CGI');
        $settings->setVatExemptionReasonCode('VATEX-FR-FRANCHISE');

        $xml = $this->write(self::invoice([self::lineItem('Lesson', 1, 3000, 3000, 0, null)]), $settings);

        self::assertSame('E', self::xmlValue($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame('TVA non applicable, art. 293 B du CGI', self::xmlValue($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:ExemptionReason'));
        self::assertSame('VATEX-FR-FRANCHISE', self::xmlValue($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:ExemptionReasonCode'));
        self::assertSame('0.00', self::xmlValue($xml, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
    }

    public function testItWritesTheNotesThePaymentMeansAndTheDeliveryAddress(): void
    {
        $xml = $this->write(self::invoice(
            [self::lineItem('Mug', 1, 1000, 1000, 200, '20%')],
            shippingAddress: self::address('3 place Bellecour', '69002', 'Lyon', 'FR'),
        ));

        self::assertSame(['PMD', 'PMT', 'AAB'], self::xmlValues($xml, '//rsm:ExchangedDocument/ram:IncludedNote/ram:SubjectCode'));
        self::assertSame('58', self::xmlValue($xml, self::SETTLEMENT . '/ram:SpecifiedTradeSettlementPaymentMeans/ram:TypeCode'));
        self::assertSame('FR7630006000011234567890189', self::xmlValue($xml, self::SETTLEMENT . '/ram:SpecifiedTradeSettlementPaymentMeans/ram:PayeePartyCreditorFinancialAccount/ram:IBANID'));
        self::assertSame('AGRIFRPP', self::xmlValue($xml, self::SETTLEMENT . '/ram:SpecifiedTradeSettlementPaymentMeans/ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID'));
        self::assertSame('2026/10/000001', self::xmlValue($xml, self::SETTLEMENT . '/ram:PaymentReference'));
        self::assertSame('Jeanne Martin', self::xmlValue($xml, '//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:Name'));
        self::assertSame('Lyon', self::xmlValue($xml, '//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CityName'));
        self::assertSame('000000042', self::xmlValue($xml, '//ram:SellerOrderReferencedDocument/ram:IssuerAssignedID'));
    }

    public function testTheRuleChecksDetectAnInconsistentTotal(): void
    {
        $xml = str_replace('<ram:GrandTotalAmount>45.83<', '<ram:GrandTotalAmount>45.84<', $this->write(self::multiRateInvoice()));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('BR-CO-15');

        self::assertEn16931BusinessRules($xml);
    }

    public function testTheXsdCheckDetectsAMisplacedElement(): void
    {
        $xml = (string) preg_replace('#(<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>)(\s*)(<ram:PaymentReference>.*?</ram:PaymentReference>)#s', '$3$2$1', $this->write(self::multiRateInvoice()));
        $xml = str_replace('<ram:PaymentReference>', '<ram:Unknown/><ram:PaymentReference>', $xml);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('XSD');

        self::assertValidAgainstXsd($xml);
    }

    private function write(InvoiceInterface $invoice, ?ChannelSettings $settings = null, ?CustomerIdentifiers $identifiers = null): string
    {
        return (new CiiXmlWriter())->write((new FacturXInvoiceMapper())->map($invoice, $settings ?? self::settings(), $identifiers));
    }
}
