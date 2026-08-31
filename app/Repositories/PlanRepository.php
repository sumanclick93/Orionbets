<?php

declare(strict_types=1);

namespace App\Repositories;

final class PlanRepository extends BaseRepository
{
    public function allActive(): array
    {
        return $this->db->fetchAll('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order');
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM subscription_plans ORDER BY sort_order');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM subscription_plans WHERE id = :id', ['id' => $id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM subscription_plans WHERE slug = :slug', ['slug' => $slug]);
    }

    public function findByPaymentUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $exact = $this->db->fetch('SELECT * FROM subscription_plans WHERE payment_url = :url LIMIT 1', ['url' => $url]);
        if ($exact) {
            return $exact;
        }

        $productId = upgrade_chat_product_id($url);
        if ($productId === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM subscription_plans WHERE payment_url LIKE :q LIMIT 1',
            ['q' => '%' . $productId . '%']
        );
    }

    public function allWithSubscriberCounts(): array
    {
        if ($this->db->tableExists('subscriptions')) {
            return $this->db->fetchAll(
                "SELECT p.*,
                    COUNT(CASE WHEN s.status IN ('active', 'trialing') AND (s.ends_at IS NULL OR s.ends_at > NOW()) THEN 1 END) AS active_subscribers_count,
                    COUNT(s.id) AS total_subscribers_count
                 FROM subscription_plans p
                 LEFT JOIN subscriptions s ON s.plan_id = p.id
                 GROUP BY p.id
                 ORDER BY p.sort_order, p.id"
            );
        }

        return array_map(static function (array $plan): array {
            $plan['active_subscribers_count'] = 0;
            $plan['total_subscribers_count'] = 0;
            return $plan;
        }, $this->all());
    }

    public function toggleStatus(int $id): bool
    {
        $plan = $this->find($id);
        if (!$plan) {
            return false;
        }

        $newStatus = empty($plan['is_active']) ? 1 : 0;
        $this->update($id, ['is_active' => $newStatus]);
        return (bool) $newStatus;
    }

    public function delete(int $id): void
    {
        $this->db->delete('subscription_plans', 'id = :id', ['id' => $id]);
    }
}
