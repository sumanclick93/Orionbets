<?php

declare(strict_types=1);

namespace App\Repositories;

final class EventRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT e.*, s.name AS sport_name, l.name AS league_name
             FROM events e
             INNER JOIN sports s ON s.id = e.sport_id
             LEFT JOIN leagues l ON l.id = e.league_id
             ORDER BY COALESCE(e.start_time, e.event_at) DESC'
        );
    }

    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(e.name LIKE :q1 OR e.home_team LIKE :q2 OR e.away_team LIKE :q3 OR e.action_network_event_id LIKE :q4)';
            $qVal = '%' . $filters['q'] . '%';
            $params['q1'] = $qVal;
            $params['q2'] = $qVal;
            $params['q3'] = $qVal;
            $params['q4'] = $qVal;
        }
        if (!empty($filters['status'])) {
            $where[] = 'e.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['league'])) {
            if (is_numeric($filters['league'])) {
                $where[] = '(e.league_id = :league_id1 OR l.id = :league_id2)';
                $params['league_id1'] = (int) $filters['league'];
                $params['league_id2'] = (int) $filters['league'];
            } else {
                $slug = strtolower(trim((string) $filters['league']));
                $where[] = '(l.slug = :league_slug OR s.slug = :sport_slug)';
                $params['league_slug'] = $slug;
                $params['sport_slug'] = $slug;
            }
        }
        if (isset($filters['active']) && $filters['active'] !== '') {
            $where[] = 'e.is_active = :active';
            $params['active'] = (int) $filters['active'];
        }
        if (!empty($filters['date'])) {
            $where[] = 'DATE(COALESCE(e.start_time, e.event_at)) = :dt';
            $params['dt'] = $filters['date'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*)
             FROM events e
             INNER JOIN sports s ON s.id = e.sport_id
             LEFT JOIN leagues l ON l.id = e.league_id
             WHERE {$whereSql}",
            $params
        );
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT e.*, s.name AS sport_name, s.slug AS sport_slug, l.name AS league_name, l.slug AS league_slug
             FROM events e
             INNER JOIN sports s ON s.id = e.sport_id
             LEFT JOIN leagues l ON l.id = e.league_id
             WHERE {$whereSql}
             ORDER BY COALESCE(e.start_time, e.event_at) DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function upcoming(int $limit = 40): array
    {
        return $this->db->fetchAll(
            'SELECT e.*, s.name AS sport_name FROM events e
             INNER JOIN sports s ON s.id = e.sport_id
             WHERE COALESCE(e.start_time, e.event_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND (e.is_active = 1 OR e.is_active IS NULL)
             ORDER BY COALESCE(e.start_time, e.event_at) DESC LIMIT ' . (int) $limit
        );
    }

    public function completed(int $limit = 40): array
    {
        return $this->db->fetchAll(
            "SELECT e.*, s.name AS sport_name, l.name AS league_name
             FROM events e
             INNER JOIN sports s ON s.id = e.sport_id
             LEFT JOIN leagues l ON l.id = e.league_id
             WHERE e.status IN ('completed','canceled')
             ORDER BY COALESCE(e.start_time, e.event_at) DESC LIMIT " . (int) $limit
        );
    }

    public function toggleActive(int $id): ?array
    {
        $event = $this->find($id);
        if (!$event) {
            return null;
        }
        $next = ((int) ($event['is_active'] ?? 1)) === 1 ? 0 : 1;
        $this->update($id, ['is_active' => $next]);
        $event['is_active'] = $next;
        return $event;
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM events WHERE id = :id', ['id' => $id]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('events', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('events', 'id = :id', ['id' => $id]);
    }
}
