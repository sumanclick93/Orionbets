<?php

declare(strict_types=1);

/**
 * OrionBets - Public Cron Entrypoint
 * Forwards web cron requests to root cron_sync.php script.
 */

require dirname(__DIR__) . '/cron_sync.php';
