<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Application $app, array $params = []): void
    {
        if (!$app->auth->check()) {
            return;
        }

        $path = $request->path();
        if ($path === '/auth/discord' || $path === '/auth/discord/callback') {
            return;
        }

        $app->response->redirect('/dashboard');
    }
}
