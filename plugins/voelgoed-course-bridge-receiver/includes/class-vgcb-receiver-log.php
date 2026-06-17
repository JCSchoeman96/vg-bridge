<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Log
{
    public const STATUS_GRANTED = 'granted';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DUPLICATE = 'duplicate';

    public const OPERATION_GRANT = 'grant';
    public const OPERATION_REVOKE = 'revoke';

    public function grants_table(): string
    {
        return VGCB_Receiver_Activator::grants_table();
    }

    public function nonces_table(): string
    {
        return VGCB_Receiver_Activator::nonces_table();
    }

    public function consume_nonce(string $source_site, string $nonce): bool
    {
        global $wpdb;

        $this->cleanup_old_nonces();

        $inserted = $wpdb->insert(
            $this->nonces_table(),
            [
                'source_site' => sanitize_text_field($source_site),
                'nonce' => sanitize_text_field($nonce),
                'created_at_gmt' => gmdate('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%s']
        );

        return (bool) $inserted;
    }

    public function insert_or_get_existing(array $payload, string $operation): array
    {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $payload_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload_json)) {
            return ['id' => 0, 'duplicate' => false];
        }

        $source_site = sanitize_text_field((string) ($payload['source_site'] ?? ''));
        $source_order_id = absint($payload['source_order_id'] ?? 0);
        $entitlement_type = sanitize_key((string) ($payload['entitlement']['type'] ?? ''));
        $entitlement_id = absint($payload['entitlement']['id'] ?? 0);

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->grants_table()} WHERE source_site = %s AND source_order_id = %d AND entitlement_type = %s AND entitlement_id = %d AND operation = %s LIMIT 1",
            $source_site,
            $source_order_id,
            $entitlement_type,
            $entitlement_id,
            $operation
        ));

        if ($existing_id > 0) {
            return ['id' => $existing_id, 'duplicate' => true];
        }

        $inserted = $wpdb->insert(
            $this->grants_table(),
            [
                'source_site' => $source_site,
                'source_order_id' => $source_order_id,
                'source_order_number' => sanitize_text_field((string) ($payload['source_order_number'] ?? '')),
                'source_order_item_id' => absint($payload['source_order_item_id'] ?? 0),
                'source_product_id' => absint($payload['source_product_id'] ?? 0),
                'source_product_name' => sanitize_text_field((string) ($payload['source_product_name'] ?? '')),
                'customer_email' => sanitize_email((string) ($payload['customer']['email'] ?? '')),
                'entitlement_type' => $entitlement_type,
                'entitlement_id' => $entitlement_id,
                'access_label' => sanitize_text_field((string) ($payload['entitlement']['label'] ?? '')),
                'operation' => $operation,
                'status' => 'pending',
                'payload_hash' => hash('sha256', $payload_json),
                'payload_json' => $payload_json,
                'created_at_gmt' => $now,
                'updated_at_gmt' => $now,
            ],
            ['%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return ['id' => 0, 'duplicate' => false];
        }

        return ['id' => (int) $wpdb->insert_id, 'duplicate' => false];
    }

    public function get_grant(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->grants_table()} WHERE id = %d LIMIT 1", $id));

        return $row ?: null;
    }

    public function mark_success(int $id, int $user_id, string $operation): void
    {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'status' => $operation === self::OPERATION_REVOKE ? self::STATUS_REVOKED : self::STATUS_GRANTED,
            'user_id' => $user_id,
            'last_error' => null,
            'updated_at_gmt' => $now,
        ];

        if ($operation === self::OPERATION_REVOKE) {
            $data['revoked_at_gmt'] = $now;
        } else {
            $data['granted_at_gmt'] = $now;
        }

        $wpdb->update($this->grants_table(), $data, ['id' => $id]);
    }

    public function mark_failed(int $id, string $error): void
    {
        global $wpdb;

        $wpdb->update(
            $this->grants_table(),
            [
                'status' => self::STATUS_FAILED,
                'last_error' => wp_strip_all_tags($error),
                'updated_at_gmt' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function mark_duplicate_touched(int $id): void
    {
        global $wpdb;

        $wpdb->update(
            $this->grants_table(),
            ['updated_at_gmt' => gmdate('Y-m-d H:i:s')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * @return object[]
     */
    public function get_recent(int $limit = 100): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->grants_table()} ORDER BY id DESC LIMIT %d",
            $limit
        )) ?: [];
    }

    private function cleanup_old_nonces(): void
    {
        global $wpdb;

        if (mt_rand(1, 20) !== 1) {
            return;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM {$this->nonces_table()} WHERE created_at_gmt < %s", $cutoff));
    }
}
