<?php

declare(strict_types=1);

namespace App\Repositories;

final class SubscriptionRepository extends BaseRepository
{
    public function currentForUser(int $userId): ?array
    {
        return $this->db->fetch(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.price_cents, p.billing_interval, p.features
             FROM subscriptions s
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.user_id = :uid
             ORDER BY s.created_at DESC
             LIMIT 1",
            ['uid' => $userId]
        );
    }

    public function transactions(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM subscription_transactions WHERE user_id = :uid ORDER BY created_at DESC',
            ['uid' => $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.price_cents, p.billing_interval
             FROM subscriptions s
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.user_id = :uid
             ORDER BY s.created_at DESC",
            ['uid' => $userId]
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('subscriptions', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('subscriptions', $data, 'id = :id', ['id' => $id]);
    }

    public function addTransaction(array $data): int
    {
        return $this->db->insert('subscription_transactions', $data);
    }

    public function findByProviderSubscription(string $providerId): ?array
    {
        if ($providerId === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM subscriptions WHERE provider_subscription_id = :id LIMIT 1',
            ['id' => $providerId]
        );
    }

    public function findTransactionByProviderId(string $providerId): ?array
    {
        if ($providerId === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM subscription_transactions WHERE provider_transaction_id = :id LIMIT 1',
            ['id' => $providerId]
        );
    }

    public function activeCount(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')"
        );
    }

    public function revenueCents(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount_cents),0) FROM subscription_transactions WHERE status = 'completed'"
        );
    }

    public function allWithUsers(): array
    {
        return $this->db->fetchAll(
            "SELECT s.*, u.email, u.first_name, u.last_name, p.name AS plan_name
             FROM subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             ORDER BY s.created_at DESC"
        );
    }
}
