<?php

declare(strict_types=1);

namespace App\Repositories;

final class LeagueRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT l.*, s.name AS sport_name FROM leagues l INNER JOIN sports s ON s.id = l.sport_id ORDER BY s.name, l.name'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM leagues WHERE id = :id', ['id' => $id]);
    }
}
