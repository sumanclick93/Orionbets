<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

final class Csrf
{
    public static function ensure(Session $session): void
    {
        if (!$session->get('_csrf_token')) {
            $session->set('_csrf_token', bin2hex(random_bytes(32)));
        }
    }

    public static function token(): string
    {
        return (string) session('_csrf_token');
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        $sessionToken = (string) session('_csrf_token');
        if ($sessionToken === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public static function assert(Request $request): void
    {
        $token = $request->post('_csrf') ?? $request->header('X-CSRF-TOKEN');
        if (!self::verify(is_string($token) ? $token : null)) {
            throw new HttpException(403, 'Invalid security token. Please refresh and try again.');
        }
    }
}
