<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Exceptions\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class RoleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Application $app, array $params = []): void
    {
        if (!$app->auth->check()) {
            $app->response->redirect('/login');
        }

        if ($params === [] || $app->auth->hasRole(...$params) || $app->auth->isSuperAdmin()) {
            return;
        }

        throw new HttpException(403, 'Your role cannot access this resource.');
    }
}
