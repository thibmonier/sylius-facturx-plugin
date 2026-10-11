# Sylius Factur-X Plugin: specification

Research and design notes of `tmonier/sylius-facturx-plugin` (October 2026). Sources are linked
in each section. **UNVERIFIED** marks a statement for which no primary source could be read
(paid standard, e-mail gated download, or vendor documentation only). **Local check** means it
was checked in a downloaded official artefact or in the source of a library.

This document is not legal or tax advice.

## 1. Goal and scope

Make the invoices of the official [Sylius Invoicing Plugin](https://github.com/Sylius/InvoicingPlugin)
(2.x) Factur-X / ZUGFeRD compliant: the invoice PDF becomes a PDF/A-3 with an embedded
`factur-x.xml` (UN/CEFACT Cross Industry Invoice) at the **EN 16931** profile, and the XML can be
downloaded on its own.

Context:

- **France**: every VAT-registered business must be able to *receive* e-invoices since
  1 September 2026; large and mid-sized companies must *issue* them since then, SMEs and micro
  companies from 1 September 2027. Invoices travel through *plateformes agréées* (PA, formerly
  PDP); the public portal (PPF) is only the directory and the data concentrator.
  [DGFiP, spécifications externes v3.2](https://www.impots.gouv.fr/specifications-externes-b2b)
- **Germany**: receiving EN 16931 e-invoices is mandatory since 1 January 2025; other formats
  are allowed until the end of 2026 (end of 2027 under 800 k€ turnover).
  [BMF](https://www.bundesfinanzministerium.de/Content/DE/Standardartikel/Themen/Steuern/das-aendert-sich-2025.html),
  [BMF FAQ](https://www.bundesfinanzministerium.de/Content/DE/FAQ/e-rechnung.html): ZUGFeRD ≥ 2.0.1
  is accepted, except the MINIMUM and BASIC-WL profiles.

Out of scope (v1): transmission to a PA, e-reporting of B2C sales (flux 10.3), credit notes
(the Invoicing Plugin has none), down-payment invoices, profiles other than EN 16931.

## 2. Factur-X / ZUGFeRD essentials

### Versions

- Current: **Factur-X 1.09.2 = ZUGFeRD 2.5.2** (4 August 2026), technically identical.
  [FNFE-MPE](https://fnfe-mpe.org/factur-x/factur-x_en/),
  [FeRD](https://www.ferd-net.de/en/downloads/publications/details/zugferd-252-english)
- 1.09 / 2.5 of 10 June 2026; 1.08 / 2.4 of 4 December 2025 (sub-lines), applicable 15 January 2026.
- 2.5.2 is said to be effective 1 September 2026 (rounding and VAT breakdown fixes, CII D22B XSD
  backward compatible with D16B). Secondary source:
  [vatupdate](https://www.vatupdate.com/2026/08/11/germany-and-france-update-zugferd-2-5-for-2026-e-invoicing-compliance/)
- The FNFE package (free, e-mail required) contains the specification, the XSD and schematron of
  the five profiles, code lists and samples. It states no licence: whether it may be
  redistributed is **UNVERIFIED**. `horstoeko/zugferd` (MIT) already ships its XSD, schematron
  and XSLT (local check), so the plugin ships none.

### Profile and document

| Item | Value |
|---|---|
| Root | `rsm:CrossIndustryInvoice` (namespaces `rsm`, `ram`, `udt`, `qdt` of UN/CEFACT `...:100`) |
| BT-24 guideline (EN 16931 / COMFORT) | `urn:cen.eu:en16931:2017` in `ExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ID` |
| Other profiles | BASIC `urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic`, EXTENDED `urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended` (local check, `ZugferdProfiles.php`) |
| Order of the root | `ExchangedDocumentContext`, `ExchangedDocument`, `SupplyChainTradeTransaction` (lines first, then header agreement, delivery, settlement) |

The XSD imposes the element order (local check of the EN 16931 XSD). The plugin writes the XML
with the `horstoeko/zugferd` builder, which follows it; the tests validate every generated XML
against the XSD.

### Calculation rules (EN 16931 schematron)

[ConnectingEurope eInvoicing-EN16931](https://github.com/ConnectingEurope/eInvoicing-EN16931),
release 1.3.16 (13 April 2026):

| Rule | Check |
|---|---|
| BR-CO-10 | BT-106 = Σ BT-131 (exact) |
| BR-CO-13 | BT-109 = Σ BT-131 − BT-107 + BT-108 |
| BR-CO-14 | BT-110 = Σ BT-117 (exact) |
| BR-CO-15 | BT-112 = BT-109 + BT-110 |
| BR-CO-16 | BT-115 = BT-112 − BT-113 + BT-114 |
| BR-CO-17, BR-S-09 | BT-117 = BT-116 × rate / 100, tolerance ±1 (currency unit) |
| BR-S-08 | per rate, BT-116 = Σ line net amounts + charges − allowances of category S (exact) |
| BR-S-10, BR-Z-10 | no exemption reason for S and Z |
| BR-E-10 | category E needs BT-120 or BT-121 |
| BR-AE-* | seller VAT id plus buyer VAT id or BT-47, rate 0, BT-117 = 0 |
| BR-CO-03 | BT-7 and BT-8 are mutually exclusive |
| BR-DEC-* | at most 2 decimals on document amounts and BT-131 |
| BR-CO-26 | the seller has BT-29, BT-30 or BT-31 |

Implication: line net amounts are rounded to the cent, and the VAT breakdown (BG-23) is derived
per (category, rate) from the lines.

### PDF/A-3 and XMP

Local check of `horstoeko/zugferd` (`assets/facturx_extension_schema.xmp`, `PdfBuilderAbstract`,
`PdfWriter`) and of `atgp/factur-x` (`Writer.php`):

- XMP namespace `fx` = `urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#`, with
  `fx:DocumentType` = `INVOICE`, `fx:DocumentFileName` = `factur-x.xml`, `fx:Version` = `1.0`,
  `fx:ConformanceLevel` = `EN 16931` (with the space).
- The PDF/A extension schema block describing these 4 properties is mandatory.
- `pdfaid:part` = 3, `pdfaid:conformance` = B.
- Embedded file `/Subtype /text#2Fxml` with `/Params /ModDate`, listed in the catalog `/AF`
  and in the `EmbeddedFiles` name tree; sRGB output intent; PDF 1.7.
- `zugferd-invoice.xml` was the ZUGFeRD 2.0 file name, deprecated in 2.1
  ([Mustang](https://www.mustangproject.org/zugferd/)).
- The source PDF must already be PDF/A-clean (embedded fonts, no transparency issues): the
  merge does not fix the content of the pages, with one exception below.
- **Glyph widths (ISO 19005-3, 6.2.11.5)**: the widths declared by the font dictionaries must
  match the advance widths of the embedded font programs. wkhtmltopdf 0.12.6 (Qt) writes the
  widths of the hinted font, about 0.7 % too small (276 instead of 277.83 for the space of
  Arial, 2048 units per em), on every embedded TrueType subset: veraPDF reported 94 failures on
  the default invoice template (local check with Mustang 2.26.0, 2026-10-11). FPDI copies the
  dictionaries as they are. The plugin's `FontWidthsNormalizer` reads the source PDF with the
  FPDI parser, recomputes `/W` (CIDFontType2, CIDToGIDMap Identity or stream) and `/Widths`
  (simple TrueType, (3,0), (1,0) or ASCII (3,1) cmap) from the `hmtx` table of each FontFile2,
  and appends the corrected dictionaries as an incremental update before the merge (own MIT
  code, no new dependency). Fonts with more than 20,000 glyphs, Type1/CFF/Type3 fonts and fonts
  defined directly in a resources dictionary are left as they are. After the fix, Mustang
  reports the default invoice valid (PDF/A-3B, EN 16931); the only remaining warning is R008
  for invoices of orders not shipped yet (see BT-72).

**AFRelationship** of the attached XML: the Factur-X specification (§ 6.2.2, read through a
secondary source) uses `Data` when the PDF carries more information than the XML, `Source` when
the PDF was produced from the XML and `Alternative` when both carry the same content. Germany
requires `Alternative` for BASIC, EN 16931 and EXTENDED; France accepts the three for BASIC and
above. The plugin uses **`Alternative`** by default (valid in both countries) and makes it
configurable. Primary text **UNVERIFIED** (the FNFE download is e-mail gated).
[dev.to summary](https://dev.to/pdfik/factur-x-zugferd-from-html-how-to-embed-en-16931-xml-in-a-pdfa-3-and-actually-pass-verapdf-and-pgn)

## 3. French mandatory mentions (reform of 2026)

Main source: DGFiP *spécifications externes* v3.2 (30 April 2026), annexe 1 (flux 1 v1.2) and
annexe 7 (rules v1.9), read locally from the
[DGFiP archive](https://www.impots.gouv.fr/specifications-externes-b2b).

| Mention | Where | Rule | Plugin |
|---|---|---|---|
| Business process, "cadre de facturation" | BT-23, `ExchangedDocumentContext/BusinessProcessSpecifiedDocumentContextParameter/ID`, 1..1, max 3 chars | G1.02 | `B1`/`S1`/`M1`, or `B2`/`S2`/`M2` when the invoice is already paid |
| Category of operation (goods / services / both) | carried by the letter of BT-23 (B, S, M) | G1.02 | channel setting `operationCategory` |
| Seller SIREN | BT-30, `SpecifiedLegalOrganization/ID@schemeID=0002`, 9 digits | G1.63, mandatory from 1/9/2026 (G6.08) | channel setting, Luhn-checked |
| Buyer SIREN (B2B) | BT-47, same path in `BuyerTradeParty` | G6.08 | customer B2B identifiers |
| Delivery address if different from the buyer's | BT-75 to BT-80 (BG-15), goods only | G6.16, from 1/9/2027 | written when the shipping address differs from the billing address |
| VAT on debits option | BT-8 `DueDateTypeCode` = `5` (CII), repeated in every BG-23 | G1.43, S1.13 | channel setting `vatOnDebits` |
| Payment notes | BT-22 notes with BT-21 subject codes `PMT` (40 € recovery indemnity, art. L441-10), `PMD` (late payment penalties), `AAB` (early payment discount), at most once each | market practice and PA rules; the exact BR-FR rule IDs are **UNVERIFIED** ([Vertex](https://developer.vertexinc.com/einvoicing/docs/france-ciiinv-structured-invoice-note), [Billit](https://docs.billit.be/docs/content-requirements-pa)) | three channel settings |
| Legal form, capital, RCS | BT-33 (seller additional legal information) | commercial code | channel settings, written as one BT-33 text |
| Electronic address | BT-34 / BT-49, `URIUniversalCommunication/URIID@schemeID` | scheme `0225` (French directory, value derived from the SIREN) comes from vendor documentation only: **UNVERIFIED** ([Payhawk](https://payhawk.com/help/fr/your-french-ppf-e-invoicing-address)). DGFiP annexe 7 lists a rejection `ADR_ERR` when BT-49/BT-34 is missing or wrong, so PAs route on it. | channel and customer settings, free scheme |

Other DGFiP rules taken into account:

- G1.24 allowed rates: 0, 10, 13, 20, 8.5, 19.6, 2.1, 5.5, 7, 20.6, 1.05, 0.9, 1.75, 9.2, 9.6.
  The plugin writes the Sylius rate; checking it is the merchant's job.
- G2.31 categories: S, E, AE, K, G, O, Z. G1.47: E requires BT-31; the "franchise en base"
  may use Z. G1.41: E requires BT-120 and BT-121.
- G1.52: `TXD` note "Membre d'un assujetti unique", `BLU` eco-participation: not generated.
- G1.60: `B4`/`S4`/`M4` (final invoice after down payment) are not used: the Invoicing Plugin
  has no down-payment invoices.
- Whether the bare code (`S1`) or an `FR:` prefix is expected in a Factur-X BT-23: DGFiP gives
  the bare code; vendors show `FR:` in UBL flux 2. **UNVERIFIED** for Factur-X; the plugin writes
  the bare code.
- Using `B2`/`S2`/`M2` for ordinary invoices of orders paid at checkout comes from the FNFE
  sheet on down payments ([Fiche flash acomptes](https://fnfe-mpe.org/wp-content/uploads/2026/04/Fiche_Flash-Acomptes.pdf)),
  which ties "2" to "already paid": **UNVERIFIED** for standard invoices.
- New mentions and the 15 € fine per missing mention: secondary source
  ([Weblex](https://www.weblex.fr/weblex-actualite/facturation-electronique-attention-aux-nouvelles-mentions-obligatoires-sur-les-factures)).
- Which Factur-X profiles the PAs accept: **UNVERIFIED**; EN 16931 is the safe choice.

## 4. Libraries

Packagist, 10 October 2026.

| Library | Licence | Notes |
|---|---|---|
| **horstoeko/zugferd** v1.0.132 (chosen) | MIT | ~8.2 M downloads, ZUGFeRD 2.5 / Factur-X 1.09, PHP ≥ 7.3, Symfony 5 to 8 components. Builder writing the XSD order, `ZugferdDocumentPdfMerger` (existing PDF + XML → PDF/A-3), `setAttachmentRelationshipType`, deterministic mode. Ships the FNFE XSD/schematron. Its KoSIT and PDF validators need Java. |
| ├ goetas-webservices/xsd2php-runtime 0.2.18 | MIT | |
| ├ jms/serializer 3.32.9 | MIT | |
| ├ setasign/fpdf 1.9.0 | MIT | needs ext-gd |
| ├ setasign/fpdi 2.6.8 | MIT | the free parser cannot read PDFs with compressed cross-reference streams (PDF 1.5+) ([setasign FAQ](https://setasign.com/support/faq/fpdi/error-document-compression-technique-not-supported)) |
| └ smalot/pdfparser 2.12.5 | **LGPL-3.0** | used to read PDFs; a Composer dependency, not bundled nor modified: compatible with an MIT plugin, but distributors of a combined work must respect the LGPL (replaceable library) |
| atgp/factur-x v3.5.0 | MIT | maintained, FPDI + smalot as well, merge only (no XML builder), `$relationship = 'Data'` by default |

TCPDF is not used. **Choice: horstoeko/zugferd**, because it builds the XML (with the element
order and code lists of each profile) *and* merges it into an existing PDF, which is what the
Invoicing Plugin produces.

The Invoicing Plugin 2.x renders its PDF with `sylius/pdf-generation-bundle` (KnpSnappy /
wkhtmltopdf by default, Gotenberg possible) and stores it in the `sylius_invoicing` storage.
wkhtmltopdf writes PDF 1.4 files that FPDI reads. A renderer producing compressed
cross-reference streams makes the merge fail: the plugin then keeps the plain PDF and logs the
error.

## 5. Validation

| Tool | Licence | Use |
|---|---|---|
| Factur-X EN 16931 XSD (via horstoeko) | see § 2 | **every unit and functional test**, PHP `DOMDocument::schemaValidate` |
| EN 16931 calculation rules | | re-computed in PHP by `tests/FacturXAssertionsTrait` (BR-01 to BR-16 subset, BR-CO-10/13/14/15/16/17/26, BR-x-08/09/10, BR-DEC) |
| ConnectingEurope schematron | EUPL-1.2 | XSLT 2, needs Saxon (Java): not run in PHP |
| [Mustang CLI](https://www.mustangproject.org/commandline/) 2.26.0 | Apache-2.0 | CI job: `java -jar Mustang-CLI-2.26.0.jar --no-notices --action validate --source x.pdf` (schematron + veraPDF). Read the XML report, not the exit code. |
| KoSIT validator 1.6.3 | Apache-2.0 | not used |
| [veraPDF](https://github.com/veraPDF/veraPDF-apps) | GPLv3+ / MPLv2+ | included in Mustang; standalone `docker run --rm -v "$PWD:/data" ghcr.io/verapdf/cli:latest -f 3b /data/x.pdf` |

UN/CEFACT XSDs may be redistributed only unmodified, with their notice
([licence](https://scancode-licensedb.aboutcode.org/un-cefact-2016.html)).

## 6. Design

```
Invoicing Plugin: InvoiceCreator / InvoiceFileProvider
        │ generate(invoice)
        ▼
FacturXInvoicePdfFileGenerator (decorates sylius_invoicing.generator.invoice_pdf_file)
        │ 1. inner generator renders the PDF
        │ 2. FacturXGenerator: channel settings + customer identifiers
        │      → FacturXInvoiceMapper → FacturXInvoice (amounts in cents)
        │      → CiiXmlWriter (horstoeko builder) → factur-x.xml
        │ 3. FacturXPdfEmbedder (horstoeko PDF merger) → PDF/A-3
        │ 4. dispatch tmonier_facturx.invoice_generated
        ▼
stored by the Invoicing Plugin → existing admin and shop download URLs serve it
```

- A failure (missing seller data, amounts that do not reconcile, unreadable PDF) never prevents
  the invoice: it is logged on the `tmonier_facturx` channel and the plain PDF is kept.
- The admin action *Factur-X XML* builds the XML on demand from the stored invoice.
- `tmonier:facturx:regenerate-pdfs` renders the stored PDFs again (after installing the plugin
  or fixing the seller data).
- Extension point for PA connectors: the `FacturXInvoiceGenerated` event
  (`tmonier_facturx.invoice_generated`) carries the invoice, the XML, the PDF content and the
  stored file name. It is dispatched every time the PDF is rendered (also on regeneration), so
  listeners must be idempotent.
- Data of the plugin: `tmonier_facturx_channel_settings` (one row per channel) and
  `tmonier_facturx_customer_identifiers` (one row per customer). Core entities are not modified.

### Amounts and rounding

The Invoicing Plugin creates one line item per group of identical order item units (net unit
price after promotions, tax rounded **per unit** by Sylius) and one per shipment (shipping
promotions included). Order promotions are already distributed on the units. Therefore:

- BT-131 (line net) = the line item subtotal; BT-146 (net price) = the discounted unit net
  price; BT-148 (gross price) with a BT-147 allowance when the unit price before promotions is
  higher.
- Shipping is an invoice line (name = shipping method label), with its own VAT rate.
- No document-level allowances or charges (BG-20/BG-21): BT-109 = BT-106.
- BG-23 sums the lines per (category, rate); BT-117 = Σ line VAT as computed by Sylius, so it
  can differ from BT-116 × rate by a few cents (within BR-CO-17's tolerance) but always matches
  the PDF and BT-112 = invoice total. The mapper refuses an invoice whose lines do not add up to
  its total.
- Paid invoice (Invoicing Plugin payment state `completed`): BT-113 = BT-112, BT-115 = 0, no due
  date. Otherwise BT-9 = issue date + the configured number of days.
- Lines without tax (no rate, or 0 % with no tax) get the channel's zero-VAT category
  (E, Z, O, AE, K or G); every line with a positive rate is category S. Default exemption
  reasons are written for E, O, AE, K, G when none is configured.

## 7. Field mapping

| BT | Name | Source |
|---|---|---|
| BT-1 | Invoice number | `Invoice::number()` |
| BT-2 | Issue date | `Invoice::issuedAt()` (format 102) |
| BT-3 | Type code | `380` (commercial invoice) |
| BT-5 | Currency | `Invoice::currencyCode()` |
| BT-8 | VAT point date code | `5` when *VAT on debits* is set |
| BT-9 | Due date | issue date + *payment due in (days)*, unpaid invoices only |
| BT-14 | Sales order reference | order number |
| BT-20 | Payment terms | channel setting |
| BT-21 / BT-22 | Notes | `PMD`, `PMT`, `AAB` channel settings |
| BT-23 | Business process | `B`/`S`/`M` + `1` (to pay) or `2` (paid) |
| BT-24 | Specification identifier | `urn:cen.eu:en16931:2017` |
| BT-27 | Seller name | channel setting *legal name*, else shop billing data company, else channel name |
| BT-29 | Seller identifier | SIRET, scheme `0009` |
| BT-30 | Seller legal registration | SIREN, scheme `0002` |
| BT-31 | Seller VAT id | channel setting, else shop billing data tax ID |
| BT-33 | Seller additional legal information | legal form / capital + trade register |
| BT-34 | Seller electronic address | channel setting + scheme |
| BG-5 | Seller address | shop billing data of the invoice (street, postcode, city, country) |
| BT-44 | Buyer name | billing address company, else customer *company name*, else first + last name |
| BT-47 | Buyer legal registration | customer SIREN, scheme `0002` |
| BT-48 | Buyer VAT id | customer setting |
| BT-49 | Buyer electronic address | customer setting + scheme |
| BG-8 | Buyer address | billing data of the invoice (province name or code as subdivision) |
| BT-70, BG-15 | Deliver-to name and address | order shipping address, only when it differs from the billing address |
| BT-72 | Actual delivery date | last `shippedAt` of the order shipments; nothing when no shipment is shipped (product decision: no invented date). The delivery group `ApplicableHeaderTradeDelivery` is mandatory in CII, so an unshipped order delivered to the billing address gives an empty group and the PEPPOL-EN16931-R008 *warning* (not an EN 16931 error) |
| BT-81 | Payment means | `58` SEPA credit transfer, when an IBAN is set |
| BT-83 | Remittance information | invoice number |
| BT-84 / BT-86 | IBAN / BIC | channel settings |
| BT-106 | Sum of line net amounts | Σ BT-131 |
| BT-109 | Total without VAT | = BT-106 |
| BT-110 | Total VAT | Σ BT-117 |
| BT-112 | Total with VAT | = invoice total (checked) |
| BT-113 | Paid amount | BT-112 when paid, else 0 |
| BT-115 | Amount due | BT-112 − BT-113 |
| BT-116 / BT-117 | VAT breakdown | Σ line net / Σ line VAT per (category, rate) |
| BT-118 / BT-119 | VAT category / rate | S with the line rate, or the zero-VAT category |
| BT-120 / BT-121 | Exemption reason / code | channel settings, or a default text |
| BT-126 | Line id | position, from 1 |
| BT-129 / BT-130 | Quantity / unit | line item quantity, `C62` |
| BT-131 | Line net amount | line item subtotal |
| BT-146 | Net price | discounted unit net price |
| BT-147 / BT-148 | Price discount / gross price | unit price − discounted price / unit price, when different |
| BT-151 / BT-152 | Line VAT category / rate | as BT-118 / BT-119 |
| BT-153 | Item name | product name (or shipping method label) |
| BT-154 | Item description | variant name, when different from the product name |
| BT-155 | Seller item id | variant code |

## 8. Responsibilities

The plugin produces a technically valid document from the data Sylius has. The merchant
remains responsible for: the legal data entered (SIREN, VAT number, mentions), the VAT rates and
categories configured in Sylius, choosing the category of operation and the exemption reasons,
getting the buyer's SIREN for B2B sales, and transmitting the invoices through a PA in France.
