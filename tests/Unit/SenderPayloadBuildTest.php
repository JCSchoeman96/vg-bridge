<?php

declare(strict_types=1);

namespace VGBridgeTests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use VGCB_Sender_Order_Handler;
use VGCB_Sender_Outbox;
use VGBridgeTests\Support\TestCase;

final class SenderPayloadBuildTest extends TestCase
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
        Functions\when('is_a')->alias(static fn($object, string $class): bool => $object instanceof $class);
    }

    public function test_payload_includes_required_fields(): void
    {
        $product = new \WC_Product(11111, 'Physical Product + 21-Day Course Bundle');
        $item = new \WC_Order_Item_Product(78910, $product);
        $order = new \WC_Order(123456, true, [$item]);

        $GLOBALS['vgcb_test_post_meta'][11111] = [
            '_vgcb_enable_course_bridge' => 'yes',
            '_vgcb_entitlement_type' => 'learndash_group',
            '_vgcb_entitlement_id' => '22222',
            '_vgcb_access_label' => '21-Day Course',
        ];

        Functions\expect('wc_get_order')->once()->with(123456)->andReturn($order);

        $outbox = Mockery::mock(VGCB_Sender_Outbox::class);
        $outbox->shouldReceive('insert_payload')
            ->once()
            ->andReturnUsing(function (array $payload, string $direction): int {
                $this->capturedInserts[] = ['payload' => $payload, 'direction' => $direction];

                return 1;
            });
        $outbox->shouldReceive('schedule_job')->once()->with(1);

        $handler = new VGCB_Sender_Order_Handler($outbox);
        $handler->handle_payment_complete(123456);

        $this->assertCount(1, $this->capturedInserts);
        $payload = $this->capturedInserts[0]['payload'];

        $this->assertSame('winkel.voelgoed.co.za', $payload['source_site']);
        $this->assertSame(123456, $payload['source_order_id']);
        $this->assertSame(78910, $payload['source_order_item_id']);
        $this->assertSame('anna@example.com', $payload['customer']['email']);
        $this->assertSame('paystack', $payload['payment']['gateway']);
        $this->assertSame('learndash_group', $payload['entitlement']['type']);
        $this->assertSame(22222, $payload['entitlement']['id']);
    }
}
