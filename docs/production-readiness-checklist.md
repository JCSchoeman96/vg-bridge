# Production Readiness Checklist

Complete before going live on winkel.voelgoed.co.za and leer.voelgoed.co.za.

## Code quality

- [ ] `composer test` passes locally
- [ ] GitHub Actions CI passes on the release branch
- [ ] `bash tools/build-zips.sh` produces clean plugin ZIPs
- [ ] `bash tools/check-zips.sh` confirms no forbidden files in ZIPs

## Configuration

- [ ] `VG_COURSE_BRIDGE_SHARED_SECRET` set on both sites (matching values)
- [ ] `VG_COURSE_BRIDGE_SOURCE_SITE` set on sender
- [ ] `VG_COURSE_BRIDGE_ALLOWED_SOURCE` set on receiver
- [ ] `VG_COURSE_BRIDGE_REMOTE_URL` points to production receiver endpoint
- [ ] `VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS` whitelist configured on receiver
- [ ] Admin notification emails configured

## Staging validation

- [ ] All items in [staging test checklist](staging-test-checklist.md) passed

## Operational

- [ ] Action Scheduler healthy on winkel (sender outbox processing)
- [ ] Cloudflare / WAF rules allow POST to `/wp-json/voelgoed-course-bridge/v1/grant-access`
- [ ] SMTP delivery confirmed for access and admin failure emails
- [ ] Rollback plan documented (disable plugins, revoke test access)

## Out of scope for v1

- Customer-facing failure emails to buyers
- Partial refund revoke logic
- Direct Paystack integration
