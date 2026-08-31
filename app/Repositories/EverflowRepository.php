<?php

declare(strict_types=1);

namespace App\Repositories;

final class EverflowRepository extends BaseRepository
{
    /** @var array<string, list<string>> */
    private array $columnCache = [];

    public function findClick(string $transactionId): ?array
    {
        if ($transactionId === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM everflow_clicks WHERE transaction_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $transactionId]
        );
    }

    public function findClickByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !$this->hasColumn('everflow_clicks', 'email')) {
            return null;
        }

        $order = $this->hasColumn('everflow_clicks', 'updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';

        return $this->db->fetch(
            'SELECT * FROM everflow_clicks WHERE email = :e ORDER BY ' . $order . ' LIMIT 1',
            ['e' => $email]
        );
    }

    public function findClickByCookie(string $cookie): ?array
    {
        if ($cookie === '' || !$this->hasColumn('everflow_clicks', 'browser_cookie')) {
            return null;
        }

        $order = $this->hasColumn('everflow_clicks', 'updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';

        return $this->db->fetch(
            'SELECT * FROM everflow_clicks WHERE browser_cookie = :c ORDER BY ' . $order . ' LIMIT 1',
            ['c' => $cookie]
        );
    }

    public function upsertClick(array $data): void
    {
        $tid = (string) ($data['transaction_id'] ?? '');
        $row = $this->filterRow('everflow_clicks', $data);
        if ($row === []) {
            return;
        }

        $existing = $tid !== '' ? $this->findClick($tid) : null;
        if ($existing) {
            $update = [];
            foreach ($row as $key => $value) {
                if ($key === 'transaction_id') {
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $update[$key] = $value;
                }
            }
            if ($update !== []) {
                $this->db->update('everflow_clicks', $update, 'id = :id', ['id' => $existing['id']]);
            }
            return;
        }

        $this->db->insert('everflow_clicks', $row);
    }

    public function findPostback(string $kind, string $orderId): ?array
    {
        if ($orderId === '') {
            return null;
        }

        if ($this->hasColumn('everflow_postbacks', 'kind')) {
            return $this->db->fetch(
                'SELECT * FROM everflow_postbacks WHERE kind = :k AND order_id = :o ORDER BY id DESC LIMIT 1',
                ['k' => $kind, 'o' => $orderId]
            );
        }

        return $this->db->fetch(
            'SELECT * FROM everflow_postbacks WHERE order_id = :o ORDER BY id DESC LIMIT 1',
            ['o' => $orderId]
        );
    }

    public function findPostbackById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db->fetch('SELECT * FROM everflow_postbacks WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function createPostback(array $data): int
    {
        $row = $this->filterRow('everflow_postbacks', $data);
        if ($row === []) {
            return 0;
        }

        return $this->db->insert('everflow_postbacks', $row);
    }

    public function updatePostback(int $id, array $data): void
    {
        $row = $this->filterRow('everflow_postbacks', $data);
        if ($id < 1 || $row === []) {
            return;
        }

        $this->db->update('everflow_postbacks', $row, 'id = :id', ['id' => $id]);
    }

    public function recentPostbacks(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM everflow_postbacks ORDER BY created_at DESC, id DESC LIMIT {$limit}"
        );
    }

    public function recentClicks(int $limit = 25): array
    {
        if (!$this->db->tableExists('everflow_clicks')) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $order = $this->hasColumn('everflow_clicks', 'updated_at')
            ? 'updated_at DESC, id DESC'
            : ($this->hasColumn('everflow_clicks', 'created_at') ? 'created_at DESC, id DESC' : 'id DESC');

        return $this->db->fetchAll(
            "SELECT * FROM everflow_clicks ORDER BY {$order} LIMIT {$limit}"
        );
    }

    /**
     * @param array{q?:string,status?:string,from?:string,to?:string} $filters
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function paginatePostbacks(array $filters, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        [$where, $params] = $this->postbackWhere($filters);

        $total = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM ' . $this->postbackFrom() . ' ' . $where,
            $params
        );
        $offset = ($page - 1) * $perPage;
        $select = $this->postbackSelect();
        $rows = $this->db->fetchAll(
            "{$select} {$where} ORDER BY p.created_at DESC, p.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array{q?:string,status?:string,from?:string,to?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function exportPostbacks(array $filters, int $limit = 5000): array
    {
        $limit = max(1, min(20000, $limit));
        [$where, $params] = $this->postbackWhere($filters);
        $select = $this->postbackSelect();

        return $this->db->fetchAll(
            "{$select} {$where} ORDER BY p.created_at DESC, p.id DESC LIMIT {$limit}",
            $params
        );
    }

    /**
     * @param array{q?:string,from?:string,to?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function exportClicks(array $filters, int $limit = 5000): array
    {
        $limit = max(1, min(20000, $limit));
        [$where, $params] = $this->clickWhere($filters);

        return $this->db->fetchAll(
            "SELECT * FROM everflow_clicks {$where} ORDER BY created_at DESC, id DESC LIMIT {$limit}",
            $params
        );
    }

    /**
     * @return array{
     *   total:int,
     *   success:int,
     *   failed:int,
     *   pending:int,
     *   revenue:float
     * }
     */
    public function postbackStats(array $filters = []): array
    {
        $empty = ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0, 'revenue' => 0.0];
        if (!$this->db->tableExists('everflow_postbacks')) {
            return $empty;
        }

        $scoped = $filters;
        unset($scoped['status']);
        [$where, $params] = $this->postbackWhere($scoped);
        $hasStatus = $this->hasColumn('everflow_postbacks', 'status');
        $statusExpr = $hasStatus
            ? 'COALESCE(p.status, CASE WHEN p.http_status >= 200 AND p.http_status < 400 THEN \'success\' ELSE \'failed\' END)'
            : "CASE WHEN p.http_status >= 200 AND p.http_status < 400 THEN 'success' ELSE 'failed' END";

        $row = $this->db->fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN {$statusExpr} = 'success' THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN {$statusExpr} = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN {$statusExpr} = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                COALESCE(SUM(CASE WHEN {$statusExpr} = 'success' THEN p.amount ELSE 0 END), 0) AS revenue
             FROM {$this->postbackFrom()}
             {$where}",
            $params
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'success' => (int) ($row['success_count'] ?? 0),
            'failed' => (int) ($row['failed_count'] ?? 0),
            'pending' => (int) ($row['pending_count'] ?? 0),
            'revenue' => (float) ($row['revenue'] ?? 0),
        ];
    }

    /**
     * @param array{q?:string,from?:string,to?:string} $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function clickWhere(array $filters): array
    {
        $clauses = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $search = [];
            foreach (['transaction_id', 'impression_id', 'affiliate_id', 'affid', 'offer_id', 'oid', 'landing_url', 'ip_address', 'ip', 'email', 'click_type', 'sub1', 'sub2', 'sub3', 'sub4', 'sub5'] as $col) {
                if ($this->hasColumn('everflow_clicks', $col)) {
                    $search[] = $col . ' LIKE :q';
                }
            }
            if ($search !== []) {
                $clauses[] = '(' . implode(' OR ', $search) . ')';
                $params['q'] = '%' . $q . '%';
            }
        }
        $from = $this->dateParam((string) ($filters['from'] ?? ''));
        if ($from !== null) {
            $clauses[] = 'created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        $to = $this->dateParam((string) ($filters['to'] ?? ''));
        if ($to !== null) {
            $clauses[] = 'created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @param array{q?:string,status?:string,from?:string,to?:string} $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function postbackWhere(array $filters): array
    {
        $clauses = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $search = [];
            foreach (['order_id', 'transaction_id', 'everflow_transaction_id', 'email', 'sub1', 'sub2', 'sub3', 'sub4', 'sub5', 'kind', 'event_type'] as $col) {
                if ($this->hasColumn('everflow_postbacks', $col)) {
                    $search[] = 'p.' . $col . ' LIKE :q';
                }
            }
            if ($this->hasColumn('everflow_postbacks', 'user_id')) {
                $search[] = 'u.email LIKE :q';
            }
            if ($search !== []) {
                $clauses[] = '(' . implode(' OR ', $search) . ')';
                $params['q'] = '%' . $q . '%';
            }
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['success', 'failed', 'pending'], true)) {
            if ($this->hasColumn('everflow_postbacks', 'status')) {
                $clauses[] = 'p.status = :st';
                $params['st'] = $status;
            } elseif ($status === 'success') {
                $clauses[] = 'p.http_status >= 200 AND p.http_status < 400';
            } elseif ($status === 'failed') {
                $clauses[] = '(p.http_status IS NULL OR p.http_status < 200 OR p.http_status >= 400)';
            }
        }

        $from = $this->dateParam((string) ($filters['from'] ?? ''));
        if ($from !== null) {
            $clauses[] = 'p.created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        $to = $this->dateParam((string) ($filters['to'] ?? ''));
        if ($to !== null) {
            $clauses[] = 'p.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function postbackFrom(): string
    {
        if ($this->hasColumn('everflow_postbacks', 'user_id')) {
            return 'everflow_postbacks p LEFT JOIN users u ON u.id = p.user_id';
        }

        return 'everflow_postbacks p';
    }

    private function postbackSelect(): string
    {
        if ($this->hasColumn('everflow_postbacks', 'user_id')) {
            return 'SELECT p.*, u.email AS user_email, u.first_name AS user_first_name, u.last_name AS user_last_name
                    FROM everflow_postbacks p
                    LEFT JOIN users u ON u.id = p.user_id';
        }

        return 'SELECT p.* FROM everflow_postbacks p';
    }

    private function dateParam(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterRow(string $table, array $data): array
    {
        $cols = $this->columns($table);
        $out = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $cols, true)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        if (!isset($this->columnCache[$table])) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t',
                    ['t' => $table]
                );
                $names = [];
                foreach ($rows as $row) {
                    $name = (string) ($row['column_name'] ?? $row['COLUMN_NAME'] ?? '');
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
                $this->columnCache[$table] = $names;
            } catch (\Throwable) {
                $this->columnCache[$table] = [];
            }
        }

        return $this->columnCache[$table];
    }
}
