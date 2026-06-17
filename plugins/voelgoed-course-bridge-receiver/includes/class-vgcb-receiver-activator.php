<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Activator
{
    public static function activate(): void
    {
        self::create_tables();
    }

    public static function maybe_upgrade(): void
    {
        if (get_option('vgcb_receiver_db_version') !== VGCB_RECEIVER_VERSION) {
            self::create_tables();
        }
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $grants = self::grants_table();
        $nonces = self::nonces_table();

        $sql_grants = "CREATE TABLE {$grants} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_site VARCHAR(190) NOT NULL DEFAULT '',
            source_order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_order_number VARCHAR(100) NOT NULL DEFAULT '',
            source_order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_product_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_email VARCHAR(190) NOT NULL DEFAULT '',
            user_id BIGINT UNSIGNED NULL,
            entitlement_type VARCHAR(32) NOT NULL DEFAULT '',
            entitlement_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            access_label VARCHAR(190) NOT NULL DEFAULT '',
            operation VARCHAR(16) NOT NULL DEFAULT 'grant',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            payload_hash CHAR(64) NOT NULL DEFAULT '',
            payload_json LONGTEXT NULL,
            last_error LONGTEXT NULL,
            created_at_gmt DATETIME NOT NULL,
            updated_at_gmt DATETIME NOT NULL,
            granted_at_gmt DATETIME NULL,
            revoked_at_gmt DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_source_operation (source_site, source_order_id, entitlement_type, entitlement_id, operation),
            KEY customer_email (customer_email),
            KEY status (status),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $sql_nonces = "CREATE TABLE {$nonces} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_site VARCHAR(190) NOT NULL DEFAULT '',
            nonce VARCHAR(100) NOT NULL DEFAULT '',
            created_at_gmt DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_source_nonce (source_site, nonce),
            KEY created_at_gmt (created_at_gmt)
        ) {$charset_collate};";

        dbDelta($sql_grants);
        dbDelta($sql_nonces);

        update_option('vgcb_receiver_db_version', VGCB_RECEIVER_VERSION, false);
    }

    public static function grants_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'vgcb_receiver_grants';
    }

    public static function nonces_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'vgcb_receiver_nonces';
    }
}
