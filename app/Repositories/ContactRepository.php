<?php

declare(strict_types=1);

namespace App\Repositories;

final class ContactRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->db->insert('contact_messages', $data);
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM contact_messages ORDER BY created_at DESC');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM contact_messages WHERE id = :id', ['id' => $id]);
    }

    public function updateStatus(int $id, string $status, ?string $notes = null): void
    {
        $this->db->update('contact_messages', [
            'status' => $status,
            'admin_notes' => $notes,
        ], 'id = :id', ['id' => $id]);
    }
}
