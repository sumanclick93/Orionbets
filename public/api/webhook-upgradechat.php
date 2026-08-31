<?php

declare(strict_types=1);

use App\Controllers\WebhookController;
use App\Setup\Schema;

$app = require dirname(__DIR__, 2) . '/app/bootstrap.php';
Schema::tryEnsure($app->db);

$controller = new WebhookController($app);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
    $controller->upgradeChatPing();
}

$controller->upgradeChat();
