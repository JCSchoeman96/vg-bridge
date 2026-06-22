<?php

declare(strict_types=1);

namespace VGBridgeTests\Unit;

use VGCB_Receiver_Authenticator;
use VGCB_Receiver_Log;
use VGBridgeTests\Support\TestCase;
use WP_Error;
use WP_REST_Request;

final class ReceiverAuthTest extends TestCase
{
    private VGCB_Receiver_Authenticator $authenticator;

  private VGCB_Receiver_Log $log;

    private const SECRET = 'test-secret-not-for-production';

    private const SOURCE = 'winkel.voelgoed.co.za';

    protected function setUp(): void
    {
        parent::setUp();
        $this->defineBridgeConstants();
        $this->log = new VGCB_Receiver_Log();
        $this->authenticator = new VGCB_Receiver_Authenticator($this->log);
    }

    public function test_valid_signed_request_is_accepted(): void
    {
        $request = $this->signedRequest($this->fixture('grant-payload.json'));

        $result = $this->authenticator->authenticate($request);

        $this->assertTrue($result);
    }

    public function test_missing_source_is_rejected(): void
    {
        $body = wp_json_encode($this->fixture('grant-payload.json'));
        $this->assertIsString($body);
        $timestamp = gmdate('Y-m-d H:i:s');
        $nonce = 'nonce-missing-source';
        $request = new WP_REST_Request($body, [
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => $this->sign($timestamp, $nonce, $body, self::SECRET),
        ]);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_source', $result->get_error_code());
    }

    public function test_wrong_source_is_rejected(): void
    {
        $request = $this->signedRequest($this->fixture('grant-payload.json'), source: 'evil.example.com');

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_source', $result->get_error_code());
    }

    public function test_missing_timestamp_is_rejected(): void
    {
        $body = wp_json_encode($this->fixture('grant-payload.json'));
        $this->assertIsString($body);
        $nonce = 'nonce-missing-ts';
        $request = new WP_REST_Request($body, [
            'x-vg-bridge-source' => self::SOURCE,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => 'deadbeef',
        ]);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_missing_headers', $result->get_error_code());
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $timestamp = gmdate('Y-m-d H:i:s', time() - 600);
        $request = $this->signedRequest($this->fixture('grant-payload.json'), timestamp: $timestamp);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_stale_timestamp', $result->get_error_code());
    }

    public function test_missing_nonce_is_rejected(): void
    {
        $body = wp_json_encode($this->fixture('grant-payload.json'));
        $this->assertIsString($body);
        $timestamp = gmdate('Y-m-d H:i:s');
        $request = new WP_REST_Request($body, [
            'x-vg-bridge-source' => self::SOURCE,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-signature' => 'deadbeef',
        ]);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_missing_headers', $result->get_error_code());
    }

    public function test_bad_signature_is_rejected(): void
    {
        $body = wp_json_encode($this->fixture('grant-payload.json'));
        $this->assertIsString($body);
        $timestamp = gmdate('Y-m-d H:i:s');
        $nonce = 'nonce-bad-sig';
        $request = new WP_REST_Request($body, [
            'x-vg-bridge-source' => self::SOURCE,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => 'not-a-valid-signature',
        ]);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_signature', $result->get_error_code());
    }

    public function test_tampered_body_is_rejected(): void
    {
        $body = wp_json_encode($this->fixture('grant-payload.json'));
        $this->assertIsString($body);
        $timestamp = gmdate('Y-m-d H:i:s');
        $nonce = 'nonce-tampered';
        $tamperedBody = wp_json_encode($this->fixture('tampered-payload.json'));
        $this->assertIsString($tamperedBody);
        $request = new WP_REST_Request($tamperedBody, [
            'x-vg-bridge-source' => self::SOURCE,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => $this->sign($timestamp, $nonce, $body, self::SECRET),
        ]);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_bad_signature', $result->get_error_code());
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $request = $this->signedRequest($this->fixture('grant-payload.json'), nonce: 'replay-nonce');

        $this->assertTrue($this->authenticator->authenticate($request));
        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('vgcb_replayed_nonce', $result->get_error_code());
    }

    public function test_nonce_is_not_consumed_when_signature_is_invalid(): void
    {
        $body = wp_json_encode($this->fixture('grant-payload.json'));
        $this->assertIsString($body);
        $timestamp = gmdate('Y-m-d H:i:s');
        $nonce = 'nonce-not-burned';
        $badRequest = new WP_REST_Request($body, [
            'x-vg-bridge-source' => self::SOURCE,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => 'invalid-signature',
        ]);

        $this->assertInstanceOf(WP_Error::class, $this->authenticator->authenticate($badRequest));
        $this->assertSame(0, $GLOBALS['wpdb']->nonceCount());

        $goodRequest = new WP_REST_Request($body, [
            'x-vg-bridge-source' => self::SOURCE,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => $this->sign($timestamp, $nonce, $body, self::SECRET),
        ]);

        $this->assertTrue($this->authenticator->authenticate($goodRequest));
    }

    private function signedRequest(
        array $payload,
        ?string $timestamp = null,
        ?string $nonce = null,
        ?string $source = null
    ): WP_REST_Request {
        $body = wp_json_encode($payload);
        $this->assertIsString($body);
        $timestamp ??= gmdate('Y-m-d H:i:s');
        $nonce ??= wp_generate_uuid4();
        $source ??= self::SOURCE;

        return new WP_REST_Request($body, [
            'x-vg-bridge-source' => $source,
            'x-vg-bridge-timestamp' => $timestamp,
            'x-vg-bridge-nonce' => $nonce,
            'x-vg-bridge-signature' => $this->sign($timestamp, $nonce, $body, self::SECRET),
        ]);
    }

    private function sign(string $timestamp, string $nonce, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);
    }
}
