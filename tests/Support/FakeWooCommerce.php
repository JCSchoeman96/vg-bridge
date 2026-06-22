<?php

declare(strict_types=1);

if (!class_exists('WC_Product')) {
    class WC_Product
    {
        public function __construct(
            private int $id,
            private string $name = 'Test Product',
            private string $type = 'simple',
            private int $parent_id = 0
        ) {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_name(): string
        {
            return $this->name;
        }

        public function is_type(string $type): bool
        {
            return $this->type === $type;
        }

        public function get_parent_id(): int
        {
            return $this->parent_id;
        }
    }
}

if (!class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product
    {
        public function __construct(
            private int $id,
            private WC_Product $product
        ) {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_product(): WC_Product
        {
            return $this->product;
        }
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order
    {
        /**
         * @param WC_Order_Item_Product[] $items
         */
        public function __construct(
            private int $id,
            private bool $paid,
            private array $items,
            private float $total = 100.00,
            private float $refunded = 0.00
        ) {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function is_paid(): bool
        {
            return $this->paid;
        }

        public function get_items(): array
        {
            return $this->items;
        }

        public function get_total(): string
        {
            return (string) $this->total;
        }

        public function get_total_refunded(): string
        {
            return (string) $this->refunded;
        }

        public function get_billing_first_name(): string
        {
            return 'Anna';
        }

        public function get_billing_last_name(): string
        {
            return 'Botha';
        }

        public function get_billing_email(): string
        {
            return 'anna@example.com';
        }

        public function get_billing_phone(): string
        {
            return '0820000000';
        }

        public function get_order_number(): string
        {
            return (string) $this->id;
        }

        public function get_payment_method(): string
        {
            return 'paystack';
        }

        public function get_payment_method_title(): string
        {
            return 'Paystack';
        }

        public function get_status(): string
        {
            return $this->paid ? 'processing' : 'pending';
        }

        public function add_order_note(string $message): void
        {
            $GLOBALS['vgcb_test_order_notes'][] = $message;
        }
    }
}
