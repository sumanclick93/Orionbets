<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=edgeplay', 'root', '');
$now = date('Y-m-d H:i:s');

$rows = [
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
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'mlb', 'roi_pct' => 6.80, 'roi' => 6.80, 'units_won' => 21.50, 'units' => 21.50, 'total_bets' => 316, 'total_picks' => 316, 'wins' => 161, 'losses' => 128, 'pushes' => 27, 'win_rate' => 55.71, 'is_demo' => 0, 'synced_at' => $now],
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'ncaab', 'roi_pct' => 8.10, 'roi' => 8.10, 'units_won' => 21.20, 'units' => 21.20, 'total_bets' => 262, 'total_picks' => 262, 'wins' => 143, 'losses' => 108, 'pushes' => 11, 'win_rate' => 56.97, 'is_demo' => 0, 'synced_at' => $now],
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'nba', 'roi_pct' => 7.50, 'roi' => 7.50, 'units_won' => 12.80, 'units' => 12.80, 'total_bets' => 170, 'total_picks' => 170, 'wins' => 93, 'losses' => 76, 'pushes' => 1, 'win_rate' => 55.03, 'is_demo' => 0, 'synced_at' => $now],
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'ncaaf', 'roi_pct' => 18.70, 'roi' => 18.70, 'units_won' => 15.00, 'units' => 15.00, 'total_bets' => 80, 'total_picks' => 80, 'wins' => 47, 'losses' => 28, 'pushes' => 5, 'win_rate' => 62.67, 'is_demo' => 0, 'synced_at' => $now],
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'nfl', 'roi_pct' => 24.10, 'roi' => 24.10, 'units_won' => 14.00, 'units' => 14.00, 'total_bets' => 58, 'total_picks' => 58, 'wins' => 36, 'losses' => 17, 'pushes' => 5, 'win_rate' => 67.92, 'is_demo' => 0, 'synced_at' => $now],
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'wnba', 'roi_pct' => -2.40, 'roi' => -2.40, 'units_won' => -1.00, 'units' => -1.00, 'total_bets' => 41, 'total_picks' => 41, 'wins' => 20, 'losses' => 21, 'pushes' => 0, 'win_rate' => 48.78, 'is_demo' => 0, 'synced_at' => $now],
    ['period' => 'all', 'period_type' => 'all', 'sport' => 'nhl', 'roi_pct' => -33.30, 'roi' => -33.30, 'units_won' => -1.00, 'units' => -1.00, 'total_bets' => 3, 'total_picks' => 3, 'wins' => 1, 'losses' => 2, 'pushes' => 0, 'win_rate' => 33.33, 'is_demo' => 0, 'synced_at' => $now],
];

foreach ($rows as $r) {
    $sport = $r['sport'];
    $stmt = $pdo->prepare('SELECT id FROM performance_metrics WHERE period = "all" AND ' . ($sport ? 'sport = :s' : '(sport IS NULL OR sport = "")') . ' LIMIT 1');
    if ($sport) {
        $stmt->execute(['s' => $sport]);
    } else {
        $stmt->execute();
    }
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $sql = 'UPDATE performance_metrics SET period_type=:period_type, roi_pct=:roi_pct, roi=:roi, units_won=:units_won, units=:units, total_bets=:total_bets, total_picks=:total_picks, wins=:wins, losses=:losses, pushes=:pushes, win_rate=:win_rate, is_demo=:is_demo, synced_at=:synced_at WHERE id = ' . $existing['id'];
        $up = $pdo->prepare($sql);
        $up->execute([
            'period_type' => $r['period_type'],
            'roi_pct' => $r['roi_pct'],
            'roi' => $r['roi'],
            'units_won' => $r['units_won'],
            'units' => $r['units'],
            'total_bets' => $r['total_bets'],
            'total_picks' => $r['total_picks'],
            'wins' => $r['wins'],
            'losses' => $r['losses'],
            'pushes' => $r['pushes'],
            'win_rate' => $r['win_rate'],
            'is_demo' => $r['is_demo'],
            'synced_at' => $r['synced_at'],
        ]);
    } else {
        $sql = 'INSERT INTO performance_metrics (period, period_type, sport, roi_pct, roi, units_won, units, total_bets, total_picks, wins, losses, pushes, win_rate, is_demo, synced_at) VALUES (:period, :period_type, :sport, :roi_pct, :roi, :units_won, :units, :total_bets, :total_picks, :wins, :losses, :pushes, :win_rate, :is_demo, :synced_at)';
        $ins = $pdo->prepare($sql);
        $ins->execute($r);
    }
}

echo "Successfully seeded performance_metrics table!\n";
