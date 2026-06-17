<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Sender_Http
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly VGCB_Sender_Outbox $outbox)
    {
    }

    public function process_outbox_job(int $outbox_id): void
    {
        $row = $this->outbox->get_row(absint($outbox_id));
        if (!$row) {
            return;
        }

        if (!in_array($row->status, [VGCB_Sender_Outbox::STATUS_PENDING, VGCB_Sender_Outbox::STATUS_FAILED, VGCB_Sender_Outbox::STATUS_REVOKE_FAILED], true)) {
            return;
        }

        if ((int) $row->attempt_count >= self::MAX_ATTEMPTS) {
            return;
        }

        $remote_url = $this->remote_url();
        $shared_secret = $this->shared_secret();
        $source_site = $this->source_site();

        if ($remote_url === '' || $shared_secret === '' || $source_site === '') {
            $this->outbox->mark_failed((int) $row->id, 0, 'Bridge constants are missing. Check VG_COURSE_BRIDGE_REMOTE_URL, VG_COURSE_BRIDGE_SHARED_SECRET, and VG_COURSE_BRIDGE_SOURCE_SITE.');
            $this->notify_admin((int) $row->id, 'Bridge constants are missing.');
            return;
        }

        $body = (string) $row->payload_json;
        if ($body === '') {
            $this->outbox->mark_failed((int) $row->id, 0, 'Payload JSON is empty.');
            return;
        }

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $nonce = wp_generate_uuid4();
        $signature = $this->sign($timestamp, $nonce, $body, $shared_secret);

        $this->outbox->mark_attempt((int) $row->id);

        $response = wp_remote_post($remote_url, [
            'timeout' => 20,
            'redirection' => 2,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-VG-Bridge-Source' => $source_site,
                'X-VG-Bridge-Timestamp' => $timestamp,
                'X-VG-Bridge-Nonce' => $nonce,
                'X-VG-Bridge-Signature' => $signature,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            $this->handle_failure($row, 0, $response->get_error_message());
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $response_body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        if ($code >= 200 && $code < 300 && is_array($decoded) && !empty($decoded['success'])) {
            $remote_user_id = isset($decoded['user_id']) ? absint($decoded['user_id']) : null;
            $this->outbox->mark_success((int) $row->id, $code, $remote_user_id);
            $this->add_order_note((int) $row->order_id, $row, $decoded);
            return;
        }

        $error = 'Remote bridge request failed.';
        if (is_array($decoded) && isset($decoded['message'])) {
            $error = sanitize_text_field((string) $decoded['message']);
        } elseif ($response_body !== '') {
            $error = wp_trim_words(wp_strip_all_tags($response_body), 50, '...');
        }

        $this->handle_failure($row, $code, $error);
    }

    private function handle_failure(object $row, int $response_code, string $error): void
    {
        $this->outbox->mark_failed((int) $row->id, $response_code, $error);

        $fresh = $this->outbox->get_row((int) $row->id);
        $attempt_count = $fresh ? (int) $fresh->attempt_count : ((int) $row->attempt_count + 1);

        if ($attempt_count < self::MAX_ATTEMPTS) {
            $delay = min(3600, (int) (300 * $attempt_count));
            $this->outbox->schedule_job((int) $row->id, $delay);
            return;
        }

        $this->notify_admin((int) $row->id, $error);
    }

    private function sign(string $timestamp, string $nonce, string $body, string $shared_secret): string
    {
        $canonical = $timestamp . "\n" . $nonce . "\n" . $body;

        return hash_hmac('sha256', $canonical, $shared_secret);
    }

    private function add_order_note(int $order_id, object $row, array $decoded): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $direction = $row->direction === VGCB_Sender_Outbox::DIRECTION_REVOKE ? 'revoked' : 'granted';
        $duplicate = !empty($decoded['duplicate']) ? ' Remote reported duplicate/idempotent success.' : '';
        $message = sprintf(
            'Voelgoed Course Bridge: LearnDash access %s for %s (%s #%d).%s',
            $direction,
            (string) $row->customer_email,
            (string) $row->entitlement_type,
            (int) $row->entitlement_id,
            $duplicate
        );

        $order->add_order_note($message);
    }

    private function notify_admin(int $outbox_id, string $error): void
    {
        $admin_email = defined('VG_COURSE_BRIDGE_ADMIN_EMAIL') ? (string) VG_COURSE_BRIDGE_ADMIN_EMAIL : get_option('admin_email');
        $admin_email = sanitize_email($admin_email ?: '');

        if ($admin_email === '') {
            return;
        }

        $subject = 'Voelgoed Course Bridge sender failure';
        $message = "A course bridge request failed after the final retry.\n\nOutbox ID: {$outbox_id}\nError: {$error}\n\nPlease check WooCommerce > Course Bridge.";

        wp_mail($admin_email, $subject, $message);
    }

    private function remote_url(): string
    {
        return defined('VG_COURSE_BRIDGE_REMOTE_URL') ? esc_url_raw((string) VG_COURSE_BRIDGE_REMOTE_URL) : '';
    }

    private function shared_secret(): string
    {
        return defined('VG_COURSE_BRIDGE_SHARED_SECRET') ? (string) VG_COURSE_BRIDGE_SHARED_SECRET : '';
    }

    private function source_site(): string
    {
        return defined('VG_COURSE_BRIDGE_SOURCE_SITE') ? sanitize_text_field((string) VG_COURSE_BRIDGE_SOURCE_SITE) : '';
    }
}
