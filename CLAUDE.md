# CLAUDE.md

Sylius 2 plugin turning the invoice PDFs of `sylius/invoicing-plugin` into Factur-X / ZUGFeRD
PDF/A-3 files (EN 16931 profile) with an embedded `factur-x.xml`.
Research, French rules and field mapping: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional + integration, need the test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
vendor/bin/console tmonier:facturx:regenerate-pdfs --dry-run -v
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3318, MailHog on
8037/1037), `tests/TestApplication/.env.local` (DATABASE_URL + MAILER_DSN) and
`.env.test.local` (DATABASE_URL). Databases: `tmonier_sylius_facturx_dev` / `_test`.
Invoice PDFs are files: dev in `vendor/sylius/test-application/private/invoices/`, test in
`private/invoices_test/` (see `tests/TestApplication/config/config.yaml`).

## Architecture

- `src/Generator/FacturXInvoicePdfFileGenerator` — decorates
  `sylius_invoicing.generator.invoice_pdf_file`: inner PDF → XML → PDF/A-3, then dispatches
  `FacturXInvoiceGenerated`. Any failure is logged and the plain PDF is kept (an invoice must
  never fail because of Factur-X).
- `src/Generator/FacturXGenerator` — loads the channel settings and customer identifiers,
  calls the mapper, the writer and the embedder. Enabled = global config + channel setting.
- `src/Mapper/FacturXInvoiceMapper` — Invoicing Plugin `Invoice` → `src/Model/FacturXInvoice`
  (syntax-free, amounts in cents). Checks that the lines add up to the invoice total.
- `src/Xml/CiiXmlWriter` — `FacturXInvoice` → CII XML with the horstoeko builder (XSD order).
- `src/Pdf/FacturXPdfEmbedder` — horstoeko `ZugferdDocumentPdfMerger` (FPDI): PDF/A-3B, XMP,
  AFRelationship (config, default `Alternative`).
- `src/Entity/ChannelSettings`, `CustomerIdentifiers` — plugin tables linked to the channel /
  customer (core entities untouched); XML mappings in `config/doctrine` (mapped superclasses).
- `src/Controller/Admin/FacturXController` — settings pages and XML download.
- `src/Command/RegenerateInvoicePdfsCommand` — renders stored PDFs again (file managers do not
  overwrite: remove first).
- `tests/TestApplication/src/Pdf/FakeInvoicePdfFileGenerator` — FPDF invoice when wkhtmltopdf
  is not installed (decorates the inner generator, priority 10).

## Rules

- Amounts stay integers (cents) until the XML writer; never compute VAT again from rates: the
  invoice amounts (Sylius per-unit rounding) are the truth, the XML must match the PDF.
- Every generated XML in tests goes through `FacturXAssertionsTrait::assertValidAgainstXsd()`
  and `assertEn16931BusinessRules()`; PDFs through `assertFacturXPdf()`.
- Keep `SPEC.md` sources and **UNVERIFIED** marks up to date when a rule changes.
- Hook context values may be missing when null: use `hookable_metadata.context.x|default(null)`.
- Model class parameters `%tmonier_facturx.model.*.class%` are set in `prepend()` because
  grids are processed before the extension loads.
- The extension alias is `tmonier_sylius_facturx` (not the conventional `tmonier_sylius_factur_x`):
  `TmonierSyliusFacturXPlugin::getContainerExtension()` is overridden for that.
- Migrations use the Schema API (MySQL, MariaDB, PostgreSQL) and contain plugin tables only.
- Every translation key must exist in en, fr, de, es, it, nl, pl (`TranslationsTest`, which also
  checks the keys used in src/templates/config). Clear the cache (dev and test) after adding a
  translation file.
- Functional tests run in a transaction rolled back after each test (kernel not rebooted);
  fixtures are random, create the state a test needs (e.g. `enableFacturX()`).
