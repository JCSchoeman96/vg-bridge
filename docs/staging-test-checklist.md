# Staging Test Checklist

Run on winkel + leer staging before production.

- [ ] Install receiver on leer staging
- [ ] Add receiver constants
- [ ] Add entitlement whitelist
- [ ] Create LearnDash test group
- [ ] Attach test course to group
- [ ] Install sender on winkel staging
- [ ] Add sender constants
- [ ] Map test WooCommerce product
- [ ] Confirm Paystack test payment marks order Processing
- [ ] Confirm sender outbox sent
- [ ] Confirm receiver log granted
- [ ] Confirm user created on leer
- [ ] Confirm user enrolled in LearnDash group
- [ ] Confirm access email arrives
- [ ] Quantity 2 still grants one access
- [ ] Existing user reused
- [ ] Full refund revokes access
- [ ] Partial refund keeps access
- [ ] Wrong whitelist causes failure
- [ ] Cloudflare does not block endpoint
- [ ] Admin failure email works
