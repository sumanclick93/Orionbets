<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Exceptions\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class PremiumMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Application $app, array $params = []): void
    {
        if (!$app->auth->check()) {
            $app->session->flash('error', 'Please sign in to continue.');
            $app->response->redirect('/login');
        }

        if (!$app->auth->isPremium()) {
            $app->session->flash('error', 'This area is available to Premium members.');
            $app->response->redirect('/pricing');
        }
    }
}
