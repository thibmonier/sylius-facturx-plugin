# Changelog

## 1.1.0 (unreleased)

### Added

- `InvoicePaymentTermsProviderInterface`: per-invoice due date (BT-9) and payment terms
  (BT-20), e.g. for orders paid on account. Services implementing it are autoconfigured with
  the `tmonier_facturx.invoice_payment_terms_provider` tag (priority: highest first); the first
  provider returning terms wins.
- `ChannelSettingsPaymentTermsProvider`: the 1.0 behaviour (issue date + payment due days when
  an amount is due, channel payment terms text), used when no provider handles an invoice.
- `CompositeInvoicePaymentTermsProvider` (`tmonier_facturx.payment_terms_provider`, alias
  `InvoicePaymentTermsProviderInterface`). When an amount is due, provider terms with neither
  due date nor text are skipped (BR-CO-25); when nothing is due (paid invoice), the due date
  is dropped. A provider passed to `FacturXInvoiceMapper` directly is wrapped in the composite,
  so the same rules apply.

### Changed

- `FacturXInvoiceMapper` takes an optional `InvoicePaymentTermsProviderInterface` constructor
  argument (the service is wired with the composite provider). Without it, the mapper behaves
  as in 1.0.

No change in the generated XML without a provider.

## 1.0.0 (2026-10-11)

First release.
