<?php
/**
 * Plugin Name: Voelgoed Course Bridge - Receiver
 * Description: Receives signed course-access requests from winkel.voelgoed.co.za and grants/revokes LearnDash access on leer.voelgoed.co.za.
 * Version: 1.0.0
 * Author: Voelgoed Media
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Text Domain: voelgoed-course-bridge-receiver
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('VGCB_RECEIVER_VERSION', '1.0.0');
define('VGCB_RECEIVER_PLUGIN_FILE', __FILE__);
define('VGCB_RECEIVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VGCB_RECEIVER_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-activator.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-log.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-mailer.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-access.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-rest.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-admin.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver.php';

register_activation_hook(__FILE__, ['VGCB_Receiver_Activator', 'activate']);

add_action('plugins_loaded', static function (): void {
    VGCB_Receiver::instance()->boot();
});
