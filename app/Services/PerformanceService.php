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
     * Whitelisted to the 7 covered sports with clean acronyms: NFL, NCAAF, NBA, NCAAB, MLB, NHL, WNBA.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableLeagues(): array
    {
        $allowed = [
            'nfl' => 'NFL',
            'ncaaf' => 'NCAAF',
            'nba' => 'NBA',
            'ncaab' => 'NCAAB',
            'mlb' => 'MLB',
            'nhl' => 'NHL',
            'wnba' => 'WNBA',
        ];

        $results = [];
        foreach ($allowed as $slug => $acronym) {
            $results[] = [
                'id' => $slug,
                'slug' => $slug,
                'name' => $acronym,
            ];
        }

        return $results;
    }

    /**
     * Get available distinct season years based on sports campaign rules.
     *
     * @return array<int, int>
     */
    public function getAvailableSeasons(): array
    {
        $seasonExpr = $this->seasonSql('COALESCE(pr.recorded_at, p.published_at, p.created_at, e.start_time, e.event_at)');
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT {$seasonExpr} AS season_year
             FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             LEFT JOIN sports s ON s.id = p.sport_id
             LEFT JOIN leagues l ON l.id = p.league_id
             LEFT JOIN events e ON e.id = p.event_id
             WHERE p.deleted_at IS NULL
             ORDER BY season_year DESC"
        );

        $years = [];
        foreach ($rows as $row) {
            if (!empty($row['season_year'])) {
                $years[] = (int) $row['season_year'];
            }
        }

        if (!in_array(2025, $years, true)) {
            $years[] = 2025;
        }
        if (!in_array(2026, $years, true)) {
            $years[] = 2026;
        }
        rsort($years);

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

        $seasonExpr = $this->seasonSql('COALESCE(pr.recorded_at, p.published_at, p.created_at)');
        if (!empty($season)) {
            $where[] = "{$seasonExpr} = :season_year";
            $params['season_year'] = (int) $season;
        } elseif ($range === 'season') {
            $where[] = "{$seasonExpr} = :season_year";
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

        $seasonExpr = $this->seasonSql('COALESCE(pr.recorded_at, p.published_at, p.created_at)');
        if (!empty($season)) {
            $where[] = "{$seasonExpr} = :season_year";
            $params['season_year'] = (int) $season;
        } elseif ($range === 'season') {
            $where[] = "{$seasonExpr} = :season_year";
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
                $sName = $this->normalizeLeagueName((string) $row['sport']);
                $sports[$sName] = ($sports[$sName] ?? 0) + 1;
            }
            if (!empty($row['league'])) {
                $lName = $this->normalizeLeagueName((string) $row['league']);
                $leagues[$lName] = ($leagues[$lName] ?? 0) + 1;
            }
        }

        if (empty($season) && empty($league) && $this->db->tableExists('performance_metrics')) {
            $metricRows = $this->db->fetchAll('SELECT * FROM performance_metrics WHERE period = "all" AND synced_at IS NOT NULL');
            foreach ($metricRows as $m) {
                if (empty($m['sport'])) {
                    if (($wl['won'] ?? 0) === 0 && ($wl['lost'] ?? 0) === 0) {
                        $wl['won'] = (int) ($m['wins'] ?? 0);
                        $wl['lost'] = (int) ($m['losses'] ?? 0);
                        $wl['push'] = (int) ($m['pushes'] ?? 0);
                    }
                } else {
                    $sName = $this->normalizeLeagueName((string) $m['sport']);
                    if (!isset($sports[$sName])) {
                        $sports[$sName] = (int) ($m['wins'] ?? $m['total_bets'] ?? 0);
                    }
                    if (!isset($leagues[$sName])) {
                        $leagues[$sName] = (int) ($m['wins'] ?? $m['total_bets'] ?? 0);
                    }
                }
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

    private function seasonSql(string $dateCol = 'COALESCE(pr.recorded_at, p.published_at, p.created_at)'): string
    {
        return "(CASE
            WHEN LOWER(COALESCE(s.slug, p.sport, l.slug, '')) IN ('nfl', 'ncaaf', 'football') THEN
                CASE WHEN MONTH({$dateCol}) IN (1, 2) THEN YEAR({$dateCol}) - 1 ELSE YEAR({$dateCol}) END
            WHEN LOWER(COALESCE(s.slug, p.sport, l.slug, '')) IN ('nba', 'ncaab', 'nhl', 'basketball', 'hockey') THEN
                CASE WHEN MONTH({$dateCol}) BETWEEN 1 AND 7 THEN YEAR({$dateCol}) - 1 ELSE YEAR({$dateCol}) END
            ELSE
                YEAR({$dateCol})
        END)";
    }

    private function normalizeLeagueName(string $raw): string
    {
        $clean = strtolower(trim($raw));
        return match ($clean) {
            'nfl', 'national football league' => 'NFL',
            'ncaaf', 'college football' => 'NCAAF',
            'nba', 'national basketball association' => 'NBA',
            'ncaab', 'college basketball' => 'NCAAB',
            'mlb', 'major league baseball' => 'MLB',
            'nhl', 'national hockey league' => 'NHL',
            'wnba', 'women\'s national basketball association' => 'WNBA',
            default => strtoupper($raw),
        };
    }

    private function resolveLeagueFilter(string $league): array
    {
        $cleanLeague = strtolower(trim($league));
        $upperLeague = strtoupper(trim($league));

        $leagueRow = null;
        if (is_numeric($league)) {
            $leagueRow = $this->db->fetch('SELECT id, slug FROM leagues WHERE id = :id LIMIT 1', ['id' => (int) $league]);
        }
        if (!$leagueRow) {
            $leagueRow = $this->db->fetch('SELECT id, slug FROM leagues WHERE slug = :slug LIMIT 1', ['slug' => $cleanLeague]);
        }

        if ($leagueRow) {
            $clause = '(p.league_id = :lid1 OR LOWER(p.league) = :lslug1 OR UPPER(p.league) = :lslug_upper1 OR l.id = :lid2 OR LOWER(l.slug) = :lslug2 OR LOWER(s.slug) = :lslug3)';
            $params = [
                'lid1' => (int) $leagueRow['id'],
                'lslug1' => $cleanLeague,
                'lslug_upper1' => $upperLeague,
                'lid2' => (int) $leagueRow['id'],
                'lslug2' => $cleanLeague,
                'lslug3' => $cleanLeague,
            ];
        } else {
            $clause = '(p.league_id = :league_val OR LOWER(p.league) = :league_str1 OR UPPER(p.league) = :league_str_upper OR LOWER(l.slug) = :league_str2 OR LOWER(s.slug) = :league_str3)';
            $params = [
                'league_val' => is_numeric($league) ? (int) $league : 0,
                'league_str1' => $cleanLeague,
                'league_str_upper' => $upperLeague,
                'league_str2' => $cleanLeague,
                'league_str3' => $cleanLeague,
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
