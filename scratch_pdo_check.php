<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=edgeplay', 'root', '');

echo "=== PERFORMANCE METRICS ===\n";
$stmt = $pdo->query('SELECT * FROM performance_metrics');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== PICKS TABLE ===\n";
$stmt2 = $pdo->query('SELECT id, action_network_pick_id, title, status FROM picks');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
