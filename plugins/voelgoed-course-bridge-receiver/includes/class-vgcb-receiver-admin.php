<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Admin
{
    public function __construct(private readonly VGCB_Receiver_Log $log)
    {
    }

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        add_management_page(
            'Course Bridge Grants',
            'Course Bridge Grants',
            'manage_options',
            'vgcb-receiver',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'voelgoed-course-bridge-receiver'));
        }

        $rows = $this->log->get_recent(100);
        $allowed_source = defined('VG_COURSE_BRIDGE_ALLOWED_SOURCE') ? (string) VG_COURSE_BRIDGE_ALLOWED_SOURCE : '';
        $has_secret = defined('VG_COURSE_BRIDGE_SHARED_SECRET') && (string) VG_COURSE_BRIDGE_SHARED_SECRET !== '';
        $login_url = defined('VG_COURSE_BRIDGE_LOGIN_URL') ? (string) VG_COURSE_BRIDGE_LOGIN_URL : 'https://leer.voelgoed.co.za/my-rekening/';
        $admin_email = defined('VG_COURSE_BRIDGE_ADMIN_EMAIL') ? (string) VG_COURSE_BRIDGE_ADMIN_EMAIL : 'online@carpediem.co.za';

        echo '<div class="wrap">';
        echo '<h1>Voelgoed Course Bridge - Receiver</h1>';
        echo '<h2>Status</h2>';
        echo '<table class="widefat striped" style="max-width: 900px;"><tbody>';
        echo '<tr><th>REST endpoint</th><td>' . esc_html(rest_url('voelgoed-course-bridge/v1/grant-access')) . '</td></tr>';
        echo '<tr><th>Allowed source</th><td>' . esc_html($allowed_source ?: 'Missing') . '</td></tr>';
        echo '<tr><th>Shared secret</th><td>' . esc_html($has_secret ? 'Configured' : 'Missing') . '</td></tr>';
        echo '<tr><th>Login URL</th><td>' . esc_html($login_url) . '</td></tr>';
        echo '<tr><th>Admin failure email</th><td>' . esc_html($admin_email) . '</td></tr>';
        echo '<tr><th>Entitlement whitelist</th><td>' . esc_html(defined('VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS') && is_array(VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS) ? 'Configured' : 'Not configured') . '</td></tr>';
        echo '<tr><th>LearnDash group function</th><td>' . esc_html(function_exists('ld_update_group_access') ? 'Available' : 'Missing') . '</td></tr>';
        echo '<tr><th>LearnDash course function</th><td>' . esc_html(function_exists('ld_update_course_access') ? 'Available' : 'Missing') . '</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Recent Grants and Revocations</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>Source Order</th><th>Email</th><th>User</th><th>Entitlement</th><th>Operation</th><th>Status</th><th>Updated GMT</th></tr></thead><tbody>';

        if (!$rows) {
            echo '<tr><td colspan="8">No bridge grants yet.</td></tr>';
        }

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $row->id) . '</td>';
            echo '<td>' . esc_html((string) $row->source_site) . ' #' . esc_html((string) $row->source_order_id) . '</td>';
            echo '<td>' . esc_html((string) $row->customer_email) . '</td>';
            echo '<td>' . ($row->user_id ? '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . absint($row->user_id))) . '">#' . esc_html((string) $row->user_id) . '</a>' : '-') . '</td>';
            echo '<td>' . esc_html((string) $row->entitlement_type) . ' #' . esc_html((string) $row->entitlement_id) . '<br><small>' . esc_html((string) $row->access_label) . '</small></td>';
            echo '<td>' . esc_html((string) $row->operation) . '</td>';
            echo '<td><strong>' . esc_html((string) $row->status) . '</strong><br><small>' . esc_html(wp_trim_words((string) $row->last_error, 18)) . '</small></td>';
            echo '<td>' . esc_html((string) $row->updated_at_gmt) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
