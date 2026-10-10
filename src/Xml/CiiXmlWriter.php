<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Xml;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Tmonier\SyliusFacturXPlugin\Mapper\FacturXInvoiceMapper;
use Tmonier\SyliusFacturXPlugin\Model\FacturXInvoice;
use Tmonier\SyliusFacturXPlugin\Model\Party;

/**
 * Writes the UN/CEFACT Cross Industry Invoice (D16B) of the Factur-X EN 16931 profile with the
 * horstoeko/zugferd builder, which orders the elements as the XSD requires.
 */
final class CiiXmlWriter implements CiiXmlWriterInterface
{
    public function write(FacturXInvoice $invoice): string
    {
        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

        $document->setDocumentInformation($invoice->number, $invoice->typeCode, $invoice->issueDate, $invoice->currencyCode);
        if (null !== $invoice->businessProcess) {
            $document->setDocumentBusinessProcess($invoice->businessProcess);
        }
        foreach ($invoice->notes as $note) {
            $document->addDocumentNote($note->content, null, $note->subjectCode);
        }

        $this->writeSeller($document, $invoice->seller);
        $this->writeBuyer($document, $invoice->buyer);

        if (null !== $invoice->salesOrderReference) {
            $document->setDocumentSellerOrderReferencedDocument($invoice->salesOrderReference);
        }

        if (null !== $invoice->deliveryAddress) {
            $document->setDocumentShipTo($invoice->deliveryName);
            $address = $invoice->deliveryAddress;
            $document->setDocumentShipToAddress($address->line1, $address->line2, null, $address->postcode, $address->city, $address->countryCode, $address->subdivision);
        }
        if (null !== $invoice->deliveryDate) {
            $document->setDocumentSupplyChainEvent($invoice->deliveryDate);
        }

        $payment = $invoice->paymentInstructions;
        if (null !== $payment && null !== $payment->iban) {
            $document->addDocumentPaymentMeanToCreditTransfer($payment->iban, $payment->accountName, null, $payment->bic, $payment->remittanceInformation);
        }

        foreach ($invoice->vatBreakdown as $breakdown) {
            $document->addDocumentTax(
                $breakdown->category,
                'VAT',
                self::amount($breakdown->taxableAmount),
                self::amount($breakdown->taxAmount),
                null === $breakdown->rate ? null : (float) $breakdown->rate,
                $breakdown->exemptionReason,
                $breakdown->exemptionReasonCode,
                null,
                null,
                null,
                $invoice->vatPointDateCode,
            );
        }

        if (null !== $invoice->paymentTerms || null !== $invoice->dueDate) {
            $document->addDocumentPaymentTerm($invoice->paymentTerms, $invoice->dueDate);
        }

        $document->setDocumentSummation(
            self::amount($invoice->grandTotalAmount),
            self::amount($invoice->duePayableAmount),
            self::amount($invoice->lineTotalAmount),
            null,
            null,
            self::amount($invoice->taxBasisTotalAmount),
            self::amount($invoice->taxTotalAmount),
            null,
            self::amount($invoice->prepaidAmount),
        );

        foreach ($invoice->lines as $line) {
            $document->addNewPosition($line->id);
            $document->setDocumentPositionProductDetails($line->name, $line->description, $line->sellerItemId);
            if (null !== $line->grossUnitPrice) {
                $document->setDocumentPositionGrossPrice(self::amount($line->grossUnitPrice));
                $document->addDocumentPositionGrossPriceAllowanceCharge(self::amount($line->unitDiscount()), false);
            }
            $document->setDocumentPositionNetPrice(self::amount($line->netUnitPrice));
            $document->setDocumentPositionQuantity((float) $line->quantity, FacturXInvoiceMapper::UNIT_CODE);
            $document->addDocumentPositionTax($line->vatCategory, 'VAT', null === $line->vatRate ? null : (float) $line->vatRate);
            $document->setDocumentPositionLineSummation(self::amount($line->netAmount));
        }

        return $document->getContent();
    }

    private function writeSeller(ZugferdDocumentBuilder $document, Party $seller): void
    {
        $document->setDocumentSeller($seller->name, null, $seller->legalInformation);
        if (null !== $seller->globalId) {
            $document->addDocumentSellerGlobalId($seller->globalId, Party::SCHEME_SIRET);
        }
        $address = $seller->address;
        $document->setDocumentSellerAddress($address->line1, $address->line2, null, $address->postcode, $address->city, $address->countryCode, $address->subdivision);
        if (null !== $seller->legalRegistrationId) {
            $document->setDocumentSellerLegalOrganisation($seller->legalRegistrationId, Party::SCHEME_SIREN, null);
        }
        if (null !== $seller->electronicAddress) {
            $document->setDocumentSellerCommunication($seller->electronicAddressScheme, $seller->electronicAddress);
        }
        if (null !== $seller->vatId) {
            $document->addDocumentSellerVATRegistrationNumber($seller->vatId);
        }
    }

    private function writeBuyer(ZugferdDocumentBuilder $document, Party $buyer): void
    {
        $document->setDocumentBuyer($buyer->name);
        $address = $buyer->address;
        $document->setDocumentBuyerAddress($address->line1, $address->line2, null, $address->postcode, $address->city, $address->countryCode, $address->subdivision);
        if (null !== $buyer->legalRegistrationId) {
            $document->setDocumentBuyerLegalOrganisation($buyer->legalRegistrationId, Party::SCHEME_SIREN, null);
        }
        if (null !== $buyer->electronicAddress) {
            $document->setDocumentBuyerCommunication($buyer->electronicAddressScheme, $buyer->electronicAddress);
        }
        if (null !== $buyer->vatId) {
            $document->addDocumentBuyerVATRegistrationNumber($buyer->vatId);
        }
    }

    /** Minor units (cents) to a decimal amount. */
    private static function amount(int $minorUnits): float
    {
        return round($minorUnits / 100, 2);
    }
}
