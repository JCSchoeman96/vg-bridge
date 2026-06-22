<?php

declare(strict_types=1);

namespace VGBridgeTests\Unit;

use VGCB_Receiver_Payload_Validator;
use VGBridgeTests\Support\TestCase;
use WP_Error;

/**
 * @runTestsInSeparateProcesses
 */
final class ReceiverWhitelistTest extends TestCase
{
  private VGCB_Receiver_Payload_Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new VGCB_Receiver_Payload_Validator();
    }

    public function test_empty_whitelist_allows_any_valid_entitlement(): void
    {
        $result = $this->validator->validate($this->fixture('grant-payload.json'));

        $this->assertTrue($result);
    }

    /**
     * @preserveGlobalState disabled
     */
    public function test_whitelisted_learndash_group_id_is_accepted(): void
    {
        define('VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS', [
            'learndash_group' => [22222],
            'learndash_course' => [],
        ]);

        $result = $this->validator->validate($this->fixture('grant-payload.json'));

        $this->assertTrue($result);
    }

    /**
     * @preserveGlobalState disabled
     */
    public function test_non_whitelisted_learndash_group_id_is_rejected(): void
    {
        define('VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS', [
            'learndash_group' => [11111],
            'learndash_course' => [],
        ]);

        $result = $this->validator->validate($this->fixture('grant-payload.json'));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_entitlement_not_allowed', $result->get_error_code());
    }

    /**
     * @preserveGlobalState disabled
     */
    public function test_whitelisted_learndash_course_id_is_accepted(): void
    {
        define('VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS', [
            'learndash_group' => [],
            'learndash_course' => [33333],
        ]);

        $payload = $this->fixture('grant-payload.json');
        $payload['entitlement']['type'] = 'learndash_course';
        $payload['entitlement']['id'] = 33333;

        $result = $this->validator->validate($payload);

        $this->assertTrue($result);
    }

    /**
     * @preserveGlobalState disabled
     */
    public function test_non_whitelisted_learndash_course_id_is_rejected(): void
    {
        define('VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS', [
            'learndash_group' => [],
            'learndash_course' => [11111],
        ]);

        $payload = $this->fixture('grant-payload.json');
        $payload['entitlement']['type'] = 'learndash_course';
        $payload['entitlement']['id'] = 33333;

        $result = $this->validator->validate($payload);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_entitlement_not_allowed', $result->get_error_code());
    }
}
