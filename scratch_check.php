<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

$db = Database::connect();
$metrics = $db->fetchAll('SELECT * FROM performance_metrics');
echo "Performance Metrics in DB:\n";
print_r($metrics);

$picks = $db->fetchAll('SELECT id, action_network_pick_id, title, status FROM picks');
echo "\nPicks in DB (" . count($picks) . " total):\n";
print_r($picks);
