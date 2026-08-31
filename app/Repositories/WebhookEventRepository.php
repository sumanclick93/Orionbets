<?php

declare(strict_types=1);

namespace App\Repositories;

final class WebhookEventRepository extends BaseRepository
{
    public function findByEventId(string $provider, string $eventId): ?array
    {
        if ($eventId === '') {
            return null;
        }

        return $this->db->fetch(
            'SELECT * FROM webhook_events WHERE provider = :p AND event_id = :e LIMIT 1',
            ['p' => $provider, 'e' => $eventId]
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('webhook_events', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('webhook_events', $data, 'id = :id', ['id' => $id]);
    }

    public function recent(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM webhook_events ORDER BY created_at DESC LIMIT {$limit}"
        );
    }
}
