<?php
$app = require __DIR__ . '/app/bootstrap.php';
$db = $app->db;

$activeCounts = $db->fetchAll("SELECT is_active, is_published, status, COUNT(*) as cnt FROM picks GROUP BY is_active, is_published, status");
echo "Picks by is_active / is_published / status:\n" . json_encode($activeCounts, JSON_PRETTY_PRINT) . "\n";
