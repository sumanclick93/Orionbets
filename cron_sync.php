<?php

declare(strict_types=1);

/**
 * OrionBets - Action Network Cron Sync Script
 * 
 * Scheduled to run every 2 minutes via server cron or web runner.
 * - Frequent Run (Default / action=live): Syncs active picks (pages 1-2), today's & yesterday's scoreboards, performance metrics.
 * - Full Historical Backfill Run (action=backfill or --backfill): Full historical multi-page sync across all picks (~934 records) and dates.
 * - Concurrency Protections: set_time_limit(300), ignore_user_abort(true), and file execution lock (storage/locks/cron_sync.lock).
 * 
 * CLI Usage:
 *   php /path/to/cron_sync.php
 *   php /path/to/cron_sync.php --backfill
 * 
 * Web Usage (Optional secret key via ?key=YOUR_CRON_SECRET):
 *   https://orionbets.co/cron_sync.php?key=YOUR_CRON_SECRET
 *   https://orionbets.co/cron_sync.php?key=YOUR_CRON_SECRET&action=backfill
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

// 2. Web Security Check & Environment Setup
$isCli = (php_sapi_name() === 'cli');
@set_time_limit($isCli ? 0 : 300);
@ignore_user_abort(true);
$startTime = microtime(true);

if (!$isCli) {
    $cronSecret = Env::get('CRON_SECRET', '');
    if ($cronSecret !== '') {
        $providedKey = (string) ($_GET['key'] ?? '');
        if (!hash_equals($cronSecret, $providedKey)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Unauthorized cron request'], JSON_PRETTY_PRINT);
            exit(1);
        }
    }
}

// 4. Determine Execution Mode / Tier
$isBackfill = false;
if ($isCli) {
    global $argv;
    $args = $argv ?? [];
    foreach ($args as $arg) {
        if (in_array(strtolower((string) $arg), ['--backfill', '-b', 'backfill'], true)) {
            $isBackfill = true;
            break;
        }
    }
} else {
    $action = strtolower((string) ($_GET['action'] ?? ''));
    if ($action === 'backfill') {
        $isBackfill = true;
    }
}

$mode = $isBackfill ? 'backfill' : 'live';

// 5. File-Based Execution Locking (TTL = 180s)
$lockDir = $basePath . '/storage/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0777, true);
}
$lockFile = $lockDir . '/cron_sync.lock';
$lockTtl = 180;

if (file_exists($lockFile)) {
    $mtime = @filemtime($lockFile);
    if ($mtime !== false && (time() - $mtime) < $lockTtl) {
        $lockedResponse = [
            'ok' => false,
            'status' => 'already_running',
            'mode' => $mode,
            'message' => 'Another cron sync process is currently active.',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        if (!$isCli) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode($lockedResponse, JSON_PRETTY_PRINT);
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] NOTICE: Sync process locked. Another cron job is currently running.\n";
        }
        exit(0);
    }
}

// Create lock file with current process ID
@file_put_contents($lockFile, (string) getmypid());
register_shutdown_function(static function () use ($lockFile): void {
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
});

// 6. Ensure Database Tables Exist
Schema::tryEnsure($app->db);

// 7. Perform Sync Execution Based on Mode
$syncService = ActionNetworkService::make($app->db);
$errors = [];
$itemsSynced = 0;
$inserted = 0;
$updated = 0;

if ($isBackfill) {
    // Full Historical Backfill Run
    $result = $syncService->backfillHistorical(730, 'backfill');
    $itemsSynced = (int) ($result['items'] ?? 0);
    $inserted = (int) ($result['inserted'] ?? 0);
    $updated = (int) ($result['updated'] ?? 0);
    if (!empty($result['errors'])) {
        $errors = (array) $result['errors'];
    }
} else {
    // Frequent Live Run (Every 2 minutes)
    // 1) Fast pick sync (poll pages 1 & 2 for live updates & grading changes)
    $pickSync = $syncService->syncPicks(1, 50, 'cron', true);
    $itemsSynced += (int) ($pickSync['items'] ?? 0);
    $inserted += (int) ($pickSync['inserted'] ?? 0);
    $updated += (int) ($pickSync['updated'] ?? 0);
    if (!empty($pickSync['error'])) {
        $errors[] = (string) $pickSync['error'];
    }

    // 2) Scoreboard for yesterday + today
    foreach ([date('Ymd', strtotime('-1 day')), date('Ymd')] as $date) {
        $scoreboard = $syncService->syncScoreboard($date, 'cron');
        $itemsSynced += (int) ($scoreboard['items'] ?? 0);
        if (!empty($scoreboard['error'])) {
            $errors[] = (string) $scoreboard['error'];
        }
    }

    // 3) Update overall performance metrics
    $perf = $syncService->syncPerformance('cron');
    $itemsSynced += (int) ($perf['items'] ?? 0);
    if (!empty($perf['error'])) {
        $errors[] = (string) $perf['error'];
    }
}

// 8. Sunday Log Cleanup (Purge log entries older than 7 days)
$logsDeleted = 0;
$isSunday = ((int) date('w') === 0);
if ($isSunday) {
    try {
        $stmt = $app->db->pdo()->prepare(
            "DELETE FROM action_network_sync_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stmt->execute();
        $logsDeleted = (int) $stmt->rowCount();
    } catch (\Throwable $e) {
        $logsDeleted = -1;
    }
}

$durationMs = (int) round((microtime(true) - $startTime) * 1000);

// 9. Output Summary
$output = [
    'ok' => $errors === [],
    'status' => $errors === [] ? 'success' : 'completed_with_errors',
    'mode' => $mode,
    'time_ms' => $durationMs,
    'items_synced' => $itemsSynced,
    'inserted' => $inserted,
    'updated' => $updated,
    'errors' => $errors,
    'sunday_log_cleanup' => [
        'executed' => $isSunday,
        'logs_deleted' => $logsDeleted,
    ],
    'timestamp' => date('Y-m-d H:i:s'),
];

// Clean up lock file explicitly before outputting
if (file_exists($lockFile)) {
    @unlink($lockFile);
}

if ($isCli) {
    echo "[" . $output['timestamp'] . "] Action Network Cron Sync Completed (Mode: {$mode})\n";
    echo "Status: " . $output['status'] . "\n";
    echo "Time: " . $output['time_ms'] . "ms\n";
    echo "Items Synced: " . $output['items_synced'] . " (Inserted: {$inserted}, Updated: {$updated})\n";
    if (!empty($output['errors'])) {
        echo "Errors: " . implode(', ', $output['errors']) . "\n";
    }
    if ($output['sunday_log_cleanup']['executed']) {
        echo "Sunday Log Cleanup: Purged " . $output['sunday_log_cleanup']['logs_deleted'] . " entries.\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);
}
