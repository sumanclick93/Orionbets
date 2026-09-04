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
     * Get available active leagues from database, pick history, or events.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableLeagues(): array
    {
        if ($this->db->tableExists('leagues')) {
            $leagues = $this->db->fetchAll(
                'SELECT DISTINCT l.id, l.name, l.slug
                 FROM leagues l
                 LEFT JOIN picks p ON (p.league_id = l.id OR p.league = l.slug)
                 LEFT JOIN events e ON (e.league_id = l.id)
                 WHERE l.is_active = 1 OR p.id IS NOT NULL OR e.id IS NOT NULL
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
     * Get available distinct season years from pick history or events.
     *
     * @return array<int, int>
     */
    public function getAvailableSeasons(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT YEAR(COALESCE(pr.recorded_at, p.updated_at, p.published_at, e.start_time, e.event_at)) AS season_year
             FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             LEFT JOIN events e ON e.id = p.event_id
             WHERE p.deleted_at IS NULL
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
            [$leagueClause, $leagueParams] = $this->resolveLeagueFilter((string) $league);
            $where[] = $leagueClause;
            $params = array_merge($params, $leagueParams);
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
             LEFT JOIN sports s ON s.id = p.sport_id
             WHERE {$whereSql}",
            $params
        ) ?? [];

        $total = (int) ($cached['total_bets'] ?? $row['total'] ?? 0);
        $wins = (int) ($cached['wins'] ?? $row['wins'] ?? 0);
        $losses = (int) ($cached['losses'] ?? $row['losses'] ?? 0);
        $pushes = (int) ($cached['pushes'] ?? $row['pushes'] ?? 0);
        $decided = $wins + $losses;
        $winRate = $decided > 0 ? round(($wins / $decided) * 100, 2) : (float) ($cached['win_rate'] ?? 0.0);
        $units = (float) ($cached['units_won'] ?? $row['units'] ?? 0);
        $roi = $decided > 0 ? round(($units / $decided) * 100, 2) : (float) ($cached['roi_pct'] ?? 0.0);

        $streaks = $this->streaks($whereSql, $params);
        $isDemo = empty($cached['synced_at']) && $total === 0;

        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'pushes' => $pushes,
            'win_rate' => $winRate,
            'units' => $units,
            'roi' => $roi,
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
            [$leagueClause, $leagueParams] = $this->resolveLeagueFilter((string) $league);
            $where[] = $leagueClause;
            $params = array_merge($params, $leagueParams);
        }

        $whereSql = implode(' AND ', $where);

        $rows = $this->db->fetchAll(
            "SELECT DATE(COALESCE(pr.recorded_at, p.published_at, p.created_at)) AS d,
                    COALESCE(pr.result, p.status) AS result,
                    COALESCE(pr.units, CASE WHEN p.status = 'won' THEN COALESCE(p.units, 0) WHEN p.status = 'lost' THEN -COALESCE(p.units, 0) ELSE 0 END) AS units,
                    COALESCE(s.name, UPPER(p.sport), 'Other') AS sport,
                    COALESCE(l.name, UPPER(p.league), 'Other') AS league
             FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             LEFT JOIN sports s ON s.id = p.sport_id
             LEFT JOIN leagues l ON l.id = p.league_id
             WHERE {$whereSql} AND (p.status IN ('won','lost','push') OR pr.result IN ('won','lost','push'))
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
            'demo' => (empty($season) && empty($league)) ? ($this->cached($range) === [] && $rows === []) : ($rows === []),
        ];
    }

    private function resolveLeagueFilter(string $league): array
    {
        $leagueRow = null;
        if (is_numeric($league)) {
            $leagueRow = $this->db->fetch('SELECT id, slug FROM leagues WHERE id = :id LIMIT 1', ['id' => (int) $league]);
        }
        if (!$leagueRow) {
            $leagueRow = $this->db->fetch('SELECT id, slug FROM leagues WHERE slug = :slug LIMIT 1', ['slug' => strtolower(trim($league))]);
        }

        if ($leagueRow) {
            $clause = '(p.league_id = :lid1 OR p.league = :lslug1 OR l.id = :lid2 OR l.slug = :lslug2 OR s.slug = :lslug3)';
            $params = [
                'lid1' => (int) $leagueRow['id'],
                'lslug1' => (string) $leagueRow['slug'],
                'lid2' => (int) $leagueRow['id'],
                'lslug2' => (string) $leagueRow['slug'],
                'lslug3' => (string) $leagueRow['slug'],
            ];
        } else {
            $clause = '(p.league_id = :league_val OR p.league = :league_str1 OR l.slug = :league_str2 OR s.slug = :league_str3)';
            $params = [
                'league_val' => is_numeric($league) ? (int) $league : 0,
                'league_str1' => strtolower(trim($league)),
                'league_str2' => strtolower(trim($league)),
                'league_str3' => strtolower(trim($league)),
            ];
        }

        return [$clause, $params];
    }

    private function streaks(string $whereSql, array $params): array
    {
        $results = $this->db->fetchAll(
            "SELECT COALESCE(pr.result, p.status) AS result FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             LEFT JOIN leagues l ON l.id = p.league_id
             LEFT JOIN sports s ON s.id = p.sport_id
             WHERE {$whereSql} AND (pr.result IN ('won','lost') OR p.status IN ('won','lost'))
             ORDER BY COALESCE(pr.recorded_at, p.published_at, p.created_at) ASC",
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
