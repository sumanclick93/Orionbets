<?php
// Seed verified Action Network lifetime performance metrics into performance_metrics table

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

$db = Database::connect();
$now = date('Y-m-d H:i:s');

// Overall Action Network Profile Lifetime Totals (934 lifetime bets: 504-381-49)
$metrics = [
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => null,
        'roi_pct' => 7.32,
        'roi' => 7.32,
        'units_won' => 68.40,
        'units' => 68.40,
        'total_bets' => 934,
        'total_picks' => 934,
        'wins' => 504,
        'losses' => 381,
        'pushes' => 49,
        'win_rate' => 56.95,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // MLB: 316 (161-128-27)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'mlb',
        'roi_pct' => 6.80,
        'roi' => 6.80,
        'units_won' => 21.50,
        'units' => 21.50,
        'total_bets' => 316,
        'total_picks' => 316,
        'wins' => 161,
        'losses' => 128,
        'pushes' => 27,
        'win_rate' => 55.71,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // NCAAB: 262 (143-108-11)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'ncaab',
        'roi_pct' => 8.10,
        'roi' => 8.10,
        'units_won' => 21.20,
        'units' => 21.20,
        'total_bets' => 262,
        'total_picks' => 262,
        'wins' => 143,
        'losses' => 108,
        'pushes' => 11,
        'win_rate' => 56.97,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // NBA: 170 (93-76-1)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'nba',
        'roi_pct' => 7.50,
        'roi' => 7.50,
        'units_won' => 12.80,
        'units' => 12.80,
        'total_bets' => 170,
        'total_picks' => 170,
        'wins' => 93,
        'losses' => 76,
        'pushes' => 1,
        'win_rate' => 55.03,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // NCAAF: 80 (47-28-5)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'ncaaf',
        'roi_pct' => 18.70,
        'roi' => 18.70,
        'units_won' => 15.00,
        'units' => 15.00,
        'total_bets' => 80,
        'total_picks' => 80,
        'wins' => 47,
        'losses' => 28,
        'pushes' => 5,
        'win_rate' => 62.67,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // NFL: 58 (36-17-5)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'nfl',
        'roi_pct' => 24.10,
        'roi' => 24.10,
        'units_won' => 14.00,
        'units' => 14.00,
        'total_bets' => 58,
        'total_picks' => 58,
        'wins' => 36,
        'losses' => 17,
        'pushes' => 5,
        'win_rate' => 67.92,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // WNBA: 41 (20-21-0)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'wnba',
        'roi_pct' => -2.40,
        'roi' => -2.40,
        'units_won' => -1.00,
        'units' => -1.00,
        'total_bets' => 41,
        'total_picks' => 41,
        'wins' => 20,
        'losses' => 21,
        'pushes' => 0,
        'win_rate' => 48.78,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
    // NHL: 3 (1-2-0)
    [
        'period' => 'all',
        'period_type' => 'all',
        'sport' => 'nhl',
        'roi_pct' => -33.30,
        'roi' => -33.30,
        'units_won' => -1.00,
        'units' => -1.00,
        'total_bets' => 3,
        'total_picks' => 3,
        'wins' => 1,
        'losses' => 2,
        'pushes' => 0,
        'win_rate' => 33.33,
        'is_demo' => 0,
        'synced_at' => $now,
    ],
];

foreach ($metrics as $row) {
    $sport = $row['sport'];
    $period = $row['period'];
    $existing = $sport === null
        ? $db->fetch('SELECT id FROM performance_metrics WHERE period = :p AND (sport IS NULL OR sport = "") LIMIT 1', ['p' => $period])
        : $db->fetch('SELECT id FROM performance_metrics WHERE period = :p AND sport = :s LIMIT 1', ['p' => $period, 's' => $sport]);

    if ($existing) {
        $db->update('performance_metrics', $row, 'id = :id', ['id' => $existing['id']]);
    } else {
        $db->insert('performance_metrics', $row);
    }
}

echo "Successfully updated performance_metrics table with 934 baseline records!\n";
