# Testing Strategy

## Prerequisites

Local PHPUnit tests require PHP 8.2+, Composer, zip, and unzip. See [README.md](../README.md#prerequisites-ubuntu--wsl--kubuntu).

## Local tests prove plugin logic

PHPUnit tests in this repository mock WordPress, WooCommerce, and LearnDash. They verify:

- Sender builds correct payloads from paid WooCommerce orders
- Sender outbox deduplication and refund rules
- Receiver HMAC authentication (source, timestamp, signature, nonce)
- Receiver payload validation and entitlement whitelist
- Receiver user creation and LearnDash grant/revoke calls

Local tests do **not** require Paystack, a live WordPress install, or LearnDash.

## Staging tests prove real integrations

After local tests pass, deploy to staging and run the [staging test checklist](staging-test-checklist.md). Staging validates:

- Paystack test payments updating WooCommerce order status
- Action Scheduler / async outbox delivery
- Cloudflare allowing the receiver REST endpoint
- Real LearnDash enrollment and SMTP email delivery

## Production starts only after both pass

Do not deploy to production until:

1. `composer test` passes locally (or in CI)
2. All staging checklist items pass on winkel + leer staging sites

See also [production readiness checklist](production-readiness-checklist.md).
