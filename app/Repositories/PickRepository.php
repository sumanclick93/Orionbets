<?php

declare(strict_types=1);

namespace App\Repositories;

final class PickRepository extends BaseRepository
{
    private function select(): string
    {
        return "SELECT p.*, s.name AS sport_name, s.slug AS sport_slug,
                       l.name AS league_name, l.slug AS league_slug,
                       e.name AS event_name, e.event_at, e.start_time, e.status AS event_status,
                       e.home_team, e.away_team, e.home_score, e.away_score,
                       COALESCE(p.matchup, e.name) AS matchup_label,
                       pr.result AS result, pr.units AS result_units, pr.recorded_at AS result_at, pr.closing_notes
                FROM picks p
                INNER JOIN sports s ON s.id = p.sport_id
                LEFT JOIN leagues l ON l.id = p.league_id
                LEFT JOIN events e ON e.id = p.event_id
                LEFT JOIN pick_results pr ON pr.pick_id = p.id";
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch($this->select() . ' WHERE p.slug = :slug AND p.deleted_at IS NULL', ['slug' => $slug]);
    }

    public function findById(int $id, bool $withArchived = false): ?array
    {
        $sql = $this->select() . ' WHERE p.id = :id';
        if (!$withArchived) {
            $sql .= ' AND p.deleted_at IS NULL';
        }
        return $this->db->fetch($sql, ['id' => $id]);
    }

    public function featured(): ?array
    {
        return $this->db->fetch(
            $this->select() . " WHERE p.deleted_at IS NULL AND p.status IN ('published','won','lost','push')
            ORDER BY p.is_premium ASC, p.published_at DESC LIMIT 1"
        );
    }

    public function todayPlaybook(): array
    {
        $sql = $this->select() . " WHERE p.deleted_at IS NULL
                AND " . $this->visibleSql() . "
                AND p.status IN ('published','scheduled','pending','in_progress')
                AND (DATE(COALESCE(e.start_time, p.published_at, e.event_at, p.created_at)) = CURDATE()
                     OR COALESCE(e.start_time, e.event_at) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY))";
        $sql .= ' ORDER BY COALESCE(e.start_time, e.event_at) ASC';
        return $this->db->fetchAll($sql);
    }

    public function search(array $filters, int $page = 1, int $perPage = 12, bool $includePremium = true): array
    {
        $where = !empty($filters['archived']) ? ['p.deleted_at IS NOT NULL'] : ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['visible'])) {
            $where[] = $this->visibleSql();
        }
        if (!empty($filters['live'])) {
            $where[] = "p.status IN ('published','scheduled','pending','in_progress')";
        }
        if (!empty($filters['settled'])) {
            $where[] = "p.status IN ('won','lost','push','canceled','cancelled','completed')";
        }
        if (!empty($filters['sport'])) {
            $where[] = '(s.slug = :sport OR p.sport = :sport_alt)';
            $params['sport'] = $filters['sport'];
            $params['sport_alt'] = $filters['sport'];
        }
        if (!empty($filters['league'])) {
            $where[] = '(l.slug = :league OR p.league = :league_alt)';
            $params['league'] = $filters['league'];
            $params['league_alt'] = $filters['league'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.title LIKE :q OR p.analysis_excerpt LIKE :q OR p.matchup LIKE :q OR p.selection_line LIKE :q OR e.name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['date'])) {
            $where[] = 'DATE(COALESCE(e.start_time, e.event_at, p.published_at)) = :dt';
            $params['dt'] = $filters['date'];
        }
        if (isset($filters['access']) && $filters['access'] === 'free') {
            $where[] = 'p.is_premium = 0';
        } elseif (isset($filters['access']) && $filters['access'] === 'premium') {
            $where[] = 'p.is_premium = 1';
        }

        if (!$includePremium && empty($filters['access'])) {
            // listing still shows premium cards, content gated later
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM picks p
             INNER JOIN sports s ON s.id = p.sport_id
             LEFT JOIN leagues l ON l.id = p.league_id
             LEFT JOIN events e ON e.id = p.event_id
             WHERE {$whereSql}",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $order = !empty($filters['live'])
            ? 'COALESCE(e.start_time, e.event_at, p.published_at) ASC'
            : 'COALESCE(p.published_at, p.created_at) DESC';
        $rows = $this->db->fetchAll(
            $this->select() . " WHERE {$whereSql} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function recentResults(int $limit = 8): array
    {
        return $this->db->fetchAll(
            $this->select() . " WHERE p.deleted_at IS NULL
             AND " . $this->visibleSql() . "
             AND (p.status IN ('won','lost','push','canceled','cancelled','completed')
                  OR pr.result IN ('won','lost','push','canceled','cancelled'))
             ORDER BY COALESCE(pr.recorded_at, e.start_time, e.event_at, p.updated_at) DESC LIMIT " . (int) $limit
        );
    }

    public function toggleActive(int $id): ?array
    {
        $pick = $this->findById($id, true);
        if (!$pick) {
            return null;
        }
        $next = ((int) ($pick['is_active'] ?? 1)) === 1 ? 0 : 1;
        $this->update($id, ['is_active' => $next]);
        $pick['is_active'] = $next;
        return $pick;
    }

    private function visibleSql(): string
    {
        return '(p.is_active = 1 OR p.is_active IS NULL) AND (p.is_published = 1 OR p.is_published IS NULL)';
    }

    public function create(array $data): int
    {
        return $this->db->insert('picks', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('picks', $data, 'id = :id', ['id' => $id]);
    }

    public function archive(int $id): void
    {
        $this->db->update('picks', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->db->update('picks', ['deleted_at' => null], 'id = :id', ['id' => $id]);
    }

    public function counts(): array
    {
        return [
            'published' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM picks WHERE deleted_at IS NULL AND status IN ('published','won','lost','push')"),
            'completed' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM picks WHERE deleted_at IS NULL AND status IN ('won','lost','push','cancelled')"),
        ];
    }
}
