<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Exceptions\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\RateLimiter;
use App\Core\Request;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Application $app, array $params = []): void
    {
        $max = isset($params[0]) ? (int) $params[0] : 8;
        $decay = isset($params[1]) ? (int) $params[1] : 300;
        $key = $request->path() . '|' . $request->ip();

        if (RateLimiter::tooMany($key, $max, $decay)) {
            throw new HttpException(429, 'Too many attempts. Please wait and try again.');
        }
    }
}
