<?php

declare(strict_types=1);

namespace VGBridgeTests\Unit;

use VGCB_Receiver_Authenticator;
use VGCB_Receiver_Log;
use VGBridgeTests\Support\TestCase;
use WP_Error;
use WP_REST_Request;

final class ReceiverNonceReplayTest extends TestCase
{
    private VGCB_Receiver_Authenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defineBridgeConstants();
        $log = new VGCB_Receiver_Log();
        $this->authenticator = new VGCB_Receiver_Authenticator($log);
    }

    public function test_valid_request_consumes_nonce_and_replay_is_rejected(): void
    {
        $payload = $this->fixture('grant-payload.json');
        $body = wp_json_encode($payload);
        $this->assertIsString($body);
        $timestamp = gmdate('Y-m-d H:i:s');
        $nonce = 'unique-nonce-12345';
        $signature = hash_hmac(
            'sha256',
            $timestamp . "\n" . $nonce . "\n" . $body,
            VG_COURSE_BRIDGE_SHARED_SECRET
        );

        $request = new WP_REST_Request($body, [
            'x-vg-bridge-source' => VG_COURSE_BRIDGE_ALLOWED_SOURCE,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => $signature,
        ]);

        $this->assertTrue($this->authenticator->authenticate($request));
        $this->assertSame(1, $GLOBALS['wpdb']->nonceCount());

        $replay = $this->authenticator->authenticate($request);
        $this->assertInstanceOf(WP_Error::class, $replay);
        $this->assertSame('vgcb_replayed_nonce', $replay->get_error_code());
    }
}
