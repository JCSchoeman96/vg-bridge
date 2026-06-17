<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Sender_Activator
{
    public static function activate(): void
    {
        self::create_tables();
    }

    public static function maybe_upgrade(): void
    {
        if (get_option('vgcb_sender_db_version') !== VGCB_SENDER_VERSION) {
            self::create_tables();
        }
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = self::outbox_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_email VARCHAR(190) NOT NULL DEFAULT '',
            entitlement_type VARCHAR(32) NOT NULL DEFAULT '',
            entitlement_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            access_label VARCHAR(190) NOT NULL DEFAULT '',
            direction VARCHAR(16) NOT NULL DEFAULT 'grant',
            payload_hash CHAR(64) NOT NULL DEFAULT '',
            payload_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error LONGTEXT NULL,
            last_response_code SMALLINT UNSIGNED NULL,
            remote_user_id BIGINT UNSIGNED NULL,
            created_at_gmt DATETIME NOT NULL,
            updated_at_gmt DATETIME NOT NULL,
            sent_at_gmt DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_order_entitlement_direction (order_id, entitlement_type, entitlement_id, direction),
            KEY status_attempts (status, attempt_count),
            KEY order_id (order_id),
            KEY customer_email (customer_email)
        ) {$charset_collate};";

        dbDelta($sql);

        update_option('vgcb_sender_db_version', VGCB_SENDER_VERSION, false);
    }

    public static function outbox_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'vgcb_sender_outbox';
    }
}
