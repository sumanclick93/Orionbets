<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/Views');

require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

require APP_PATH . '/Helpers/functions.php';

Env::loadFirst(Env::candidatePaths());

date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC'));

if (Env::get('APP_DEBUG', 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

$app = Application::boot();

return $app;
