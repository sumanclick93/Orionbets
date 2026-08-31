<?php

declare(strict_types=1);

namespace App\Repositories;

final class TransactionRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, (int) ($filters['page'] ?? $page));
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? $perPage)));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilterClause($filters);

        $totalSql = "SELECT COUNT(*) FROM (
            SELECT st.id
            FROM subscription_transactions st
            LEFT JOIN users u ON u.id = st.user_id
            LEFT JOIN subscriptions s ON s.id = st.subscription_id
            LEFT JOIN checkout_sessions cs ON (
                cs.provider_order_id = st.provider_transaction_id
                OR (cs.user_id = st.user_id AND cs.provider_transaction_id = st.provider_transaction_id)
            )
            {$where}
        ) AS total_count";

        $total = (int) $this->db->fetchColumn($totalSql, $params);

        $query = "SELECT
            st.id AS transaction_record_id,
            st.provider_transaction_id AS transaction_id,
            COALESCE(cs.provider_order_id, st.provider_transaction_id) AS order_id,
            st.amount_cents,
            st.currency,
            st.status,
            st.provider,
            st.description,
            st.payload,
            st.created_at,
            u.id AS user_id,
            u.first_name,
            u.last_name,
            u.email AS user_email,
            cs.email AS checkout_email,
            cs.name AS customer_name,
            cs.everflow_transaction_id,
            s.id AS subscription_id
        FROM subscription_transactions st
        LEFT JOIN users u ON u.id = st.user_id
        LEFT JOIN subscriptions s ON s.id = st.subscription_id
        LEFT JOIN checkout_sessions cs ON (
            cs.provider_order_id = st.provider_transaction_id
            OR (cs.user_id = st.user_id AND cs.provider_transaction_id = st.provider_transaction_id)
        )
        {$where}
        ORDER BY st.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->fetchAll($query, $params);

        return [
            'data' => array_map(fn ($row) => $this->normalizeRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function exportTransactions(array $filters = []): array
    {
        [$where, $params] = $this->buildFilterClause($filters);

        $query = "SELECT
            st.id AS transaction_record_id,
            st.provider_transaction_id AS transaction_id,
            COALESCE(cs.provider_order_id, st.provider_transaction_id) AS order_id,
            st.amount_cents,
            st.currency,
            st.status,
            st.provider,
            st.description,
            st.payload,
            st.created_at,
            u.id AS user_id,
            u.first_name,
            u.last_name,
            u.email AS user_email,
            cs.email AS checkout_email,
            cs.name AS customer_name,
            cs.everflow_transaction_id,
            s.id AS subscription_id
        FROM subscription_transactions st
        LEFT JOIN users u ON u.id = st.user_id
        LEFT JOIN subscriptions s ON s.id = st.subscription_id
        LEFT JOIN checkout_sessions cs ON (
            cs.provider_order_id = st.provider_transaction_id
            OR (cs.user_id = st.user_id AND cs.provider_transaction_id = st.provider_transaction_id)
        )
        {$where}
        ORDER BY st.created_at DESC";

        $rows = $this->db->fetchAll($query, $params);

        return array_map(fn ($row) => $this->normalizeRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{total_count: int, completed_count: int, total_amount_cents: int}
     */
    public function stats(array $filters = []): array
    {
        [$where, $params] = $this->buildFilterClause($filters);

        $sql = "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN st.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
            COALESCE(SUM(CASE WHEN st.status = 'completed' THEN st.amount_cents ELSE 0 END), 0) AS total_amount_cents
        FROM subscription_transactions st
        LEFT JOIN users u ON u.id = st.user_id
        LEFT JOIN checkout_sessions cs ON (
            cs.provider_order_id = st.provider_transaction_id
            OR (cs.user_id = st.user_id AND cs.provider_transaction_id = st.provider_transaction_id)
        )
        {$where}";

        $res = $this->db->fetch($sql, $params);

        return [
            'total_count' => (int) ($res['total_count'] ?? 0),
            'completed_count' => (int) ($res['completed_count'] ?? 0),
            'total_amount_cents' => (int) ($res['total_amount_cents'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterClause(array $filters): array
    {
        $clauses = [];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        $provider = strtolower(trim((string) ($filters['provider'] ?? '')));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($search !== '') {
            $like = '%' . $search . '%';
            $clauses[] = '(
                st.provider_transaction_id LIKE :q1
                OR cs.provider_order_id LIKE :q2
                OR cs.everflow_transaction_id LIKE :q3
                OR u.email LIKE :q4
                OR u.first_name LIKE :q5
                OR u.last_name LIKE :q6
                OR cs.email LIKE :q7
                OR cs.name LIKE :q8
            )';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
            $params['q7'] = $like;
            $params['q8'] = $like;
        }

        if ($provider !== '' && $provider !== 'all') {
            if ($provider === 'upgradechat' || $provider === 'upgrade.chat') {
                $clauses[] = "st.provider IN ('upgradechat', 'upgrade.chat', 'upgrade_chat')";
            } else {
                $clauses[] = "st.provider = :filter_provider";
                $params['filter_provider'] = $provider;
            }
        }

        if ($status !== '' && $status !== 'all') {
            $clauses[] = "st.status = :filter_status";
            $params['filter_status'] = $status;
        }

        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $clauses[] = "st.created_at >= :filter_from";
            $params['filter_from'] = $from . ' 00:00:00';
        }

        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $clauses[] = "st.created_at <= :filter_to";
            $params['filter_to'] = $to . ' 23:59:59';
        }

        $where = $clauses !== [] ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $payload = is_array($row['payload'] ?? null)
            ? $row['payload']
            : (json_decode((string) ($row['payload'] ?? ''), true) ?: []);

        $customerName = trim((string) ($row['customer_name'] ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        }
        if ($customerName === '') {
            $customerName = (string) ($payload['name'] ?? 'Guest Customer');
        }

        $customerEmail = strtolower(trim((string) ($row['user_email'] ?? $row['checkout_email'] ?? $payload['email'] ?? $payload['paypal_payer_email'] ?? '')));

        $providerRaw = strtolower(trim((string) ($row['provider'] ?? '')));
        $providerLabel = match ($providerRaw) {
            'paypal' => 'PayPal',
            'upgradechat', 'upgrade.chat', 'upgrade_chat' => 'Upgrade.Chat',
            'demo' => 'Demo',
            default => ucfirst($providerRaw ?: 'Unknown'),
        };

        $everflowTid = (string) ($row['everflow_transaction_id'] ?? $payload['everflow_transaction_id'] ?? $payload['transaction_id'] ?? '');

        return [
            'id' => (int) ($row['transaction_record_id'] ?? 0),
            'transaction_id' => (string) ($row['transaction_id'] ?? ''),
            'order_id' => (string) ($row['order_id'] ?? ''),
            'amount_cents' => (int) ($row['amount_cents'] ?? 0),
            'currency' => (string) ($row['currency'] ?? 'USD'),
            'status' => strtolower((string) ($row['status'] ?? 'completed')),
            'provider' => $providerLabel,
            'provider_key' => $providerRaw,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'user_id' => !empty($row['user_id']) ? (int) $row['user_id'] : null,
            'everflow_transaction_id' => $everflowTid,
            'description' => (string) ($row['description'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'payload' => $payload,
        ];
    }
}
