<?php

declare(strict_types=1);

namespace App\Core;

final class Env
{
    private static array $values = [];
    private static ?string $loadedPath = null;

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = ltrim($line, "\xEF\xBB\xBF");
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key === 'PAYPAL_CLIENT_ID' && self::isStalePaypalClientId($value) && !self::isStalePaypalClientId((string) (self::$values[$key] ?? ''))) {
                continue;
            }

            self::$values[$key] = $value;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }

        self::$loadedPath = $path;
    }

    public static function loadFirst(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                self::load($path);
            }
        }
    }

    public static function candidatePaths(): array
    {
        $paths = [
            BASE_PATH . '/.env',
            BASE_PATH . '/public/.env',
        ];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $root = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\');
            $paths[] = dirname($root) . '/.env';
            $paths[] = $root . '/.env';
        }

        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            $paths[] = dirname((string) $_SERVER['SCRIPT_FILENAME']) . '/.env';
        }

        return array_values(array_unique($paths));
    }

    private static function isStalePaypalClientId(string $id): bool
    {
        return $id === 'AQ-rALZclj5x_R3RvyC0FRX0Kh-ghK4qgMRXT94VhzgW7IXbCiDp1NzElOQ4-uylQ0wkgG86GUKuv6yNI';
    }

    public static function loadedPath(): ?string
    {
        return self::$loadedPath;
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }

    public static function all(): array
    {
        return self::$values;
    }

    public static function write(string $path, array $values): void
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . self::escape((string) $value);
        }

        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Cannot write .env to ' . $dir . '. Check folder permissions.');
        }

        $ok = file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
        if ($ok === false) {
            throw new \RuntimeException('Could not save .env at ' . $path);
        }

        self::$values = [];
        self::$loadedPath = null;
        self::load($path);
    }

    public static function writablePath(): string
    {
        $paths = [BASE_PATH . '/.env'];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $paths[] = dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/.env';
        }

        foreach (array_unique($paths) as $path) {
            $dir = dirname($path);
            if (is_dir($dir) && is_writable($dir)) {
                return $path;
            }
        }

        return BASE_PATH . '/.env';
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values) && self::$values[$key] !== '') {
            return self::$values[$key];
        }

        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = strtolower((string) self::get($key, $default ? 'true' : 'false'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function escape(string $value): string
    {
        if ($value !== '' && preg_match('/[\s#"\']/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
