<?php

declare(strict_types=1);

namespace VGBridgeTests\Integration;

use Brain\Monkey\Functions;
use Mockery;
use VGCB_Sender_Order_Handler;
use VGCB_Sender_Outbox;
use VGBridgeTests\Support\TestCase;

final class SenderRefundFlowTest extends TestCase
{
    /** @var array<int, array{payload: array, direction: string}> */
    private array $capturedInserts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->defineBridgeConstants();
        $this->capturedInserts = [];

        Functions\when('get_post_meta')->alias(function (int $id, string $key, bool $single = false) {
            $meta = $GLOBALS['vgcb_test_post_meta'][$id] ?? [];

            return $meta[$key] ?? '';
        });
    }

    public function test_partial_refund_does_not_create_revoke(): void
    {
        $order = new \WC_Order(200, true, [], 100.00, 50.00);

        Functions\expect('wc_get_order')->once()->with(200)->andReturn($order);

        $outbox = Mockery::mock(VGCB_Sender_Outbox::class);
        $outbox->shouldNotReceive('insert_payload');
        $outbox->shouldNotReceive('mark_skipped_pending_for_order');

        (new VGCB_Sender_Order_Handler($outbox))->handle_order_refunded(200, 1);
    }

    public function test_full_refund_creates_revoke_only_after_successful_grant_exists(): void
    {
        $grantPayload = $this->fixture('grant-payload.json');
        $order = new \WC_Order(201, true, [], 100.00, 100.00);

        Functions\expect('wc_get_order')->once()->with(201)->andReturn($order);

        $sentGrant = (object) [
            'id' => 1,
            'payload_json' => wp_json_encode($grantPayload),
            'direction' => VGCB_Sender_Outbox::DIRECTION_GRANT,
            'status' => VGCB_Sender_Outbox::STATUS_SENT,
        ];

        $outbox = Mockery::mock(VGCB_Sender_Outbox::class);
        $outbox->shouldReceive('mark_skipped_pending_for_order')->once()->with(201, Mockery::type('string'));
        $outbox->shouldReceive('get_grants_for_order')->once()->with(201)->andReturn([$sentGrant]);
        $outbox->shouldReceive('insert_payload')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                $this->capturedInserts[] = ['payload' => $payload, 'direction' => 'revoke'];

                return $payload['event'] === 'revoke_access'
                    && isset($payload['refund']['full_refund'])
                    && $payload['refund']['full_refund'] === true;
            }), VGCB_Sender_Outbox::DIRECTION_REVOKE)
            ->andReturn(2);
        $outbox->shouldReceive('schedule_job')->once()->with(2);

        (new VGCB_Sender_Order_Handler($outbox))->handle_order_refunded(201, 1);

        $this->assertCount(1, $this->capturedInserts);
        $this->assertSame('revoke_access', $this->capturedInserts[0]['payload']['event']);
    }

    public function test_full_refund_before_grant_marks_pending_grants_as_skipped(): void
    {
        $order = new \WC_Order(202, true, [], 100.00, 100.00);

        Functions\expect('wc_get_order')->once()->with(202)->andReturn($order);

        $outbox = Mockery::mock(VGCB_Sender_Outbox::class);
        $outbox->shouldReceive('mark_skipped_pending_for_order')->once()->with(202, Mockery::type('string'));
        $outbox->shouldReceive('get_grants_for_order')->once()->with(202)->andReturn([]);
        $outbox->shouldNotReceive('insert_payload');

        (new VGCB_Sender_Order_Handler($outbox))->handle_order_refunded(202, 1);
    }
}
