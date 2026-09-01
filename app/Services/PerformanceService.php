<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class PerformanceService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Get available active leagues from database or pick history.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableLeagues(): array
    {
        if ($this->db->tableExists('leagues')) {
            $leagues = $this->db->fetchAll(
                'SELECT DISTINCT l.id, l.name, l.slug
                 FROM leagues l
                 INNER JOIN picks p ON p.league_id = l.id
                 WHERE p.deleted_at IS NULL
                 ORDER BY l.name ASC'
            );
            if (!empty($leagues)) {
                return $leagues;
            }

            return $this->db->fetchAll(
                'SELECT id, name, slug FROM leagues WHERE is_active = 1 ORDER BY name ASC'
            );
        }

        return [];
    }

    /**
     * Get available distinct season years from pick history.
     *
     * @return array<int, int>
     */
    public function getAvailableSeasons(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT YEAR(COALESCE(pr.recorded_at, p.updated_at, p.published_at)) AS season_year
             FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             WHERE p.deleted_at IS NULL
               AND (pr.recorded_at IS NOT NULL OR p.published_at IS NOT NULL)
             ORDER BY season_year DESC'
        );

        $years = [];
        foreach ($rows as $row) {
            if (!empty($row['season_year'])) {
                $years[] = (int) $row['season_year'];
            }
        }

        if (empty($years)) {
            $curr = (int) date('Y');
            $years = [$curr, $curr - 1, $curr - 2];
        }

        return array_values(array_unique($years));
    }

    public function summary(?string $range = 'all', ?string $season = null, ?string $league = null): array
    {
        $range = $range ?: 'all';
        [$start, $end] = $this->bounds($range);

        $where = [
            'p.deleted_at IS NULL',
            '(p.is_active = 1 OR p.is_active IS NULL)',
            "(p.status IN ('won','lost','push') OR pr.result IN ('won','lost','push'))",
        ];
        $params = [];

        if ($range === '7d' || $range === '30d' || $range === '90d') {
            if ($start && $end) {
                $where[] = 'COALESCE(pr.recorded_at, p.updated_at, p.published_at) BETWEEN :start AND :end';
                $params['start'] = $start;
                $params['end'] = $end;
            }
        }

        if (!empty($season)) {
            $where[] = 'YEAR(COALESCE(pr.recorded_at, p.updated_at, p.published_at)) = :season_year';
            $params['season_year'] = (int) $season;
        } elseif ($range === 'season') {
            $where[] = 'YEAR(COALESCE(pr.recorded_at, p.updated_at, p.published_at)) = :season_year';
            $params['season_year'] = (int) date('Y');
        }

        if (!empty($league)) {
            if (is_numeric($league)) {
                $where[] = 'p.league_id = :league_id';
                $params['league_id'] = (int) $league;
            } else {
                $where[] = 'l.slug = :league_slug';
                $params['league_slug'] = $league;
            }
        }

        $whereSql = implode(' AND ', $where);

        $cached = (empty($season) && empty($league)) ? $this->cached($range) : [];
        $row = $this->db->fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(p.status = 'won' OR pr.result = 'won') AS wins,
                SUM(p.status = 'lost' OR pr.result = 'lost') AS losses,
                SUM(p.status = 'push' OR pr.result = 'push') AS pushes,
                COALESCE(SUM(CASE
                    WHEN pr.units IS NOT NULL THEN pr.units
                    WHEN p.status = 'won' THEN COALESCE(p.units, 0)
                    WHEN p.status = 'lost' THEN -COALESCE(p.units, 0)
                    ELSE 0
                END), 0) AS units,
                COALESCE(AVG(p.confidence),0) AS avg_confidence
             FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             LEFT JOIN leagues l ON l.id = p.league_id
             WHERE {$whereSql}",
            $params
        ) ?? [];

        $decided = (int) ($row['wins'] ?? 0) + (int) ($row['losses'] ?? 0);
        $winRate = $decided > 0 ? round(((int) $row['wins'] / $decided) * 100, 2) : 0.0;
        $units = (float) ($row['units'] ?? 0);
        $roi = $decided > 0 ? round(($units / $decided) * 100, 2) : 0.0;

        $streaks = $this->streaks($whereSql, $params);
        $isDemo = empty($cached['synced_at']);

        return [
            'total' => (int) ($cached['total_bets'] ?? $row['total'] ?? 0),
            'wins' => (int) ($cached['wins'] ?? $row['wins'] ?? 0),
            'losses' => (int) ($cached['losses'] ?? $row['losses'] ?? 0),
            'pushes' => (int) ($cached['pushes'] ?? $row['pushes'] ?? 0),
            'win_rate' => (float) ($cached['win_rate'] ?? $winRate),
            'units' => (float) ($cached['units_won'] ?? $units),
            'roi' => (float) ($cached['roi_pct'] ?? $roi),
            'avg_confidence' => round((float) ($row['avg_confidence'] ?? 0), 1),
            'current_streak' => $streaks['current'],
            'best_streak' => $streaks['best'],
            'is_demo' => $isDemo,
            'synced_at' => $cached['synced_at'] ?? null,
            'range' => $range,
            'season' => $season,
            'league' => $league,
        ];
    }

    public function chartPayload(?string $range = 'all', ?string $season = null, ?string $league = null): array
    {
        $range = $range ?: 'all';
        [$start, $end] = $this->bounds($range);

        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if ($range === '7d' || $range === '30d' || $range === '90d') {
            if ($start && $end) {
                $where[] = 'COALESCE(pr.recorded_at, p.updated_at, p.published_at) BETWEEN :start AND :end';
                $params['start'] = $start;
                $params['end'] = $end;
            }
        }

        if (!empty($season)) {
            $where[] = 'YEAR(COALESCE(pr.recorded_at, p.updated_at, p.published_at)) = :season_year';
            $params['season_year'] = (int) $season;
        } elseif ($range === 'season') {
            $where[] = 'YEAR(COALESCE(pr.recorded_at, p.updated_at, p.published_at)) = :season_year';
            $params['season_year'] = (int) date('Y');
        }

        if (!empty($league)) {
            if (is_numeric($league)) {
                $where[] = 'p.league_id = :league_id';
                $params['league_id'] = (int) $league;
            } else {
                $where[] = 'l.slug = :league_slug';
                $params['league_slug'] = $league;
            }
        }

        $whereSql = implode(' AND ', $where);

        $rows = $this->db->fetchAll(
            "SELECT DATE(COALESCE(pr.recorded_at, p.published_at, p.created_at)) AS d,
                    pr.result, pr.units, s.name AS sport, l.name AS league
             FROM pick_results pr
             INNER JOIN picks p ON p.id = pr.pick_id
             INNER JOIN sports s ON s.id = p.sport_id
             LEFT JOIN leagues l ON l.id = p.league_id
             WHERE {$whereSql}
             ORDER BY COALESCE(pr.recorded_at, p.published_at, p.created_at) ASC",
            $params
        );

        $cumulative = [];
        $running = 0.0;
        $monthly = [];
        $wl = ['won' => 0, 'lost' => 0, 'push' => 0];
        $sports = [];
        $leagues = [];

        foreach ($rows as $row) {
            $running += (float) $row['units'];
            $cumulative[] = ['date' => $row['d'], 'units' => round($running, 2)];
            $month = substr((string) $row['d'], 0, 7);
            $monthly[$month] = ($monthly[$month] ?? 0) + (float) $row['units'];
            if (!empty($row['result'])) {
                $wl[$row['result']] = ($wl[$row['result']] ?? 0) + 1;
            }
            if (!empty($row['sport'])) {
                $sports[$row['sport']] = ($sports[$row['sport']] ?? 0) + 1;
            }
            if (!empty($row['league'])) {
                $leagues[$row['league']] = ($leagues[$row['league']] ?? 0) + 1;
            }
        }

        $monthSeries = [];
        foreach ($monthly as $label => $value) {
            $monthSeries[] = ['label' => $label, 'units' => round($value, 2)];
        }

        return [
            'cumulative' => $cumulative,
            'monthly' => $monthSeries,
            'distribution' => $wl,
            'sports' => $sports,
            'leagues' => $leagues,
            'demo' => (empty($season) && empty($league)) ? ($this->cached($range) === []) : true,
        ];
    }

    private function streaks(string $whereSql, array $params): array
    {
        $results = $this->db->fetchAll(
            "SELECT pr.result FROM pick_results pr
             INNER JOIN picks p ON p.id = pr.pick_id
             LEFT JOIN leagues l ON l.id = p.league_id
             WHERE {$whereSql} AND pr.result IN ('won','lost')
             ORDER BY pr.recorded_at ASC",
            $params
        );

        $best = 0;
        $run = 0;
        foreach ($results as $row) {
            if ($row['result'] === 'won') {
                $run++;
                $best = max($best, $run);
            } else {
                $run = 0;
            }
        }

        $current = 0;
        $sign = null;
        foreach (array_reverse($results) as $row) {
            if ($sign === null) {
                $sign = $row['result'];
            }
            if ($row['result'] !== $sign) {
                break;
            }
            $current++;
        }

        $currentLabel = $sign === 'lost' ? -$current : $current;

        return ['current' => $currentLabel, 'best' => $best];
    }

    /**
     * @return array<string,mixed>
     */
    private function cached(string $range): array
    {
        if (!$this->db->tableExists('performance_metrics') || !$this->db->columnExists('performance_metrics', 'synced_at')) {
            return [];
        }

        $period = match ($range) {
            '7d', '30d', '90d', 'season' => $range,
            default => 'all',
        };

        $row = $this->db->fetch(
            'SELECT * FROM performance_metrics
             WHERE period = :period AND (sport IS NULL OR sport = "")
               AND synced_at IS NOT NULL
             ORDER BY synced_at DESC LIMIT 1',
            ['period' => $period]
        );
        if ($row) {
            return $row;
        }

        if ($period === 'all') {
            return $this->db->fetch(
                'SELECT * FROM performance_metrics
                 WHERE synced_at IS NOT NULL AND (sport IS NULL OR sport = "")
                 ORDER BY synced_at DESC LIMIT 1'
            ) ?? [];
        }

        return [];
    }

    private function bounds(string $range): array
    {
        return match ($range) {
            '7d' => [date('Y-m-d 00:00:00', strtotime('-7 days')), date('Y-m-d 23:59:59')],
            '30d' => [date('Y-m-d 00:00:00', strtotime('-30 days')), date('Y-m-d 23:59:59')],
            '90d' => [date('Y-m-d 00:00:00', strtotime('-90 days')), date('Y-m-d 23:59:59')],
            'season' => [date('Y-01-01 00:00:00'), date('Y-m-d 23:59:59')],
            default => [null, null],
        };
    }
}
