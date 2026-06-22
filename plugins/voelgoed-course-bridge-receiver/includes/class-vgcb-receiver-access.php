<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Access
{
    public function __construct(
        private readonly VGCB_Receiver_Log $log,
        private readonly VGCB_Receiver_Mailer $mailer,
        private readonly VGCB_Receiver_Payload_Validator $validator
    ) {
    }

    public function process_payload(array $payload): array
    {
        $validation = $this->validator->validate($payload);
        if (is_wp_error($validation)) {
            return [
                'success' => false,
                'message' => $validation->get_error_message(),
                'status' => 400,
            ];
        }

        $operation = ($payload['event'] ?? '') === 'revoke_access'
            ? VGCB_Receiver_Log::OPERATION_REVOKE
            : VGCB_Receiver_Log::OPERATION_GRANT;

        $insert = $this->log->insert_or_get_existing($payload, $operation);
        $grant_id = (int) $insert['id'];
        $duplicate = (bool) $insert['duplicate'];

        if ($grant_id <= 0) {
            return [
                'success' => false,
                'message' => 'Could not create or read receiver grant log entry.',
                'status' => 500,
            ];
        }

        if ($duplicate) {
            $existing = $this->log->get_grant($grant_id);
            $successful_statuses = [VGCB_Receiver_Log::STATUS_GRANTED, VGCB_Receiver_Log::STATUS_REVOKED];

            if ($existing && in_array((string) $existing->status, $successful_statuses, true)) {
                $this->log->mark_duplicate_touched($grant_id);

                return [
                    'success' => true,
                    'duplicate' => true,
                    'grant_id' => $grant_id,
                    'user_id' => (int) $existing->user_id,
                    'message' => 'Duplicate operation already processed successfully.',
                    'status' => 200,
                ];
            }
        }

        try {
            $result = $this->grant_or_revoke($payload, $operation);
            if (!$result['success']) {
                $this->log->mark_failed($grant_id, $result['message']);
                $this->mailer->send_admin_failure($result['message'], $payload);
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'grant_id' => $grant_id,
                    'status' => 500,
                ];
            }

            $this->log->mark_success($grant_id, (int) $result['user_id'], $operation);

            if ($operation === VGCB_Receiver_Log::OPERATION_GRANT) {
                $user = get_user_by('id', (int) $result['user_id']);
                if ($user instanceof WP_User) {
                    $this->mailer->send_access_email($user, $payload, (bool) $result['is_new_user'], $result['password_reset_url']);
                }
            }

            return [
                'success' => true,
                'duplicate' => false,
                'grant_id' => $grant_id,
                'user_id' => (int) $result['user_id'],
                'message' => $operation === VGCB_Receiver_Log::OPERATION_REVOKE ? 'Access revoked.' : 'Access granted.',
                'status' => 200,
            ];
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            $this->log->mark_failed($grant_id, $message);
            $this->mailer->send_admin_failure($message, $payload);

            return [
                'success' => false,
                'message' => $message,
                'grant_id' => $grant_id,
                'status' => 500,
            ];
        }
    }

    private function grant_or_revoke(array $payload, string $operation): array
    {
        $email = sanitize_email((string) ($payload['customer']['email'] ?? ''));
        $first_name = sanitize_text_field((string) ($payload['customer']['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($payload['customer']['last_name'] ?? ''));
        $phone = sanitize_text_field((string) ($payload['customer']['phone'] ?? ''));
        $type = sanitize_key((string) ($payload['entitlement']['type'] ?? ''));
        $entitlement_id = absint($payload['entitlement']['id'] ?? 0);

        $user_result = $this->find_or_create_user($email, $first_name, $last_name, $phone);
        if (!$user_result['success']) {
            return $user_result;
        }

        $user_id = (int) $user_result['user_id'];
        $remove = $operation === VGCB_Receiver_Log::OPERATION_REVOKE;

        if ($type === 'learndash_group') {
            if (!function_exists('ld_update_group_access')) {
                return ['success' => false, 'message' => 'LearnDash function ld_update_group_access() is not available.'];
            }

            ld_update_group_access($user_id, $entitlement_id, $remove);
        } elseif ($type === 'learndash_course') {
            if (!function_exists('ld_update_course_access')) {
                return ['success' => false, 'message' => 'LearnDash function ld_update_course_access() is not available.'];
            }

            ld_update_course_access($user_id, $entitlement_id, $remove);
        } else {
            return ['success' => false, 'message' => 'Unsupported entitlement type.'];
        }

        return [
            'success' => true,
            'user_id' => $user_id,
            'is_new_user' => (bool) $user_result['is_new_user'],
            'password_reset_url' => $user_result['password_reset_url'],
        ];
    }

    private function find_or_create_user(string $email, string $first_name, string $last_name, string $phone): array
    {
        if (!is_email($email)) {
            return ['success' => false, 'message' => 'Invalid customer email address.'];
        }

        $user = get_user_by('email', $email);
        if ($user instanceof WP_User) {
            $this->update_user_profile($user->ID, $first_name, $last_name, $phone, false);

            return [
                'success' => true,
                'user_id' => $user->ID,
                'is_new_user' => false,
                'password_reset_url' => null,
            ];
        }

        $username = $this->generate_username_from_email($email);
        $password = wp_generate_password(32, true, true);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return ['success' => false, 'message' => $user_id->get_error_message()];
        }

        $this->update_user_profile((int) $user_id, $first_name, $last_name, $phone, true);

        $password_reset_url = $this->create_password_reset_url((int) $user_id);

        return [
            'success' => true,
            'user_id' => (int) $user_id,
            'is_new_user' => true,
            'password_reset_url' => $password_reset_url,
        ];
    }

    private function update_user_profile(int $user_id, string $first_name, string $last_name, string $phone, bool $set_role): void
    {
        $userdata = [
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name) ?: null,
        ];

        if ($set_role) {
            $role = get_role('customer') ? 'customer' : 'subscriber';
            $userdata['role'] = $role;
        }

        wp_update_user(array_filter($userdata, static fn($value) => $value !== null));

        if ($phone !== '') {
            update_user_meta($user_id, 'billing_phone', $phone);
        }
    }

    private function generate_username_from_email(string $email): string
    {
        $base = sanitize_user(current(explode('@', $email)), true);
        if ($base === '') {
            $base = 'leerling';
        }

        $username = $base;
        $counter = 1;

        while (username_exists($username)) {
            $counter++;
            $username = $base . $counter;
        }

        return $username;
    }

    private function create_password_reset_url(int $user_id): ?string
    {
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return null;
        }

        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return null;
        }

        return network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
    }
}
