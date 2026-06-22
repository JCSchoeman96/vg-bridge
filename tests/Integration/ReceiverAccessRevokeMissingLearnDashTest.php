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

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ReceiverAccessRevokeMissingLearnDashTest extends TestCase
{
    protected bool $loadLearnDashStubs = false;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('get_user_meta')->justReturn('');
        Functions\when('update_user_meta')->justReturn(true);
        Functions\when('wp_update_user')->justReturn(1);
        Functions\when('wp_mail')->justReturn(true);
    }

    public function test_missing_learndash_function_fails_cleanly(): void
    {
        $this->assertFalse(function_exists('ld_update_group_access'));

        $log = new VGCB_Receiver_Log();
        $mailer = new VGCB_Receiver_Mailer();
        $validator = new VGCB_Receiver_Payload_Validator();
        $access = new VGCB_Receiver_Access($log, $mailer, $validator);

        $existing = new WP_User(42, 'anna@example.com', 'anna');
        Functions\when('get_user_by')->alias(function (string $field, mixed $value) use ($existing): WP_User|false {
            if ($field === 'email' && $value === 'anna@example.com') {
                return $existing;
            }

            return false;
        });

        $result = $access->process_payload($this->fixture('revoke-payload.json'));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ld_update_group_access', $result['message']);
    }
}
