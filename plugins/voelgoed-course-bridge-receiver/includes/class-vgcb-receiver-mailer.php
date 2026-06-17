<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Mailer
{
    public function send_access_email(WP_User $user, array $payload, bool $is_new_user, ?string $password_reset_url): void
    {
        $email = $user->user_email;
        if (!is_email($email)) {
            return;
        }

        $access_label = sanitize_text_field((string) ($payload['entitlement']['label'] ?? 'jou kursus'));
        $login_url = defined('VG_COURSE_BRIDGE_LOGIN_URL') ? esc_url_raw((string) VG_COURSE_BRIDGE_LOGIN_URL) : 'https://leer.voelgoed.co.za/my-rekening/';
        $first_name = trim((string) get_user_meta($user->ID, 'first_name', true));
        $greeting_name = $first_name !== '' ? $first_name : $user->display_name;

        $subject = sprintf('Jou toegang tot %s is gereed', $access_label);

        $message_lines = [];
        $message_lines[] = 'Hallo ' . $greeting_name;
        $message_lines[] = '';
        $message_lines[] = 'Goeie nuus! Jou toegang tot ' . $access_label . ' is nou geaktiveer op Voelgoed Leer.';
        $message_lines[] = '';
        $message_lines[] = 'Meld hier aan:';
        $message_lines[] = $login_url;
        $message_lines[] = '';

        if ($is_new_user && $password_reset_url) {
            $message_lines[] = 'Omdat hierdie rekening nuut geskep is, stel asseblief eers jou wagwoord hier:';
            $message_lines[] = $password_reset_url;
            $message_lines[] = '';
        }

        $message_lines[] = 'Groete';
        $message_lines[] = 'Die Voelgoed-span';

        wp_mail($email, $subject, implode("\n", $message_lines));
    }

    public function send_admin_failure(string $error, array $payload): void
    {
        $admin_email = defined('VG_COURSE_BRIDGE_ADMIN_EMAIL') ? (string) VG_COURSE_BRIDGE_ADMIN_EMAIL : 'online@carpediem.co.za';
        $admin_email = sanitize_email($admin_email);

        if ($admin_email === '') {
            return;
        }

        $source_order_id = absint($payload['source_order_id'] ?? 0);
        $customer_email = sanitize_email((string) ($payload['customer']['email'] ?? ''));

        $subject = 'Voelgoed Course Bridge receiver failure';
        $message = "A LearnDash access request failed on leer.voelgoed.co.za.\n\nSource order ID: {$source_order_id}\nCustomer email: {$customer_email}\nError: {$error}\n\nPlease check Tools > Course Bridge Grants.";

        wp_mail($admin_email, $subject, $message);
    }
}
