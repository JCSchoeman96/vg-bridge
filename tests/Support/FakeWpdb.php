<?php

declare(strict_types=1);

namespace VGBridgeTests\Support;

final class FakeWpdb
{
    public string $prefix = 'wp_';

    public int $insert_id = 0;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $tables = [];

    private int $autoIncrement = 0;

    public function get_charset_collate(): string
    {
        return 'utf8mb4_unicode_ci';
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $format
     */
    public function insert(string $table, array $data, array $format = []): int|false
    {
        if ($table === $this->prefix . 'vgcb_receiver_nonces') {
            foreach ($this->tables[$table] ?? [] as $row) {
                if ($row['source_site'] === $data['source_site'] && $row['nonce'] === $data['nonce']) {
                    return false;
                }
            }
        }

        $this->autoIncrement++;
        $this->insert_id = $this->autoIncrement;
        $data['id'] = $this->insert_id;

        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }

        $this->tables[$table][$this->insert_id] = $data;

        return 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null): int|false
    {
        $updated = 0;

        foreach ($this->tables[$table] ?? [] as $id => $row) {
            $match = true;
            foreach ($where as $key => $value) {
                if (($row[$key] ?? null) != $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                $this->tables[$table][$id] = array_merge($row, $data);
                $updated++;
            }
        }

        return $updated > 0 ? $updated : false;
    }

    public function query(string $query): int|false
    {
        if (preg_match('/UPDATE\s+(\S+)\s+SET/i', $query, $matches)) {
            $table = $matches[1];

            if (str_contains($query, 'attempt_count = attempt_count + 1')) {
                if (preg_match('/WHERE id = (\d+)/', $query, $idMatch)) {
                    $id = (int) $idMatch[1];
                    if (isset($this->tables[$table][$id])) {
                        $this->tables[$table][$id]['attempt_count'] = ((int) ($this->tables[$table][$id]['attempt_count'] ?? 0)) + 1;
                    }
                }

                return 1;
            }

            if (preg_match("/status = '([^']+)'/", $query, $statusMatch)
                && preg_match('/WHERE order_id = (\d+)/', $query, $orderMatch)
                && str_contains($query, "direction = 'grant'")
            ) {
                $orderId = (int) $orderMatch[1];
                $newStatus = $statusMatch[1];
                $updated = 0;

                foreach ($this->tables[$table] ?? [] as $id => $row) {
                    if ((int) $row['order_id'] === $orderId
                        && $row['direction'] === 'grant'
                        && in_array($row['status'], ['pending', 'failed'], true)
                    ) {
                        $this->tables[$table][$id]['status'] = $newStatus;
                        $updated++;
                    }
                }

                return $updated;
            }

            if (preg_match('/WHERE id = (\d+)/', $query, $idMatch)) {
                $id = (int) $idMatch[1];
                if (!isset($this->tables[$table][$id])) {
                    return false;
                }

                if (preg_match("/status = '([^']+)'/", $query, $statusMatch)) {
                    $this->tables[$table][$id]['status'] = $statusMatch[1];
                }
                if (preg_match('/last_response_code = (\d+)/', $query, $codeMatch)) {
                    $this->tables[$table][$id]['last_response_code'] = (int) $codeMatch[1];
                }
                if (preg_match('/remote_user_id = (\d+)/', $query, $userMatch)) {
                    $this->tables[$table][$id]['remote_user_id'] = (int) $userMatch[1];
                }
                if (str_contains($query, 'last_error = NULL')) {
                    $this->tables[$table][$id]['last_error'] = null;
                }

                return 1;
            }
        }

        if (preg_match('/DELETE FROM/', $query)) {
            return 0;
        }

        return false;
    }

    public function get_var(string $query): mixed
    {
        $row = $this->get_row($query);

        if ($row === null) {
            return null;
        }

        if (preg_match('/SELECT\s+(\w+)/i', $query, $matches)) {
            $col = $matches[1];

            return $row->$col ?? null;
        }

        return null;
    }

    public function get_row(string $query): ?object
    {
        $results = $this->get_results($query);

        return $results[0] ?? null;
    }

    /**
     * @return object[]
     */
    public function get_results(string $query): array
    {
        if (!preg_match('/FROM\s+(\S+)/i', $query, $tableMatch)) {
            return [];
        }

        $table = $tableMatch[1];
        $rows = array_values($this->tables[$table] ?? []);

        if (preg_match('/WHERE id = (\d+)/', $query, $idMatch)) {
            $id = (int) $idMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $id);
        }

        if (preg_match('/WHERE order_id = (\d+)/', $query, $orderMatch)) {
            $orderId = (int) $orderMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => (int) ($row['order_id'] ?? 0) === $orderId);
        }

        if (preg_match("/AND direction = '([^']+)'/", $query, $dirMatch)) {
            $direction = $dirMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['direction'] ?? '') === $direction);
        }

        if (preg_match("/AND status = '([^']+)'/", $query, $statusMatch)) {
            $status = $statusMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['status'] ?? '') === $status);
        }

        if (preg_match('/WHERE source_site = \'([^\']+)\'/', $query, $siteMatch)) {
            $site = $siteMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['source_site'] ?? '') === $site);
        }

        if (preg_match('/AND source_order_id = (\d+)/', $query, $orderIdMatch)) {
            $sourceOrderId = (int) $orderIdMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => (int) ($row['source_order_id'] ?? 0) === $sourceOrderId);
        }

        if (preg_match("/AND entitlement_type = '([^']+)'/", $query, $typeMatch)) {
            $type = $typeMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['entitlement_type'] ?? '') === $type);
        }

        if (preg_match('/AND entitlement_id = (\d+)/', $query, $entMatch)) {
            $entId = (int) $entMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => (int) ($row['entitlement_id'] ?? 0) === $entId);
        }

        if (preg_match("/AND operation = '([^']+)'/", $query, $opMatch)) {
            $operation = $opMatch[1];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['operation'] ?? '') === $operation);
        }

        if (preg_match('/ORDER BY id DESC/', $query) && preg_match('/LIMIT (\d+)/', $query, $limitMatch)) {
            usort($rows, static fn(array $a, array $b): int => (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
            $rows = array_slice($rows, 0, (int) $limitMatch[1]);
        }

        if (preg_match('/ORDER BY id ASC/', $query)) {
            usort($rows, static fn(array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        }

        return array_map(static fn(array $row): object => (object) $row, array_values($rows));
    }

    public function prepare(string $query, mixed ...$args): string
    {
        $result = $query;
        foreach ($args as $arg) {
            if (is_int($arg) || is_float($arg)) {
                $replacement = (string) (int) $arg;
            } else {
                $replacement = "'" . str_replace("'", "''", (string) $arg) . "'";
            }

            $result = preg_replace('/%[dfs]/', $replacement, $result, 1) ?? $result;
        }

        return $result;
    }

    public function nonceCount(): int
    {
        return count($this->tables[$this->prefix . 'vgcb_receiver_nonces'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTableRows(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    public function seedOutboxRow(array $data): int
    {
        $table = $this->prefix . 'vgcb_sender_outbox';
        $this->autoIncrement++;
        $this->insert_id = $this->autoIncrement;
        $data['id'] = $this->insert_id;

        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }

        $this->tables[$table][$this->insert_id] = $data;

        return $this->insert_id;
    }
}
