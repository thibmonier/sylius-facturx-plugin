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

        if ($invoice->deliveryAddress instanceof \Tmonier\SyliusFacturXPlugin\Model\Address) {
            $document->setDocumentShipTo($invoice->deliveryName);
            $address = $invoice->deliveryAddress;
            $document->setDocumentShipToAddress($address->line1, $address->line2, null, $address->postcode, $address->city, $address->countryCode, $address->subdivision);
        }
        if ($invoice->deliveryDate instanceof \DateTimeImmutable) {
            $document->setDocumentSupplyChainEvent($invoice->deliveryDate);
        }

        $payment = $invoice->paymentInstructions;
        if ($payment instanceof \Tmonier\SyliusFacturXPlugin\Model\PaymentInstructions && null !== $payment->iban) {
            $document->addDocumentPaymentMeanToCreditTransfer($payment->iban, $payment->accountName, null, $payment->bic, $payment->remittanceInformation);
        }

        foreach ($invoice->vatBreakdown as $breakdown) {
            $document->addDocumentTax(
                $breakdown->category,
                'VAT',
                $this->amount($breakdown->taxableAmount),
                $this->amount($breakdown->taxAmount),
                null === $breakdown->rate ? null : (float) $breakdown->rate,
                $breakdown->exemptionReason,
                $breakdown->exemptionReasonCode,
                null,
                null,
                null,
                $invoice->vatPointDateCode,
            );
        }

        if (null !== $invoice->paymentTerms || $invoice->dueDate instanceof \DateTimeImmutable) {
            $document->addDocumentPaymentTerm($invoice->paymentTerms, $invoice->dueDate);
        }

        $document->setDocumentSummation(
            $this->amount($invoice->grandTotalAmount),
            $this->amount($invoice->duePayableAmount),
            $this->amount($invoice->lineTotalAmount),
            null,
            null,
            $this->amount($invoice->taxBasisTotalAmount),
            $this->amount($invoice->taxTotalAmount),
            null,
            $this->amount($invoice->prepaidAmount),
        );

        foreach ($invoice->lines as $line) {
            $document->addNewPosition($line->id);
            $document->setDocumentPositionProductDetails($line->name, $line->description, $line->sellerItemId);
            if (null !== $line->grossUnitPrice) {
                $document->setDocumentPositionGrossPrice($this->amount($line->grossUnitPrice));
                $document->addDocumentPositionGrossPriceAllowanceCharge($this->amount($line->unitDiscount()), false);
            }
            $document->setDocumentPositionNetPrice($this->amount($line->netUnitPrice));
            $document->setDocumentPositionQuantity((float) $line->quantity, FacturXInvoiceMapper::UNIT_CODE);
            $document->addDocumentPositionTax($line->vatCategory, 'VAT', null === $line->vatRate ? null : (float) $line->vatRate);
            $document->setDocumentPositionLineSummation($this->amount($line->netAmount));
        }

        $xml = $document->getContent();

        return $this->addDocumentReferencesToLines($xml, $invoice->lines);
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
    private function amount(int $minorUnits): float
    {
        return round($minorUnits / 100, 2);
    }

    /**
     * Post-processes the XML to add AdditionalReferencedDocument elements to line items.
     *
     * The horstoeko builder doesn't support adding AdditionalReferencedDocument in the
     * SpecifiedLineTradeSettlement for EN16931 profile, so we inject it manually via DOM.
     *
     * @param list<\Tmonier\SyliusFacturXPlugin\Model\InvoiceLine> $lines
     */
    private function addDocumentReferencesToLines(string $xml, array $lines): string
    {
        // Collect lines that have a document reference
        $lineReferences = [];
        foreach ($lines as $line) {
            if (null !== $line->documentReference) {
                $lineReferences[$line->id] = $line->documentReference;
            }
        }

        if ([] === $lineReferences) {
            return $xml; // No references to add
        }

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');

        // For each line item, insert the AdditionalReferencedDocument if needed
        $lineItems = $xpath->query('//ram:IncludedSupplyChainTradeLineItem');
        if (false === $lineItems) {
            return $xml;
        }

        foreach ($lineItems as $lineItem) {
            \assert($lineItem instanceof \DOMElement);

            // Find the line ID
            $lineIdNodes = $xpath->query('.//ram:AssociatedDocumentLineDocument/ram:LineID', $lineItem);
            if (false === $lineIdNodes) {
                continue;
            }
            $lineIdNode = $lineIdNodes->item(0);
            if (!$lineIdNode instanceof \DOMNode) {
                continue;
            }
            $lineId = trim($lineIdNode->textContent);
            if ('' === $lineId) {
                continue;
            }

            if (!isset($lineReferences[$lineId])) {
                continue; // This line has no document reference
            }

            // Find the SpecifiedLineTradeSettlement element
            $settlementNodes = $xpath->query('.//ram:SpecifiedLineTradeSettlement', $lineItem);
            if (false === $settlementNodes) {
                continue;
            }
            $settlement = $settlementNodes->item(0);
            if (!$settlement instanceof \DOMElement) {
                continue;
            }

            // Create the AdditionalReferencedDocument element
            $refDoc = $dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:AdditionalReferencedDocument');

            $issuerId = $dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:IssuerAssignedID');
            $issuerId->textContent = $lineReferences[$lineId];
            $refDoc->appendChild($issuerId);

            $typeCode = $dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:TypeCode');
            $typeCode->textContent = '916';
            $refDoc->appendChild($typeCode);

            // Insert after SpecifiedTradeSettlementLineMonetarySummation (to respect XSD order)
            $summationNodes = $xpath->query('.//ram:SpecifiedTradeSettlementLineMonetarySummation', $settlement);
            if (false === $summationNodes) {
                $settlement->appendChild($refDoc);

                continue;
            }
            $summation = $summationNodes->item(0);
            if ($summation instanceof \DOMElement && null !== $summation->nextSibling) {
                $settlement->insertBefore($refDoc, $summation->nextSibling);
            } else {
                $settlement->appendChild($refDoc);
            }
        }

        return (string) $dom->saveXML();
    }
}
