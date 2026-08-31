<?php

declare(strict_types=1);

namespace App\Repositories;

final class SportRepository extends BaseRepository
{
    public function allActive(): array
    {
        return $this->db->fetchAll('SELECT * FROM sports WHERE is_active = 1 ORDER BY sort_order, name');
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM sports ORDER BY sort_order, name');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM sports WHERE id = :id', ['id' => $id]);
    }
}
