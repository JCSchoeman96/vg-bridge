<?php
/**
 * Plugin Name: Voelgoed Course Bridge - Sender
 * Description: Sends paid WooCommerce bundle orders from winkel.voelgoed.co.za to leer.voelgoed.co.za for LearnDash access grants.
 * Version: 1.0.0
 * Author: Voelgoed Media
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * Text Domain: voelgoed-course-bridge-sender
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('VGCB_SENDER_VERSION', '1.0.0');
define('VGCB_SENDER_PLUGIN_FILE', __FILE__);
define('VGCB_SENDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VGCB_SENDER_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-activator.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-outbox.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-http.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-order-handler.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-admin.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender.php';

register_activation_hook(__FILE__, ['VGCB_Sender_Activator', 'activate']);

add_action('plugins_loaded', static function (): void {
    VGCB_Sender::instance()->boot();
});
