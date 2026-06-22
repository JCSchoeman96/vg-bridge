<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

interface VGCB_Sender_Outbox_Store
{
    public const DIRECTION_GRANT = 'grant';
    public const DIRECTION_REVOKE = 'revoke';

    public function insert_payload(array $payload, string $direction): int;

    public function schedule_job(int $outbox_id): void;

    public function mark_skipped_pending_for_order(int $order_id, string $reason): void;

    /**
     * @return array<int, object>
     */
    public function get_grants_for_order(int $order_id): array;
}
