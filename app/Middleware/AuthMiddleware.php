<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Exceptions\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Application $app, array $params = []): void
    {
        if ($app->auth->check()) {
            return;
        }

        if ($request->isApi()) {
            $app->response->json(['error' => 'Unauthenticated'], 401);
        }

        $path = $request->path();
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $intended = $path . ($query !== '' ? '?' . $query : '');
        if (intended_path($intended)) {
            $app->session->set('intended_url', $intended);
        }

        $app->session->flash('error', 'Please sign in to continue.');
        $app->response->redirect('/login');
    }
}
