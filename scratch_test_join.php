<?php
$app = require __DIR__ . '/app/bootstrap.php';
$db = $app->db;

echo "=== SPORTS TABLE ===\n";
$sports = $db->fetchAll("SELECT * FROM sports");
echo json_encode($sports, JSON_PRETTY_PRINT) . "\n";

echo "=== LEAGUES TABLE ===\n";
$leagues = $db->fetchAll("SELECT * FROM leagues");
echo json_encode($leagues, JSON_PRETTY_PRINT) . "\n";

echo "=== SAMPLE PICKS SPORT_ID / LEAGUE_ID ===\n";
$samplePicks = $db->fetchAll("SELECT id, sport_id, league_id, sport, league, status FROM picks LIMIT 10");
echo json_encode($samplePicks, JSON_PRETTY_PRINT) . "\n";

echo "=== TESTING PickRepository::select() ===\n";
$repo = new \App\Repositories\PickRepository($db);
$recent = $repo->recentResults(8);
echo "recentResults count: " . count($recent) . "\n";
if (count($recent) > 0) {
    echo "First recent result: " . json_encode($recent[0], JSON_PRETTY_PRINT) . "\n";
}
