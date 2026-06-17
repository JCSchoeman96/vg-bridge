<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Sender
{
    private static ?VGCB_Sender $instance = null;

    public static function instance(): VGCB_Sender
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if (!$this->dependencies_loaded()) {
            add_action('admin_notices', [$this, 'render_dependency_notice']);
            return;
        }

        VGCB_Sender_Activator::maybe_upgrade();

        $outbox = new VGCB_Sender_Outbox();
        $http = new VGCB_Sender_Http($outbox);

        (new VGCB_Sender_Order_Handler($outbox))->hooks();
        (new VGCB_Sender_Admin($outbox))->hooks();

        add_action('vgcb_sender_process_outbox_job', [$http, 'process_outbox_job'], 10, 1);
        add_action('vgcb_sender_retry_outbox_job', [$http, 'process_outbox_job'], 10, 1);
    }

    private function dependencies_loaded(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_order');
    }

    public function render_dependency_notice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>Voelgoed Course Bridge - Sender</strong> requires WooCommerce to be active on this site.</p></div>';
    }
}
