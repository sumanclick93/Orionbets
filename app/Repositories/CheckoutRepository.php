<?php

declare(strict_types=1);

namespace App\Repositories;

final class CheckoutRepository extends BaseRepository
{
    public function findByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return $this->db->fetch('SELECT * FROM checkout_sessions WHERE token = :t LIMIT 1', ['t' => $token]);
    }

    public function findByOrderId(string $orderId): ?array
    {
        if ($orderId === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM checkout_sessions WHERE provider_order_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $orderId]
        );
    }

    public function findOpenByEmail(string $email): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM checkout_sessions
             WHERE email = :email AND status IN ('pending', 'processing')
             ORDER BY id DESC LIMIT 1",
            ['email' => $email]
        );
    }

    public function findLatestByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM checkout_sessions WHERE email = :email ORDER BY id DESC LIMIT 1',
            ['email' => $email]
        );
    }

    /**
     * @param list<string> $emails
     * @return list<array<string, mixed>>
     */
    public function forMember(int $userId, array $emails = [], string $cookie = ''): array
    {
        $clauses = ['c.user_id = :uid'];
        $params = ['uid' => $userId];
        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            $emails
        ))));
        foreach ($emails as $i => $email) {
            $key = 'em' . $i;
            $clauses[] = "c.email = :{$key}";
            $params[$key] = $email;
        }
        if ($cookie !== '') {
            $clauses[] = 'c.browser_cookie = :cookie';
            $params['cookie'] = $cookie;
        }

        return $this->db->fetchAll(
            'SELECT c.*, p.name AS plan_name
             FROM checkout_sessions c
             LEFT JOIN subscription_plans p ON p.id = c.plan_id
             WHERE ' . implode(' OR ', $clauses) . '
             ORDER BY c.created_at DESC',
            $params
        );
    }

    public function recent(int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            "SELECT c.*, p.name AS plan_name
             FROM checkout_sessions c
             LEFT JOIN subscription_plans p ON p.id = c.plan_id
             ORDER BY c.created_at DESC
             LIMIT {$limit}"
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('checkout_sessions', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('checkout_sessions', $data, 'id = :id', ['id' => $id]);
    }
}
