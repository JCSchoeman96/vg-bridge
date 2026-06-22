# Security Model

## Shared secret

The HMAC shared secret lives in `wp-config.php` on both sites. It is never stored in this repository.

Sender constant: `VG_COURSE_BRIDGE_SHARED_SECRET`  
Receiver constant: `VG_COURSE_BRIDGE_SHARED_SECRET` (must match sender)

## Request signing

Every bridge request is signed with HMAC-SHA256:

```
signature = HMAC-SHA256(timestamp + "\n" + nonce + "\n" + body, shared_secret)
```

Headers:

- `X-VG-Bridge-Source` — must match receiver's `VG_COURSE_BRIDGE_ALLOWED_SOURCE`
- `X-VG-Bridge-Timestamp` — GMT datetime, must be within ±5 minutes
- `X-VG-Bridge-Nonce` — unique per request
- `X-VG-Bridge-Signature` — hex-encoded HMAC

## Replay protection

- **Timestamp** rejects requests outside the allowed window.
- **Nonce** is consumed only after a valid signature is verified, preventing same-window replay.

## Entitlement whitelist

Receiver may define `VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS` to restrict which LearnDash group/course IDs can be granted. This prevents a compromised sender from granting arbitrary courses.

## Payment authority

- Sender never trusts Paystack directly.
- WooCommerce paid order state (`is_paid()`) is the only payment authority for granting access.
- Paystack webhooks must update WooCommerce; the bridge listens to WooCommerce hooks only.

## v1 access rules

- One paid order buyer billing email = one course access (quantity ignored for same entitlement).
- Full refund revokes access only after a successful grant was sent.
- Partial refund does not revoke access.
