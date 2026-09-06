<?php

declare(strict_types=1);

use App\Services\ActionNetworkService;
use App\Setup\Installer;
use App\Setup\Schema;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This command must be run from the CLI.\n");
    exit(1);
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'migrate':
        echo Installer::migrate() . PHP_EOL;
        break;
    case 'seed':
        echo Installer::seed() . PHP_EOL;
        echo "Seed complete.\n";
        break;
    case 'setup':
        foreach (Installer::setup() as $line) {
            echo $line . PHP_EOL;
        }
        echo "Seed complete.\n";
        break;
    case 'action-network:sync':
        Schema::tryEnsure($app->db);
        $result = ActionNetworkService::make($app->db)->syncAll('cron');
        echo $result['ok'] ? "Action Network sync complete.\n" : "Action Network sync finished with errors.\n";
        echo 'Records upserted: ' . (int) $result['items'] . PHP_EOL;
        foreach ($result['errors'] as $error) {
            fwrite(STDERR, $error . PHP_EOL);
        }
        exit($result['ok'] ? 0 : 1);
    case 'action-network:backfill':
        Schema::tryEnsure($app->db);
        $days = 365;
        foreach (array_slice($argv, 2) as $arg) {
            if (str_starts_with((string) $arg, '--days=')) {
                $days = max(1, (int) substr((string) $arg, 7));
            }
        }
        echo "Backfilling {$days} day(s) from Action Network...\n";
        $result = ActionNetworkService::make($app->db)->backfillHistorical($days, 'backfill');
        echo $result['ok'] ? "Backfill complete.\n" : "Backfill finished with errors.\n";
        echo 'Records upserted: ' . (int) $result['items'];
        if (isset($result['inserted']) || isset($result['updated'])) {
            echo ' (Inserted: ' . (int) ($result['inserted'] ?? 0) . ', Updated: ' . (int) ($result['updated'] ?? 0) . ')';
        }
        echo PHP_EOL;
        foreach ($result['errors'] as $error) {
            fwrite(STDERR, $error . PHP_EOL);
        }
        exit($result['ok'] || (int) $result['items'] > 0 ? 0 : 1);
    default:
        echo "Orion Bets console\n";
        echo "  php bin/console.php migrate\n";
        echo "  php bin/console.php seed\n";
        echo "  php bin/console.php setup\n";
        echo "  php bin/console.php action-network:sync\n";
        echo "  php bin/console.php action-network:backfill --days=365\n";
}
