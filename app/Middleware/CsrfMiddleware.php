<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Csrf;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Application $app, array $params = []): void
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            Csrf::assert($request);
        }
    }
}
