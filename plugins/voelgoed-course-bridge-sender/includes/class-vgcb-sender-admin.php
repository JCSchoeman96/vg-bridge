<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Sender_Admin
{
    public function __construct(private readonly VGCB_Sender_Outbox $outbox)
    {
    }

    public function hooks(): void
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_vgcb_sender_retry', [$this, 'handle_retry_action']);
    }

    public function add_product_data_tab(array $tabs): array
    {
        $tabs['vgcb_course_bridge'] = [
            'label' => 'Course Bridge',
            'target' => 'vgcb_course_bridge_product_data',
            'class' => [],
            'priority' => 80,
        ];

        return $tabs;
    }

    public function render_product_data_panel(): void
    {
        global $post;

        $product_id = $post ? (int) $post->ID : 0;

        echo '<div id="vgcb_course_bridge_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => '_vgcb_enable_course_bridge',
            'label' => 'Enable Course Bridge access',
            'description' => 'Grant one LearnDash access on leer.voelgoed.co.za when this product is paid on winkel.voelgoed.co.za.',
            'value' => get_post_meta($product_id, '_vgcb_enable_course_bridge', true),
        ]);

        woocommerce_wp_select([
            'id' => '_vgcb_entitlement_type',
            'label' => 'Remote LearnDash access type',
            'description' => 'Use LearnDash Group where possible. Group access is more flexible than direct course access.',
            'desc_tip' => true,
            'value' => get_post_meta($product_id, '_vgcb_entitlement_type', true) ?: 'learndash_group',
            'options' => [
                'learndash_group' => 'LearnDash Group',
                'learndash_course' => 'LearnDash Course',
            ],
        ]);

        woocommerce_wp_text_input([
            'id' => '_vgcb_entitlement_id',
            'label' => 'Remote LearnDash group/course ID',
            'type' => 'number',
            'custom_attributes' => [
                'min' => '1',
                'step' => '1',
            ],
            'description' => 'The LearnDash Group or Course ID on leer.voelgoed.co.za.',
            'desc_tip' => true,
            'value' => get_post_meta($product_id, '_vgcb_entitlement_id', true),
        ]);

        woocommerce_wp_text_input([
            'id' => '_vgcb_access_label',
            'label' => 'Access label',
            'description' => 'Human-readable name shown in logs and emails, for example: 21-Day Course.',
            'desc_tip' => true,
            'value' => get_post_meta($product_id, '_vgcb_access_label', true),
        ]);

        echo '<p class="form-field"><label>Grant mode</label><span class="description">Fixed in v1: one paid order buyer email receives one access, regardless of quantity.</span></p>';
        echo '</div>';
        echo '</div>';
    }

    public function save_product_fields(WC_Product $product): void
    {
        $enabled = isset($_POST['_vgcb_enable_course_bridge']) ? 'yes' : 'no';
        $type = isset($_POST['_vgcb_entitlement_type']) ? sanitize_key(wp_unslash((string) $_POST['_vgcb_entitlement_type'])) : 'learndash_group';
        $remote_id = isset($_POST['_vgcb_entitlement_id']) ? absint(wp_unslash((string) $_POST['_vgcb_entitlement_id'])) : 0;
        $label = isset($_POST['_vgcb_access_label']) ? sanitize_text_field(wp_unslash((string) $_POST['_vgcb_access_label'])) : '';

        if (!in_array($type, ['learndash_group', 'learndash_course'], true)) {
            $type = 'learndash_group';
        }

        $product->update_meta_data('_vgcb_enable_course_bridge', $enabled);
        $product->update_meta_data('_vgcb_entitlement_type', $type);
        $product->update_meta_data('_vgcb_entitlement_id', $remote_id);
        $product->update_meta_data('_vgcb_access_label', $label);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            'Course Bridge',
            'Course Bridge',
            'manage_woocommerce',
            'vgcb-sender',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'voelgoed-course-bridge-sender'));
        }

        $rows = $this->outbox->get_recent(100);
        $remote_url = defined('VG_COURSE_BRIDGE_REMOTE_URL') ? (string) VG_COURSE_BRIDGE_REMOTE_URL : '';
        $source_site = defined('VG_COURSE_BRIDGE_SOURCE_SITE') ? (string) VG_COURSE_BRIDGE_SOURCE_SITE : '';
        $has_secret = defined('VG_COURSE_BRIDGE_SHARED_SECRET') && (string) VG_COURSE_BRIDGE_SHARED_SECRET !== '';

        echo '<div class="wrap">';
        echo '<h1>Voelgoed Course Bridge - Sender</h1>';
        echo '<h2>Status</h2>';
        echo '<table class="widefat striped" style="max-width: 900px;"><tbody>';
        echo '<tr><th>Remote URL</th><td>' . esc_html($remote_url ?: 'Missing') . '</td></tr>';
        echo '<tr><th>Source site</th><td>' . esc_html($source_site ?: 'Missing') . '</td></tr>';
        echo '<tr><th>Shared secret</th><td>' . esc_html($has_secret ? 'Configured' : 'Missing') . '</td></tr>';
        echo '<tr><th>Admin failure email</th><td>' . esc_html(defined('VG_COURSE_BRIDGE_ADMIN_EMAIL') ? (string) VG_COURSE_BRIDGE_ADMIN_EMAIL : get_option('admin_email')) . '</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Recent Outbox Entries</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>Order</th><th>Email</th><th>Entitlement</th><th>Direction</th><th>Status</th><th>Attempts</th><th>Response</th><th>Updated GMT</th><th>Action</th></tr></thead><tbody>';

        if (!$rows) {
            echo '<tr><td colspan="10">No bridge entries yet.</td></tr>';
        }

        foreach ($rows as $row) {
            $retry_url = wp_nonce_url(
                admin_url('admin-post.php?action=vgcb_sender_retry&outbox_id=' . absint($row->id)),
                'vgcb_sender_retry_' . absint($row->id)
            );

            echo '<tr>';
            echo '<td>' . esc_html((string) $row->id) . '</td>';
            echo '<td><a href="' . esc_url(admin_url('post.php?post=' . absint($row->order_id) . '&action=edit')) . '">#' . esc_html((string) $row->order_id) . '</a></td>';
            echo '<td>' . esc_html((string) $row->customer_email) . '</td>';
            echo '<td>' . esc_html((string) $row->entitlement_type) . ' #' . esc_html((string) $row->entitlement_id) . '</td>';
            echo '<td>' . esc_html((string) $row->direction) . '</td>';
            echo '<td><strong>' . esc_html((string) $row->status) . '</strong><br><small>' . esc_html(wp_trim_words((string) $row->last_error, 18)) . '</small></td>';
            echo '<td>' . esc_html((string) $row->attempt_count) . '</td>';
            echo '<td>' . esc_html((string) $row->last_response_code) . '</td>';
            echo '<td>' . esc_html((string) $row->updated_at_gmt) . '</td>';
            echo '<td><a class="button" href="' . esc_url($retry_url) . '">Retry</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_retry_action(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'voelgoed-course-bridge-sender'));
        }

        $outbox_id = isset($_GET['outbox_id']) ? absint($_GET['outbox_id']) : 0;
        check_admin_referer('vgcb_sender_retry_' . $outbox_id);

        if ($outbox_id > 0) {
            $this->outbox->reset_for_retry($outbox_id);
            $this->outbox->schedule_job($outbox_id);
        }

        wp_safe_redirect(admin_url('admin.php?page=vgcb-sender'));
        exit;
    }
}
