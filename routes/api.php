<?php

declare(strict_types=1);

use App\Controllers\Api\ApiController;

/** @var \App\Core\Router $router */

$router->get('/api/picks', [ApiController::class, 'picks']);
$router->get('/api/picks/{id}', [ApiController::class, 'pick']);
$router->get('/api/performance', [ApiController::class, 'performance']);
$router->get('/api/sports', [ApiController::class, 'sports']);
$router->get('/api/leagues', [ApiController::class, 'leagues']);
$router->get('/api/events', [ApiController::class, 'events']);

$router->get('/api/me', [ApiController::class, 'me'], ['auth']);
$router->get('/api/me/picks', [ApiController::class, 'mePicks'], ['auth']);
$router->get('/api/me/subscription', [ApiController::class, 'meSubscription'], ['auth']);
$router->get('/api/me/notifications', [ApiController::class, 'meNotifications'], ['auth']);
