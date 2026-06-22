<?php

declare(strict_types=1);

namespace VGBridgeTests\Unit;

use VGCB_Receiver_Payload_Validator;
use VGBridgeTests\Support\TestCase;
use WP_Error;

final class ReceiverPayloadValidationTest extends TestCase
{
    private VGCB_Receiver_Payload_Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new VGCB_Receiver_Payload_Validator();
    }

    public function test_grant_access_is_accepted(): void
    {
        $result = $this->validator->validate($this->fixture('grant-payload.json'));

        $this->assertTrue($result);
    }

    public function test_revoke_access_is_accepted(): void
    {
        $result = $this->validator->validate($this->fixture('revoke-payload.json'));

        $this->assertTrue($result);
    }

    public function test_unknown_event_is_rejected(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $payload['event'] = 'unknown_event';

        $result = $this->validator->validate($payload);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_event', $result->get_error_code());
    }

    public function test_missing_source_order_id_is_rejected(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $payload['source_order_id'] = 0;

        $result = $this->validator->validate($payload);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_source_order', $result->get_error_code());
    }

    public function test_invalid_email_is_rejected(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $payload['customer']['email'] = 'not-an-email';

        $result = $this->validator->validate($payload);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_email', $result->get_error_code());
    }

    public function test_unsupported_entitlement_type_is_rejected(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $payload['entitlement']['type'] = 'bad_type';

        $result = $this->validator->validate($payload);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_entitlement', $result->get_error_code());
    }

    public function test_entitlement_id_zero_is_rejected(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $payload['entitlement']['id'] = 0;

        $result = $this->validator->validate($payload);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_entitlement', $result->get_error_code());
    }
}
