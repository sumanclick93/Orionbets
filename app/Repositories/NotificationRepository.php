<?php

declare(strict_types=1);

namespace App\Repositories;

final class NotificationRepository extends BaseRepository
{
    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :id ORDER BY created_at DESC LIMIT ' . (int) $limit,
            ['id' => $userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :id AND read_at IS NULL',
            ['id' => $userId]
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->update(
            'notifications',
            ['read_at' => date('Y-m-d H:i:s')],
            'id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('notifications', $data);
    }

    public function preferences(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM notification_preferences WHERE user_id = :id',
            ['id' => $userId]
        );
    }

    public function upsertPreference(int $userId, string $channel, string $event, bool $enabled): void
    {
        $row = $this->db->fetch(
            'SELECT id FROM notification_preferences WHERE user_id = :u AND channel = :c AND event_type = :e',
            ['u' => $userId, 'c' => $channel, 'e' => $event]
        );
        if ($row) {
            $this->db->update('notification_preferences', ['enabled' => $enabled ? 1 : 0], 'id = :id', ['id' => $row['id']]);
            return;
        }
        $this->db->insert('notification_preferences', [
            'user_id' => $userId,
            'channel' => $channel,
            'event_type' => $event,
            'enabled' => $enabled ? 1 : 0,
        ]);
    }
}
