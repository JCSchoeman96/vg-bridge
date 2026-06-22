<?php

declare(strict_types=1);

namespace VGBridgeTests\Integration;

use Brain\Monkey\Functions;
use Mockery;
use VGCB_Sender_Order_Handler;
use VGCB_Sender_Outbox_Store;
use VGBridgeTests\Support\TestCase;

final class SenderOrderHandlerTest extends TestCase
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

    public function test_unpaid_order_creates_no_outbox_job(): void
    {
        $this->expectNotToPerformAssertions();

        $product = new \WC_Product(11111);
        $item = new \WC_Order_Item_Product(1, $product);
        $order = new \WC_Order(100, false, [$item]);

        Functions\expect('wc_get_order')->once()->with(100)->andReturn($order);

        $outbox = Mockery::mock(VGCB_Sender_Outbox_Store::class);
        $outbox->shouldNotReceive('insert_payload');

        (new VGCB_Sender_Order_Handler($outbox))->handle_payment_complete(100);
    }

    public function test_paid_order_with_no_mapped_product_creates_no_outbox_job(): void
    {
        $this->expectNotToPerformAssertions();

        $product = new \WC_Product(11111);
        $item = new \WC_Order_Item_Product(1, $product);
        $order = new \WC_Order(101, true, [$item]);

        Functions\expect('wc_get_order')->once()->with(101)->andReturn($order);

        $outbox = Mockery::mock(VGCB_Sender_Outbox_Store::class);
        $outbox->shouldNotReceive('insert_payload');

        (new VGCB_Sender_Order_Handler($outbox))->handle_payment_complete(101);
    }

    public function test_paid_order_with_mapped_product_creates_one_grant_job(): void
    {
        $this->mapProduct(11111, 'learndash_group', 22222);
        $order = $this->mappedOrder(102, 11111);

        Functions\expect('wc_get_order')->once()->with(102)->andReturn($order);

        $outbox = $this->spyOutbox();

        (new VGCB_Sender_Order_Handler($outbox))->handle_payment_complete(102);

        $this->assertCount(1, $this->capturedInserts);
        $this->assertSame('grant', $this->capturedInserts[0]['direction']);
        $this->assertSame('grant_access', $this->capturedInserts[0]['payload']['event']);
    }

    public function test_quantity_two_of_same_entitlement_creates_only_one_grant(): void
    {
        $this->mapProduct(11111, 'learndash_group', 22222);
        $product = new \WC_Product(11111);
        $item1 = new \WC_Order_Item_Product(1, $product);
        $item2 = new \WC_Order_Item_Product(2, $product);
        $order = new \WC_Order(103, true, [$item1, $item2]);

        Functions\expect('wc_get_order')->once()->with(103)->andReturn($order);

        $outbox = $this->spyOutbox();

        (new VGCB_Sender_Order_Handler($outbox))->handle_payment_complete(103);

        $this->assertCount(1, $this->capturedInserts);
    }

    public function test_two_different_entitlements_create_two_grant_jobs(): void
    {
        $this->mapProduct(11111, 'learndash_group', 22222);
        $this->mapProduct(22222, 'learndash_course', 33333);

        $item1 = new \WC_Order_Item_Product(1, new \WC_Product(11111));
        $item2 = new \WC_Order_Item_Product(2, new \WC_Product(22222));
        $order = new \WC_Order(104, true, [$item1, $item2]);

        Functions\expect('wc_get_order')->once()->with(104)->andReturn($order);

        $outbox = $this->spyOutbox(2);

        (new VGCB_Sender_Order_Handler($outbox))->handle_payment_complete(104);

        $this->assertCount(2, $this->capturedInserts);
        $types = array_map(static fn(array $row): string => $row['payload']['entitlement']['type'], $this->capturedInserts);
        $this->assertContains('learndash_group', $types);
        $this->assertContains('learndash_course', $types);
    }

    private function mapProduct(int $productId, string $type, int $entitlementId): void
    {
        $GLOBALS['vgcb_test_post_meta'][$productId] = [
            '_vgcb_enable_course_bridge' => 'yes',
            '_vgcb_entitlement_type' => $type,
            '_vgcb_entitlement_id' => (string) $entitlementId,
            '_vgcb_access_label' => 'Test Access',
        ];
    }

    private function mappedOrder(int $orderId, int $productId): \WC_Order
    {
        $product = new \WC_Product($productId);
        $item = new \WC_Order_Item_Product(1, $product);

        return new \WC_Order($orderId, true, [$item]);
    }

    private function spyOutbox(int $expectedSchedules = 1): VGCB_Sender_Outbox_Store
    {
        $outbox = Mockery::mock(VGCB_Sender_Outbox_Store::class);
        $outbox->shouldReceive('insert_payload')
            ->andReturnUsing(function (array $payload, string $direction): int {
                $this->capturedInserts[] = ['payload' => $payload, 'direction' => $direction];

                return count($this->capturedInserts);
            });
        $outbox->shouldReceive('schedule_job')->times($expectedSchedules);

        return $outbox;
    }
}
