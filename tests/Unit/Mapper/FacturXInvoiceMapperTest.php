<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Shipment;
use Sylius\InvoicingPlugin\Entity\BillingData;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Tests\Tmonier\SyliusFacturXPlugin\Unit\InvoiceBuilderTrait;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiers;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapper;
use Tmonier\SyliusFacturXPlugin\Model\InvoiceLine;
use Tmonier\SyliusFacturXPlugin\Model\Note;
use Tmonier\SyliusFacturXPlugin\Model\PaymentInstructions;
use Tmonier\SyliusFacturXPlugin\Model\VatBreakdown;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\CompositeInvoicePaymentTermsProvider;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTerms;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;

final class FacturXInvoiceMapperTest extends TestCase
{
    use InvoiceBuilderTrait;

    private FacturXInvoiceMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new FacturXInvoiceMapper();
    }

    public function testItMapsTheHeaderOfTheInvoice(): void
    {
        $document = $this->mapper->map(self::multiRateInvoice(), self::settings());

        self::assertSame('2026/10/000001', $document->number);
        self::assertSame('380', $document->typeCode);
        self::assertSame('2026-10-10', $document->issueDate->format('Y-m-d'));
        self::assertSame('EUR', $document->currencyCode);
        self::assertSame('000000042', $document->salesOrderReference);
    }

    public function testItSumsTheLinesPerVatRateAndReconcilesTheTotals(): void
    {
        $document = $this->mapper->map(self::multiRateInvoice(), self::settings());

        self::assertCount(3, $document->lines);
        self::assertEquals([
            new VatBreakdown('S', '20', 2500, 500),
            new VatBreakdown('S', '5.5', 1500, 83),
        ], $document->vatBreakdown);
        self::assertSame(4000, $document->lineTotalAmount);
        self::assertSame(4000, $document->taxBasisTotalAmount);
        self::assertSame(583, $document->taxTotalAmount);
        self::assertSame(4583, $document->grandTotalAmount);
    }

    public function testShippingIsAnInvoiceLineWithItsOwnVatRate(): void
    {
        $document = $this->mapper->map(self::multiRateInvoice(), self::settings());

        $shipping = $document->lines[2];
        self::assertSame('3', $shipping->id);
        self::assertSame('UPS', $shipping->name);
        self::assertSame(1, $shipping->quantity);
        self::assertSame(500, $shipping->netAmount);
        self::assertSame('S', $shipping->vatCategory);
        self::assertSame('20', $shipping->vatRate);
        self::assertNull($shipping->sellerItemId);
    }

    public function testDiscountsAreWrittenAsGrossPriceAndNetPrice(): void
    {
        $invoice = self::invoice([
            self::lineItem('Mug', 3, 1000, 900, 540, '20%', variantCode: 'MUG'),
            self::lineItem('UPS', 1, 500, 0, 0, '20%'),
        ]);

        $document = $this->mapper->map($invoice, self::settings());

        $mug = $document->lines[0];
        self::assertSame(1000, $mug->grossUnitPrice);
        self::assertSame(900, $mug->netUnitPrice);
        self::assertSame(100, $mug->unitDiscount());
        self::assertSame(2700, $mug->netAmount);

        // Free shipping thanks to a shipping promotion.
        $shipping = $document->lines[1];
        self::assertSame(500, $shipping->grossUnitPrice);
        self::assertSame(0, $shipping->netUnitPrice);
        self::assertSame(0, $shipping->netAmount);

        self::assertSame(2700, $document->lineTotalAmount);
        self::assertSame(3240, $document->grandTotalAmount);
    }

    public function testOrderPromotionsDistributedOnTheUnitsGiveLinesWithUnevenUnitPrices(): void
    {
        // A 10.00 order promotion spread on 3 units of 10.00 (3.34 + 3.33 + 3.33): net unit
        // prices of 6.66, 6.67 and 6.67. The Invoicing Plugin merges only the identical units, so
        // the line net amounts stay exact.
        $invoice = self::invoice([
            self::lineItem('Cap', 1, 1000, 666, 133, '20%'),
            self::lineItem('Cap', 2, 1000, 667, 266, '20%'),
        ]);

        $document = $this->mapper->map($invoice, self::settings());

        self::assertSame([666, 1334], array_map(static fn (InvoiceLine $line): int => $line->netAmount, $document->lines));
        self::assertEquals([new VatBreakdown('S', '20', 2000, 399)], $document->vatBreakdown);
        self::assertSame(2399, $document->grandTotalAmount);
    }

    public function testTheVatOfTheBreakdownIsTheSumOfTheTaxesRoundedPerUnitBySylius(): void
    {
        // 3 x 8.33 at 20 %: Sylius rounds the tax of each unit (1.666 -> 1.67), so the invoice
        // shows 5.01 of VAT where 24.99 x 20 % = 5.00. The XML keeps the amounts of the invoice
        // (within the 1.00 tolerance of BR-CO-17) so that its total matches the PDF.
        $invoice = self::invoice([self::lineItem('Pen', 3, 833, 833, 501, '20%')]);

        $document = $this->mapper->map($invoice, self::settings());

        self::assertEquals([new VatBreakdown('S', '20', 2499, 501)], $document->vatBreakdown);
        self::assertSame(3000, $document->grandTotalAmount);
    }

    public function testItParsesTheFloatRateLabelsOfTheInvoicingPlugin(): void
    {
        $invoice = self::invoice([self::lineItem('Wine', 1, 1000, 1000, 70, '7.000000000000001%')]);

        $document = $this->mapper->map($invoice, self::settings());

        self::assertSame('7', $document->lines[0]->vatRate);
    }

    public function testLinesWithoutVatUseTheZeroVatCategoryOfTheChannel(): void
    {
        $settings = self::settings();
        $settings->setZeroVatCategory('E');
        $settings->setVatExemptionReason('TVA non applicable, art. 293 B du CGI');
        $settings->setVatExemptionReasonCode('VATEX-FR-FRANCHISE');
        $invoice = self::invoice([
            self::lineItem('Lesson', 1, 3000, 3000, 0, null),
            self::lineItem('Book', 1, 1500, 1500, 0, '0%'),
        ]);

        $document = $this->mapper->map($invoice, $settings);

        self::assertSame(['E', 'E'], array_map(static fn (InvoiceLine $line): string => $line->vatCategory, $document->lines));
        self::assertSame(['0', '0'], array_map(static fn (InvoiceLine $line): ?string => $line->vatRate, $document->lines));
        self::assertEquals([
            new VatBreakdown('E', '0', 4500, 0, 'TVA non applicable, art. 293 B du CGI', 'VATEX-FR-FRANCHISE'),
        ], $document->vatBreakdown);
    }

    public function testReverseChargeAndExemptCategoriesGetADefaultExemptionReason(): void
    {
        $settings = self::settings();
        $settings->setZeroVatCategory('AE');

        $document = $this->mapper->map(self::invoice([self::lineItem('Consulting', 1, 10000, 10000, 0, null)]), $settings);

        self::assertEquals([new VatBreakdown('AE', '0', 10000, 0, 'Reverse charge')], $document->vatBreakdown);
    }

    public function testNotSubjectToVatLinesHaveNoRate(): void
    {
        $settings = self::settings();
        $settings->setZeroVatCategory('O');

        $document = $this->mapper->map(self::invoice([self::lineItem('Donation', 1, 2000, 2000, 0, null)]), $settings);

        self::assertNull($document->lines[0]->vatRate);
        self::assertEquals([new VatBreakdown('O', null, 2000, 0, 'Not subject to VAT')], $document->vatBreakdown);
    }

    public function testZeroRatedLinesAndStandardRatedLinesAreSeparateBreakdowns(): void
    {
        $settings = self::settings();
        $settings->setZeroVatCategory('Z');

        $document = $this->mapper->map(self::invoice([
            self::lineItem('Newspaper', 1, 200, 200, 0, '0%'),
            self::lineItem('Mug', 1, 1000, 1000, 200, '20%'),
        ]), $settings);

        self::assertEquals([
            new VatBreakdown('Z', '0', 200, 0),
            new VatBreakdown('S', '20', 1000, 200),
        ], $document->vatBreakdown);
    }

    public function testItRefusesAnInvoiceWhoseLinesDoNotAddUpToItsTotal(): void
    {
        $invoice = self::invoice([self::lineItem('Mug', 1, 1000, 1000, 200, '20%')], total: 1300);

        $this->expectException(FacturXException::class);
        $this->expectExceptionMessage('do not add up to its total (1300 expected, 1200 computed)');

        $this->mapper->map($invoice, self::settings());
    }

    public function testAPaidInvoiceHasNothingDueAndTheAlreadyPaidBusinessProcess(): void
    {
        $document = $this->mapper->map(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_COMPLETED), self::settings());

        self::assertSame('B2', $document->businessProcess);
        self::assertSame(4583, $document->prepaidAmount);
        self::assertSame(0, $document->duePayableAmount);
        self::assertNull($document->dueDate);
    }

    public function testAnUnpaidInvoiceHasADueDate(): void
    {
        $settings = self::settings();
        $settings->setOperationCategory(ChannelSettingsInterface::OPERATION_SERVICES);

        $document = $this->mapper->map(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), $settings);

        self::assertSame('S1', $document->businessProcess);
        self::assertSame(0, $document->prepaidAmount);
        self::assertSame(4583, $document->duePayableAmount);
        self::assertSame('2026-11-09', $document->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 30 jours', $document->paymentTerms);
    }

    public function testAPaymentTermsProviderGivesPerOrderTerms(): void
    {
        $provider = new class() implements InvoicePaymentTermsProviderInterface {
            public ?int $duePayableAmount = null;

            public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): ?InvoicePaymentTerms
            {
                $this->duePayableAmount = $duePayableAmount;

                return '000000042' === $invoice->order()->getNumber()
                    ? new InvoicePaymentTerms(new \DateTimeImmutable('2026-12-31'), 'Net 60 days end of month')
                    : null;
            }
        };
        $mapper = new FacturXInvoiceMapper(new CompositeInvoicePaymentTermsProvider([$provider]));

        $document = $mapper->map(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), self::settings());

        self::assertSame(4583, $provider->duePayableAmount);
        self::assertSame('2026-12-31', $document->dueDate?->format('Y-m-d'));
        self::assertSame('Net 60 days end of month', $document->paymentTerms);
    }

    public function testAProviderWithoutTermsFallsBackToTheChannelSettings(): void
    {
        $provider = new class() implements InvoicePaymentTermsProviderInterface {
            public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): ?InvoicePaymentTerms
            {
                return null;
            }
        };

        $document = (new FacturXInvoiceMapper($provider))->map(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), self::settings());

        self::assertSame('2026-11-09', $document->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 30 jours', $document->paymentTerms);
    }

    public function testAProviderPassedDirectlyFollowsTheCompositeRules(): void
    {
        $provider = new class() implements InvoicePaymentTermsProviderInterface {
            public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): InvoicePaymentTerms
            {
                return $duePayableAmount > 0 ? new InvoicePaymentTerms() : new InvoicePaymentTerms(new \DateTimeImmutable('2026-12-31'), 'Paid');
            }
        };
        $mapper = new FacturXInvoiceMapper($provider);

        // Empty terms for an amount due: channel settings (BR-CO-25).
        $due = $mapper->map(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_PENDING), self::settings());
        self::assertSame('2026-11-09', $due->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 30 jours', $due->paymentTerms);

        // Paid invoice: no due date.
        $paid = $mapper->map(self::multiRateInvoice(InvoiceInterface::PAYMENT_STATE_COMPLETED), self::settings());
        self::assertNull($paid->dueDate);
        self::assertSame('Paid', $paid->paymentTerms);
    }

    public function testVatOnDebitsIsTheVatPointDateCode5(): void
    {
        $settings = self::settings();
        self::assertNull($this->mapper->map(self::multiRateInvoice(), $settings)->vatPointDateCode);

        $settings->setVatOnDebits(true);
        self::assertSame('5', $this->mapper->map(self::multiRateInvoice(), $settings)->vatPointDateCode);
    }

    public function testItMapsTheSeller(): void
    {
        $seller = $this->mapper->map(self::multiRateInvoice(), self::settings())->seller;

        self::assertSame('Boutique Exemple SAS', $seller->name);
        self::assertSame('303265045', $seller->legalRegistrationId);
        self::assertSame('30326504500011', $seller->globalId);
        self::assertSame('FR40303265045', $seller->vatId);
        self::assertSame('SAS au capital de 10 000 € - RCS Paris 303 265 045', $seller->legalInformation);
        self::assertSame('FR', $seller->address->countryCode);
        self::assertSame('1 avenue des Champs-Élysées', $seller->address->line1);
        self::assertSame('75008', $seller->address->postcode);
        self::assertSame('Paris', $seller->address->city);
    }

    public function testTheChannelSettingsOverrideTheShopBillingData(): void
    {
        $settings = self::settings();
        $settings->setLegalName('Exemple Holding SAS');
        $settings->setVatNumber('FR03552081317');
        $settings->setElectronicAddress('303265045');
        $settings->setElectronicAddressScheme('0225');

        $seller = $this->mapper->map(self::multiRateInvoice(), $settings)->seller;

        self::assertSame('Exemple Holding SAS', $seller->name);
        self::assertSame('FR03552081317', $seller->vatId);
        self::assertSame('303265045', $seller->electronicAddress);
        self::assertSame('0225', $seller->electronicAddressScheme);
    }

    public function testTheSellerNeedsALegalIdentifier(): void
    {
        $settings = self::settings();
        $settings->setSiren(null);
        $settings->setSiret(null);
        $invoice = self::multiRateInvoice();
        $invoice->shopBillingData()->setTaxId(null);

        $this->expectException(FacturXException::class);
        $this->expectExceptionMessage('BR-CO-26');

        $this->mapper->map($invoice, $settings);
    }

    public function testTheSellerNeedsACountry(): void
    {
        $invoice = self::multiRateInvoice();
        $invoice->shopBillingData()->setCountryCode(null);

        $this->expectException(FacturXException::class);
        $this->expectExceptionMessage('no country');

        $this->mapper->map($invoice, self::settings());
    }

    public function testTheBuyerIsTheCompanyOfTheBillingAddress(): void
    {
        $buyer = $this->mapper->map(self::multiRateInvoice(), self::settings())->buyer;

        self::assertSame('Martin Conseil SARL', $buyer->name);
        self::assertSame('FR', $buyer->address->countryCode);
        self::assertNull($buyer->legalRegistrationId);
        self::assertNull($buyer->vatId);
    }

    public function testTheBuyerIdentifiersComeFromTheCustomerB2bIdentifiers(): void
    {
        $identifiers = new CustomerIdentifiers();
        $identifiers->setCompanyName('Martin Conseil');
        $identifiers->setSiren('552081317');
        $identifiers->setVatNumber('FR03552081317');
        $identifiers->setElectronicAddress('552081317');
        $identifiers->setElectronicAddressScheme('0225');
        $billingData = new BillingData('Jeanne', 'Martin', 'FR', '12 rue de la Paix', 'Paris', '75002');
        $invoice = self::invoice([self::lineItem('Mug', 1, 1000, 1000, 200, '20%')], billingData: $billingData);

        $buyer = $this->mapper->map($invoice, self::settings(), $identifiers)->buyer;

        self::assertSame('Martin Conseil', $buyer->name);
        self::assertSame('552081317', $buyer->legalRegistrationId);
        self::assertSame('FR03552081317', $buyer->vatId);
        self::assertSame('552081317', $buyer->electronicAddress);
        self::assertSame('0225', $buyer->electronicAddressScheme);
    }

    public function testAConsumerBuyerIsNamedAfterTheBillingAddress(): void
    {
        $billingData = new BillingData('Jeanne', 'Martin', 'DE', 'Hauptstraße 1', 'Berlin', '10115');
        $invoice = self::invoice([self::lineItem('Mug', 1, 1000, 1000, 200, '20%')], billingData: $billingData);

        $buyer = $this->mapper->map($invoice, self::settings())->buyer;

        self::assertSame('Jeanne Martin', $buyer->name);
        self::assertSame('DE', $buyer->address->countryCode);
    }

    public function testTheDeliveryAddressIsOnlyGivenWhenItDiffersFromTheBillingAddress(): void
    {
        $same = $this->mapper->map(self::multiRateInvoice(), self::settings());
        self::assertNull($same->deliveryAddress);
        self::assertNull($same->deliveryName);

        $invoice = self::invoice(
            [self::lineItem('Mug', 1, 1000, 1000, 200, '20%')],
            shippingAddress: self::address('3 place Bellecour', '69002', 'Lyon', 'FR'),
        );
        $different = $this->mapper->map($invoice, self::settings());

        self::assertSame('Jeanne Martin', $different->deliveryName);
        $address = $different->deliveryAddress;
        self::assertNotNull($address);
        self::assertSame('3 place Bellecour', $address->line1);
        self::assertSame('69002', $address->postcode);
        self::assertSame('Lyon', $address->city);
        self::assertSame('FR', $address->countryCode);
    }

    public function testTheDeliveryDateIsTheLastShippingDateAndNothingWhenNotShipped(): void
    {
        $invoice = self::multiRateInvoice();
        self::assertNull($this->mapper->map($invoice, self::settings())->deliveryDate);

        $invoice->order()->addShipment(new Shipment());
        self::assertNull($this->mapper->map($invoice, self::settings())->deliveryDate, 'Shipment not shipped yet');

        foreach (['2026-10-12 09:00', '2026-10-14 16:00'] as $date) {
            $shipment = new Shipment();
            $shipment->setShippedAt(new \DateTime($date));
            $invoice->order()->addShipment($shipment);
        }
        $invoice->order()->addShipment(new Shipment());

        self::assertEquals(new \DateTimeImmutable('2026-10-14 16:00'), $this->mapper->map($invoice, self::settings())->deliveryDate);
    }

    public function testItMapsTheFrenchPaymentNotesAndTheBankAccount(): void
    {
        $document = $this->mapper->map(self::multiRateInvoice(), self::settings());

        self::assertSame(['PMD', 'PMT', 'AAB'], array_map(static fn (Note $note): ?string => $note->subjectCode, $document->notes));
        self::assertEquals(
            new PaymentInstructions(PaymentInstructions::SEPA_CREDIT_TRANSFER, 'FR7630006000011234567890189', null, 'AGRIFRPP', '2026/10/000001'),
            $document->paymentInstructions,
        );
    }

    public function testThereAreNoPaymentInstructionsWithoutIban(): void
    {
        $settings = self::settings();
        $settings->setIban(null);

        self::assertNull($this->mapper->map(self::multiRateInvoice(), $settings)->paymentInstructions);
    }
}
