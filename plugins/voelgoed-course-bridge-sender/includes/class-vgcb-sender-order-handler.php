<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Sender_Order_Handler
{
    public function __construct(private readonly VGCB_Sender_Outbox_Store $outbox)
    {
    }

    public function hooks(): void
    {
        add_action('woocommerce_payment_complete', [$this, 'handle_payment_complete'], 20, 1);
        add_action('woocommerce_order_status_processing', [$this, 'handle_order_status_processing'], 20, 2);
        add_action('woocommerce_order_refunded', [$this, 'handle_order_refunded'], 20, 2);
        add_action('woocommerce_order_status_refunded', [$this, 'handle_order_status_refunded'], 20, 2);
    }

    public function handle_payment_complete(int $order_id): void
    {
        $this->process_paid_order($order_id, 'woocommerce_payment_complete');
    }

    public function handle_order_status_processing(int $order_id, $order = null): void
    {
        $this->process_paid_order($order_id, 'woocommerce_order_status_processing');
    }

    public function handle_order_refunded(int $order_id, int $refund_id): void
    {
        $this->maybe_process_full_refund($order_id, 'woocommerce_order_refunded');
    }

    public function handle_order_status_refunded(int $order_id, $order = null): void
    {
        $this->maybe_process_full_refund($order_id, 'woocommerce_order_status_refunded');
    }

    private function process_paid_order(int $order_id, string $trigger): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if (!$order->is_paid()) {
            return;
        }

        if ($this->is_order_fully_refunded($order)) {
            $this->outbox->mark_skipped_pending_for_order($order_id, 'Order is fully refunded; grant skipped.');
            return;
        }

        $created_ids = [];
        $seen_entitlements = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $mapping = $this->get_product_mapping($product);
            if (!$mapping) {
                continue;
            }

            $dedupe_key = $mapping['entitlement_type'] . ':' . $mapping['entitlement_id'];
            if (isset($seen_entitlements[$dedupe_key])) {
                continue;
            }
            $seen_entitlements[$dedupe_key] = true;

            $payload = $this->build_payload($order, $item, $product, $mapping, 'grant_access', $trigger);
            $outbox_id = $this->outbox->insert_payload($payload, VGCB_Sender_Outbox_Store::DIRECTION_GRANT);

            if ($outbox_id > 0) {
                $created_ids[] = $outbox_id;
            }
        }

        foreach (array_unique($created_ids) as $outbox_id) {
            $this->outbox->schedule_job((int) $outbox_id);
        }
    }

    private function maybe_process_full_refund(int $order_id, string $trigger): void
    {
        $order = wc_get_order($order_id);
        if (!$order || !$this->is_order_fully_refunded($order)) {
            return;
        }

        $this->outbox->mark_skipped_pending_for_order($order_id, 'Order was fully refunded before access was granted.');

        $grants = $this->outbox->get_grants_for_order($order_id);
        foreach ($grants as $grant) {
            $payload = json_decode((string) $grant->payload_json, true);
            if (!is_array($payload)) {
                continue;
            }

            $payload['event'] = 'revoke_access';
            $payload['trigger'] = $trigger;
            $payload['occurred_at_gmt'] = gmdate('Y-m-d H:i:s');
            $payload['refund'] = [
                'full_refund' => true,
                'total' => (string) $order->get_total(),
                'total_refunded' => (string) $order->get_total_refunded(),
            ];

            $outbox_id = $this->outbox->insert_payload($payload, VGCB_Sender_Outbox_Store::DIRECTION_REVOKE);
            if ($outbox_id > 0) {
                $this->outbox->schedule_job($outbox_id);
            }
        }
    }

    private function is_order_fully_refunded(WC_Order $order): bool
    {
        $total = (float) $order->get_total();
        $refunded = (float) $order->get_total_refunded();

        return $total > 0 && $refunded >= max(0, $total - 0.01);
    }

    /**
     * @return array{entitlement_type:string, entitlement_id:int, access_label:string}|null
     */
    private function get_product_mapping(WC_Product $product): ?array
    {
        $product_ids = [$product->get_id()];

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id > 0) {
                $product_ids[] = $parent_id;
            }
        }

        foreach ($product_ids as $product_id) {
            $enabled = get_post_meta($product_id, '_vgcb_enable_course_bridge', true);
            if ($enabled !== 'yes') {
                continue;
            }

            $type = sanitize_key((string) get_post_meta($product_id, '_vgcb_entitlement_type', true));
            $remote_id = absint(get_post_meta($product_id, '_vgcb_entitlement_id', true));
            $label = sanitize_text_field((string) get_post_meta($product_id, '_vgcb_access_label', true));

            if (!in_array($type, ['learndash_group', 'learndash_course'], true) || $remote_id <= 0) {
                continue;
            }

            return [
                'entitlement_type' => $type,
                'entitlement_id' => $remote_id,
                'access_label' => $label !== '' ? $label : 'Voelgoed course access',
            ];
        }

        return null;
    }

    private function build_payload(WC_Order $order, WC_Order_Item_Product $item, WC_Product $product, array $mapping, string $event, string $trigger): array
    {
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();

        return [
            'schema_version' => 1,
            'event' => $event,
            'trigger' => $trigger,
            'source_site' => defined('VG_COURSE_BRIDGE_SOURCE_SITE') ? (string) VG_COURSE_BRIDGE_SOURCE_SITE : home_url(),
            'source_order_id' => $order->get_id(),
            'source_order_number' => $order->get_order_number(),
            'source_order_item_id' => $item->get_id(),
            'source_product_id' => $product->get_id(),
            'source_product_name' => $product->get_name(),
            'customer' => [
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
            ],
            'entitlement' => [
                'type' => $mapping['entitlement_type'],
                'id' => $mapping['entitlement_id'],
                'label' => $mapping['access_label'],
            ],
            'payment' => [
                'gateway' => $order->get_payment_method(),
                'gateway_title' => $order->get_payment_method_title(),
                'order_status' => $order->get_status(),
                'paid' => $order->is_paid(),
            ],
            'occurred_at_gmt' => gmdate('Y-m-d H:i:s'),
        ];
    }
}
