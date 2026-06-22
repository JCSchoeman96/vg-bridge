<?php

declare(strict_types=1);

namespace VGBridgeTests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use VGCB_Receiver_Access;
use VGCB_Receiver_Log;
use VGCB_Receiver_Mailer;
use VGCB_Receiver_Payload_Validator;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use VGBridgeTests\Support\FakeWpdb;
use WP_User;

final class ReceiverAccessRevokeMissingLearnDashTest extends PhpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $GLOBALS['vgcb_test_users'] = [];
        $GLOBALS['vgcb_test_group_access_calls'] = [];
        $GLOBALS['vgcb_test_course_access_calls'] = [];
        $GLOBALS['wpdb'] = new FakeWpdb();

        Functions\when('get_user_meta')->justReturn('');
        Functions\when('update_user_meta')->justReturn(true);
        Functions\when('wp_update_user')->justReturn(1);
        Functions\when('wp_mail')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_missing_learndash_function_fails_cleanly(): void
    {
        $this->assertFalse(function_exists('ld_update_group_access'));

        $log = new VGCB_Receiver_Log();
        $mailer = new VGCB_Receiver_Mailer();
        $validator = new VGCB_Receiver_Payload_Validator();
        $access = new VGCB_Receiver_Access($log, $mailer, $validator);

        $existing = new WP_User(42, 'anna@example.com', 'anna');
        Functions\expect('get_user_by')->once()->with('email', 'anna@example.com')->andReturn($existing);

        $json = file_get_contents(dirname(__DIR__) . '/fixtures/revoke-payload.json');
        $this->assertIsString($json);
        $payload = json_decode($json, true);
        $this->assertIsArray($payload);

        $result = $access->process_payload($payload);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ld_update_group_access', $result['message']);
    }
}
