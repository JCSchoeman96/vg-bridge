<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Sender_Outbox implements VGCB_Sender_Outbox_Store
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_REVOKE_FAILED = 'revoke_failed';

    public function table(): string
    {
        return VGCB_Sender_Activator::outbox_table();
    }

    public function insert_payload(array $payload, string $direction): int
    {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $payload_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload_json)) {
            return 0;
        }

        $order_id = absint($payload['source_order_id'] ?? 0);
        $order_item_id = absint($payload['source_order_item_id'] ?? 0);
        $product_id = absint($payload['source_product_id'] ?? 0);
        $customer_email = sanitize_email((string) ($payload['customer']['email'] ?? ''));
        $entitlement_type = sanitize_key((string) ($payload['entitlement']['type'] ?? ''));
        $entitlement_id = absint($payload['entitlement']['id'] ?? 0);
        $access_label = sanitize_text_field((string) ($payload['entitlement']['label'] ?? ''));
        $payload_hash = hash('sha256', $payload_json);

        if ($order_id <= 0 || $entitlement_id <= 0 || $customer_email === '') {
            return 0;
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table()} WHERE order_id = %d AND entitlement_type = %s AND entitlement_id = %d AND direction = %s LIMIT 1",
            $order_id,
            $entitlement_type,
            $entitlement_id,
            $direction
        ));

        if ($existing_id > 0) {
            return $existing_id;
        }

        $inserted = $wpdb->insert(
            $this->table(),
            [
                'order_id' => $order_id,
                'order_item_id' => $order_item_id,
                'product_id' => $product_id,
                'customer_email' => $customer_email,
                'entitlement_type' => $entitlement_type,
                'entitlement_id' => $entitlement_id,
                'access_label' => $access_label,
                'direction' => $direction,
                'payload_hash' => $payload_hash,
                'payload_json' => $payload_json,
                'status' => self::STATUS_PENDING,
                'attempt_count' => 0,
                'created_at_gmt' => $now,
                'updated_at_gmt' => $now,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if (!$inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function get_row(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id));

        return $row ?: null;
    }

    /**
     * @return object[]
     */
    public function get_recent(int $limit = 100): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d",
            $limit
        )) ?: [];
    }

    /**
     * @return object[]
     */
    public function get_grants_for_order(int $order_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE order_id = %d AND direction = %s AND status = %s ORDER BY id ASC",
            $order_id,
            self::DIRECTION_GRANT,
            self::STATUS_SENT
        )) ?: [];
    }

    public function mark_attempt(int $id): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET attempt_count = attempt_count + 1, updated_at_gmt = %s WHERE id = %d",
            gmdate('Y-m-d H:i:s'),
            $id
        ));
    }

    public function mark_success(int $id, int $response_code, ?int $remote_user_id): void
    {
        global $wpdb;

        $row = $this->get_row($id);
        $status = ($row && $row->direction === self::DIRECTION_REVOKE) ? self::STATUS_REVOKED : self::STATUS_SENT;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()}
             SET status = %s, last_error = NULL, last_response_code = %d, remote_user_id = %d, sent_at_gmt = %s, updated_at_gmt = %s
             WHERE id = %d",
            $status,
            $response_code,
            $remote_user_id ?? 0,
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s'),
            $id
        ));
    }

    public function mark_failed(int $id, int $response_code, string $error): void
    {
        global $wpdb;

        $row = $this->get_row($id);
        $status = ($row && $row->direction === self::DIRECTION_REVOKE) ? self::STATUS_REVOKE_FAILED : self::STATUS_FAILED;

        $wpdb->update(
            $this->table(),
            [
                'status' => $status,
                'last_error' => wp_strip_all_tags($error),
                'last_response_code' => $response_code,
                'updated_at_gmt' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );
    }

    public function mark_skipped_pending_for_order(int $order_id, string $reason): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET status = %s, last_error = %s, updated_at_gmt = %s WHERE order_id = %d AND direction = %s AND status IN (%s, %s)",
            self::STATUS_SKIPPED,
            $reason,
            gmdate('Y-m-d H:i:s'),
            $order_id,
            self::DIRECTION_GRANT,
            self::STATUS_PENDING,
            self::STATUS_FAILED
        ));
    }

    public function reset_for_retry(int $id): void
    {
        global $wpdb;

        $row = $this->get_row($id);
        if (!$row) {
            return;
        }

        if (in_array($row->status, [self::STATUS_SENT, self::STATUS_REVOKED, self::STATUS_SKIPPED], true)) {
            return;
        }

        $wpdb->update(
            $this->table(),
            [
                'status' => self::STATUS_PENDING,
                'attempt_count' => 0,
                'last_error' => null,
                'last_response_code' => null,
                'updated_at_gmt' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $id]
        );
    }

    public function schedule_job(int $outbox_id, int $delay_seconds = 0): void
    {
        $outbox_id = absint($outbox_id);
        if ($outbox_id <= 0) {
            return;
        }

        if (function_exists('as_enqueue_async_action') && $delay_seconds <= 0) {
            as_enqueue_async_action('vgcb_sender_process_outbox_job', [$outbox_id], 'voelgoed-course-bridge');
            return;
        }

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + max(0, $delay_seconds), 'vgcb_sender_retry_outbox_job', [$outbox_id], 'voelgoed-course-bridge');
            return;
        }

        wp_schedule_single_event(time() + max(0, $delay_seconds), 'vgcb_sender_retry_outbox_job', [$outbox_id]);
    }
}
