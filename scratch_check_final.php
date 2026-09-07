<?php
$app = require __DIR__ . '/app/bootstrap.php';
$db = $app->db;

$picksCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM picks");
echo "Picks count: {$picksCount}\n";

$metrics = $db->fetchAll("SELECT period, sport, total_bets, wins, losses, pushes, win_rate, roi, units FROM performance_metrics");
echo "Performance Metrics:\n" . json_encode($metrics, JSON_PRETTY_PRINT) . "\n";
