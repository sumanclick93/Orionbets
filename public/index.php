<?php

declare(strict_types=1);

use App\Core\Application;

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';

$efHosts = function_exists('everflow_csp_hosts') ? everflow_csp_hosts() : '';
$scriptExtra = $efHosts !== '' ? ' ' . $efHosts : '';
$connectExtra = $efHosts !== '' ? ' ' . $efHosts : '';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 0');
$paypalHosts = ' https://www.paypal.com https://www.sandbox.paypal.com https://*.paypal.com https://www.paypalobjects.com https://*.paypalobjects.com https://c.paypal.com https://t.paypal.com';
$paypalConnect = ' https://www.paypal.com https://www.sandbox.paypal.com https://*.paypal.com https://www.paypalobjects.com https://*.paypalobjects.com https://c.paypal.com https://t.paypal.com https://api-m.paypal.com https://api-m.sandbox.paypal.com';
$paypalFrames = ' https://www.paypal.com https://www.sandbox.paypal.com https://*.paypal.com https://www.paypalobjects.com https://checkout.paypal.com';
header(
    "Content-Security-Policy: default-src 'self'; "
    . "img-src 'self' data: https:; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://upgrade.chat https://www.upgrade.chat{$paypalHosts}; "
    . "font-src 'self' https://fonts.gstatic.com data: https://upgrade.chat https://www.upgrade.chat; "
    . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://js.stripe.com https://upgrade.chat https://www.upgrade.chat{$paypalHosts}{$scriptExtra}; "
    . "script-src-elem 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://js.stripe.com https://upgrade.chat https://www.upgrade.chat{$paypalHosts}{$scriptExtra}; "
    . "connect-src 'self' https://api.upgrade.chat https://upgrade.chat https://www.upgrade.chat https://api.stripe.com https://js.stripe.com{$paypalConnect}{$connectExtra}; "
    . "frame-src 'self' https://upgrade.chat https://www.upgrade.chat https://js.stripe.com https://checkout.stripe.com https://hooks.stripe.com https://pay.stripe.com{$paypalFrames}; "
    . "child-src 'self' blob:{$paypalFrames}; "
    . "worker-src 'self' blob:{$paypalHosts}; "
    . "frame-ancestors 'self'"
);

if ($app->request->path() === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

(new \App\Middleware\GeoBlockMiddleware())->handle($app->request, $app);

$app->run();
