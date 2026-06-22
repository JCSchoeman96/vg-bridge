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

final class ReceiverAccessRevokeTest extends TestCase
{
    private VGCB_Receiver_Access $access;

    protected function setUp(): void
    {
        parent::setUp();

        $log = new VGCB_Receiver_Log();
        $mailer = new VGCB_Receiver_Mailer();
        $validator = new VGCB_Receiver_Payload_Validator();
        $this->access = new VGCB_Receiver_Access($log, $mailer, $validator);

        Functions\when('get_user_meta')->justReturn('');
        Functions\when('update_user_meta')->justReturn(true);
        Functions\when('wp_update_user')->justReturn(1);
        Functions\when('wp_mail')->justReturn(true);
    }

    public function test_learndash_group_revoke_calls_ld_update_group_access_with_remove_true(): void
    {
        $existing = new WP_User(42, 'anna@example.com', 'anna');
        Functions\expect('get_user_by')->once()->with('email', 'anna@example.com')->andReturn($existing);

        $this->access->process_payload($this->fixture('revoke-payload.json'));

        $this->assertCount(1, $GLOBALS['vgcb_test_group_access_calls']);
        $this->assertSame([
            'user_id' => 42,
            'group_id' => 22222,
            'remove' => true,
        ], $GLOBALS['vgcb_test_group_access_calls'][0]);
    }

    public function test_learndash_course_revoke_calls_ld_update_course_access_with_remove_true(): void
    {
        $payload = $this->fixture('revoke-payload.json');
        $payload['entitlement']['type'] = 'learndash_course';
        $payload['entitlement']['id'] = 33333;

        $existing = new WP_User(42, 'anna@example.com', 'anna');
        Functions\expect('get_user_by')->once()->with('email', 'anna@example.com')->andReturn($existing);

        $this->access->process_payload($payload);

        $this->assertCount(1, $GLOBALS['vgcb_test_course_access_calls']);
        $this->assertSame([
            'user_id' => 42,
            'course_id' => 33333,
            'remove' => true,
        ], $GLOBALS['vgcb_test_course_access_calls'][0]);
    }
}
