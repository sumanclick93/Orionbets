<?php

declare(strict_types=1);

/**
 * OrionBets - Action Network Cron Sync Script
 * 
 * Scheduled to run 3 times a week at midnight (Sunday, Wednesday, Friday).
 * - Executes full Action Network synchronization (Scoreboard, Picks, Performance).
 * - Every Sunday at midnight, purges previous week's logs from `action_network_sync_logs`.
 * 
 * CLI Usage:
 *   php /path/to/cron_sync.php
 * 
 * Web Usage (Optional secret key via ?key=YOUR_CRON_SECRET):
 *   https://orionbets.co/cron_sync.php?key=YOUR_CRON_SECRET
 */

// 1. Determine base path and load bootstrap
$basePath = __DIR__;
if (!file_exists($basePath . '/app/bootstrap.php')) {
    $basePath = dirname(__DIR__);
}

$app = require $basePath . '/app/bootstrap.php';

use App\Core\Env;
use App\Services\ActionNetworkService;
use App\Setup\Schema;

// 2. Web Security Check (if called via web browser / URL)
if (php_sapi_name() !== 'cli') {
    $cronSecret = Env::get('CRON_SECRET', '');
    if ($cronSecret !== '') {
        $providedKey = (string) ($_GET['key'] ?? '');
        if (!hash_equals($cronSecret, $providedKey)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Unauthorized cron request']);
            exit(1);
        }
    }
}

// 3. Ensure DB tables exist
Schema::tryEnsure($app->db);

// 4. Perform full sync (same functionality as admin dashboard)
$syncService = ActionNetworkService::make($app->db);
$result = $syncService->syncAll('cron');

$logsDeleted = 0;
$isSunday = ((int) date('w') === 0); // 0 = Sunday

// 5. Sunday Log Cleanup (Delete previous week's log entries from `action_network_sync_logs`)
if ($isSunday) {
    try {
        // Delete log records older than 7 days from action_network_sync_logs table
        $stmt = $app->db->pdo()->prepare(
            "DELETE FROM action_network_sync_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stmt->execute();
        $logsDeleted = $stmt->rowCount();
    } catch (\Throwable $e) {
        $logsDeleted = -1;
    }
}

// 6. Output Summary
$output = [
    'timestamp' => date('Y-m-d H:i:s'),
    'day' => date('l'),
    'sync_status' => $result['ok'] ? 'success' : 'completed_with_errors',
    'items_synced' => (int) ($result['items'] ?? 0),
    'errors' => $result['errors'] ?? [],
    'sunday_log_cleanup' => [
        'executed' => $isSunday,
        'logs_deleted' => $logsDeleted,
    ],
];

if (php_sapi_name() === 'cli') {
    echo "[" . $output['timestamp'] . "] Action Network Cron Sync Completed (" . $output['day'] . ")\n";
    echo "Status: " . $output['sync_status'] . "\n";
    echo "Items Synced: " . $output['items_synced'] . "\n";
    if (!empty($output['errors'])) {
        echo "Errors: " . implode(', ', $output['errors']) . "\n";
    }
    if ($output['sunday_log_cleanup']['executed']) {
        echo "Sunday Cleanup: Purged " . $output['sunday_log_cleanup']['logs_deleted'] . " old entries from action_network_sync_logs\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);
}
