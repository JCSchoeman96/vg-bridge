<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Payload_Validator
{
    public function validate(array $payload): true|WP_Error
    {
        $event = sanitize_key((string) ($payload['event'] ?? ''));
        $source_site = sanitize_text_field((string) ($payload['source_site'] ?? ''));
        $source_order_id = absint($payload['source_order_id'] ?? 0);
        $email = sanitize_email((string) ($payload['customer']['email'] ?? ''));
        $type = sanitize_key((string) ($payload['entitlement']['type'] ?? ''));
        $entitlement_id = absint($payload['entitlement']['id'] ?? 0);

        if (!in_array($event, ['grant_access', 'revoke_access'], true)) {
            return new WP_Error('vgcb_bad_event', 'Unsupported event.');
        }

        if ($source_site === '' || $source_order_id <= 0) {
            return new WP_Error('vgcb_bad_source_order', 'Missing source site or source order ID.');
        }

        if (!is_email($email)) {
            return new WP_Error('vgcb_bad_email', 'Invalid customer email.');
        }

        if (!in_array($type, ['learndash_group', 'learndash_course'], true) || $entitlement_id <= 0) {
            return new WP_Error('vgcb_bad_entitlement', 'Invalid entitlement type or ID.');
        }

        $whitelist = defined('VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS') ? VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS : [];
        if (is_array($whitelist) && $whitelist !== []) {
            $allowed_ids = $whitelist[$type] ?? [];
            $allowed_ids = is_array($allowed_ids) ? array_map('absint', $allowed_ids) : [];

            if (!in_array($entitlement_id, $allowed_ids, true)) {
                return new WP_Error('vgcb_entitlement_not_allowed', 'Entitlement ID is not allowed by the receiver whitelist.');
            }
        }

        return true;
    }
}
