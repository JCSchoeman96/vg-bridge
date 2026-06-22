<?php

declare(strict_types=1);

namespace VGBridgeTests\Support;

use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    protected bool $loadLearnDashStubs = true;

    protected function setUp(): void
    {
        parent::setUp();

        Monkey\setUp();

        $GLOBALS['vgcb_test_users'] = [];
        $GLOBALS['vgcb_test_group_access_calls'] = [];
        $GLOBALS['vgcb_test_course_access_calls'] = [];
        $GLOBALS['vgcb_test_mail'] = [];
        $GLOBALS['vgcb_test_order_notes'] = [];
        $GLOBALS['vgcb_test_wp_update_user_calls'] = [];
        $GLOBALS['vgcb_test_options'] = [
            'admin_email' => 'online@carpediem.co.za',
        ];
        $GLOBALS['vgcb_test_roles'] = [
            'customer' => true,
            'subscriber' => true,
        ];
        $GLOBALS['vgcb_test_post_meta'] = [];
        $GLOBALS['vgcb_test_next_user_id'] = 100;
        $GLOBALS['wpdb'] = new FakeWpdb();

        if ($this->loadLearnDashStubs) {
            require_once dirname(__DIR__) . '/Support/LearnDashStubs.php';
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();

        parent::tearDown();
    }

    protected function fixture(string $name): array
    {
        $path = dirname(__DIR__) . '/fixtures/' . $name;

        $json = file_get_contents($path);
        $this->assertIsString($json, "Fixture could not be read: {$name}");

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, "Fixture is invalid JSON: {$name}");

        return $decoded;
    }

    protected function defineBridgeConstants(): void
    {
        if (!defined('VG_COURSE_BRIDGE_ALLOWED_SOURCE')) {
            define('VG_COURSE_BRIDGE_ALLOWED_SOURCE', 'winkel.voelgoed.co.za');
        }
        if (!defined('VG_COURSE_BRIDGE_SHARED_SECRET')) {
            define('VG_COURSE_BRIDGE_SHARED_SECRET', 'test-secret-not-for-production');
        }
        if (!defined('VG_COURSE_BRIDGE_SOURCE_SITE')) {
            define('VG_COURSE_BRIDGE_SOURCE_SITE', 'winkel.voelgoed.co.za');
        }
    }
}
