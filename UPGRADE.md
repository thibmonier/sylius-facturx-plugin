# Upgrade

## From 1.0 to 1.1

Nothing to do: without a payment terms provider, the due date (BT-9) and the payment terms
(BT-20) are computed from the channel settings exactly as in 1.0.

- If you decorated `tmonier_facturx.mapper.invoice` only to change BT-9 or BT-20, implement
  `Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface` instead (see
  the README, "Extension point: per-order payment terms").
- If you instantiate `FacturXInvoiceMapper` yourself, its constructor now accepts an optional
  provider; `new FacturXInvoiceMapper()` keeps the channel-settings behaviour.
- If you replaced the `tmonier_facturx.mapper.invoice` service definition, pass it
  `service('tmonier_facturx.payment_terms_provider')` to benefit from the providers.
