<?php

declare(strict_types=1);

namespace VGBridgeTests\Integration;

use Brain\Monkey\Functions;
use VGCB_Receiver_Access;
use VGCB_Receiver_Log;
use VGCB_Receiver_Mailer;
use VGCB_Receiver_Payload_Validator;
use VGBridgeTests\Support\TestCase;
use WP_User;

final class ReceiverAccessGrantTest extends TestCase
{
    private VGCB_Receiver_Access $access;

    protected function setUp(): void
    {
        parent::setUp();

        $log = new VGCB_Receiver_Log();
        $mailer = new VGCB_Receiver_Mailer();
        $validator = new VGCB_Receiver_Payload_Validator();
        $this->access = new VGCB_Receiver_Access($log, $mailer, $validator);

        Functions\when('wp_mail')->alias(function (string $to, string $subject, string $message): bool {
            $GLOBALS['vgcb_test_mail'][] = compact('to', 'subject', 'message');

            return true;
        });
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('update_user_meta')->justReturn(true);
        Functions\when('wp_update_user')->alias(function (array $userdata): int {
            $GLOBALS['vgcb_test_wp_update_user_calls'][] = $userdata;

            return (int) ($userdata['ID'] ?? 1);
        });
        Functions\when('wp_generate_password')->justReturn('generated-password');
        Functions\when('get_password_reset_key')->justReturn('reset-key-abc');
    }

    public function test_new_user_is_created_from_billing_email(): void
    {
        $this->stubGetUserByForNewUser(100);

        Functions\expect('wp_create_user')->once()->andReturnUsing(function (): int {
            $id = $GLOBALS['vgcb_test_next_user_id']++;
            $GLOBALS['vgcb_test_users'][$id] = [
                'ID' => $id,
                'user_email' => 'anna@example.com',
                'user_login' => 'anna',
            ];

            return $id;
        });

        $result = $this->access->process_payload($this->fixture('grant-payload.json'));

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['user_id']);
    }

    public function test_existing_user_is_reused(): void
    {
        $existing = new WP_User(42, 'anna@example.com', 'anna');
        $this->stubGetUserByForExistingUser($existing);

        Functions\expect('wp_create_user')->never();

        $result = $this->access->process_payload($this->fixture('grant-payload.json'));

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['user_id']);
    }

    public function test_new_user_gets_customer_role_when_available(): void
    {
        $this->stubGetUserByForNewUser(50);

        Functions\expect('wp_create_user')->once()->andReturn(50);

        $this->access->process_payload($this->fixture('grant-payload.json'));

        $roles = array_filter(array_map(
            static fn(array $call): ?string => $call['role'] ?? null,
            $GLOBALS['vgcb_test_wp_update_user_calls']
        ));

        $this->assertContains('customer', $roles);
    }

    public function test_learndash_group_grant_calls_ld_update_group_access(): void
    {
        $existing = new WP_User(42, 'anna@example.com', 'anna');
        $this->stubGetUserByForExistingUser($existing);

        $this->access->process_payload($this->fixture('grant-payload.json'));

        $this->assertCount(1, $GLOBALS['vgcb_test_group_access_calls']);
        $this->assertSame([
            'user_id' => 42,
            'group_id' => 22222,
            'remove' => false,
        ], $GLOBALS['vgcb_test_group_access_calls'][0]);
    }

    public function test_learndash_course_grant_calls_ld_update_course_access(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $payload['entitlement']['type'] = 'learndash_course';
        $payload['entitlement']['id'] = 33333;

        $existing = new WP_User(42, 'anna@example.com', 'anna');
        $this->stubGetUserByForExistingUser($existing);

        $this->access->process_payload($payload);

        $this->assertCount(1, $GLOBALS['vgcb_test_course_access_calls']);
        $this->assertSame([
            'user_id' => 42,
            'course_id' => 33333,
            'remove' => false,
        ], $GLOBALS['vgcb_test_course_access_calls'][0]);
    }

    public function test_access_email_is_sent_for_grant(): void
    {
        $existing = new WP_User(42, 'anna@example.com', 'anna');
        $this->stubGetUserByForExistingUser($existing);

        $this->access->process_payload($this->fixture('grant-payload.json'));

        $this->assertNotEmpty($GLOBALS['vgcb_test_mail']);
        $this->assertSame('anna@example.com', $GLOBALS['vgcb_test_mail'][0]['to']);
    }

    private function stubGetUserByForNewUser(int $newUserId): void
    {
        Functions\when('get_user_by')->alias(function (string $field, mixed $value) use ($newUserId): WP_User|false {
            if ($field === 'email' && $value === 'anna@example.com') {
                return false;
            }

            if ($field === 'id' && (int) $value === $newUserId) {
                return new WP_User($newUserId, 'anna@example.com', 'anna');
            }

            return false;
        });
    }

    private function stubGetUserByForExistingUser(WP_User $existing): void
    {
        Functions\when('get_user_by')->alias(function (string $field, mixed $value) use ($existing): WP_User|false {
            if ($field === 'email' && $value === 'anna@example.com') {
                return $existing;
            }

            if ($field === 'id' && (int) $value === $existing->ID) {
                return $existing;
            }

            return false;
        });
    }
}
