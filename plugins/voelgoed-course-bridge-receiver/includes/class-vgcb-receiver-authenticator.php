<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Authenticator
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __construct(private readonly VGCB_Receiver_Log $log)
    {
    }

    public function authenticate(WP_REST_Request $request): true|WP_Error
    {
        $source = sanitize_text_field($request->get_header('x-vg-bridge-source') ?: '');
        $timestamp = sanitize_text_field($request->get_header('x-vg-bridge-timestamp') ?: '');
        $nonce = sanitize_text_field($request->get_header('x-vg-bridge-nonce') ?: '');
        $signature = sanitize_text_field($request->get_header('x-vg-bridge-signature') ?: '');
        $body = $request->get_body();

        $allowed_source = defined('VG_COURSE_BRIDGE_ALLOWED_SOURCE') ? sanitize_text_field((string) VG_COURSE_BRIDGE_ALLOWED_SOURCE) : '';
        $shared_secret = defined('VG_COURSE_BRIDGE_SHARED_SECRET') ? (string) VG_COURSE_BRIDGE_SHARED_SECRET : '';

        if ($allowed_source === '' || $shared_secret === '') {
            return new WP_Error('vgcb_missing_config', 'Receiver bridge constants are not configured.', ['status' => 500]);
        }

        if ($source === '' || !hash_equals($allowed_source, $source)) {
            return new WP_Error('vgcb_bad_source', 'Invalid bridge source.', ['status' => 403]);
        }

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return new WP_Error('vgcb_missing_headers', 'Missing bridge authentication headers.', ['status' => 401]);
        }

        $timestamp_unix = strtotime($timestamp);
        if (!$timestamp_unix) {
            return new WP_Error('vgcb_bad_timestamp', 'Invalid bridge timestamp.', ['status' => 401]);
        }

        if (abs(time() - $timestamp_unix) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return new WP_Error('vgcb_stale_timestamp', 'Bridge timestamp is outside the allowed window.', ['status' => 401]);
        }

        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $shared_secret);
        if (!hash_equals($expected, $signature)) {
            return new WP_Error('vgcb_bad_signature', 'Invalid bridge signature.', ['status' => 401]);
        }

        if (!$this->log->consume_nonce($source, $nonce)) {
            return new WP_Error('vgcb_replayed_nonce', 'Bridge nonce was already used.', ['status' => 409]);
        }

        return true;
    }
}
