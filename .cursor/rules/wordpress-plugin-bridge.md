# Voelgoed Course Bridge Rules

This repo contains two WordPress plugins:
- Sender for winkel.voelgoed.co.za
- Receiver for leer.voelgoed.co.za

## Hard rules

- Never hardcode secrets.
- Never commit wp-config.php constants.
- Do not talk directly to Paystack for LearnDash access.
- Sender trusts WooCommerce paid order state only.
- Receiver must validate HMAC before processing payloads.
- One paid order buyer email equals one course access in v1.
- Full refund revokes access only after successful grant exists.
- Partial refund does not revoke access.
- Customer-facing failure emails are not part of v1.
- Keep generated ZIPs out of Git.
- Plugin ZIPs must be clean WordPress plugin ZIPs.
- Do not rewrite the plugin into a framework.
- Prefer small testability seams over reflection-heavy tests.
