<?php

declare(strict_types=1);

namespace App\Repositories;

final class FaqRepository extends BaseRepository
{
    public function published(): array
    {
        return $this->db->fetchAll('SELECT * FROM faqs WHERE is_published = 1 ORDER BY category, sort_order');
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM faqs ORDER BY category, sort_order');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM faqs WHERE id = :id', ['id' => $id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('faqs', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('faqs', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('faqs', 'id = :id', ['id' => $id]);
    }
}
