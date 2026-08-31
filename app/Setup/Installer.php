<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use RuntimeException;
use Throwable;

final class Installer
{
    public static function migrate(): string
    {
        $db = Database::getInstance();
        $file = BASE_PATH . '/database/migrations/001_create_schema.sql';
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Migration file missing.');
        }

        $pdo = $db->pdo();
        $clean = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $clean))) as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }

        try {
            $exists = $db->fetch("SELECT id FROM schema_migrations WHERE migration = '001_create_schema' LIMIT 1");
            if (!$exists) {
                $db->insert('schema_migrations', ['migration' => '001_create_schema']);
            }
        } catch (Throwable $e) {
            Logger::warning('Could not record migration', ['error' => $e->getMessage()]);
        }

        Schema::ensure($db);

        return 'Migrations complete.';
    }

    public static function seed(): string
    {
        ob_start();
        if (!class_exists('DatabaseSeeder', false)) {
            require BASE_PATH . '/database/seeders/DatabaseSeeder.php';
        }
        (new \DatabaseSeeder(Database::getInstance()))->run();
        $output = trim((string) ob_get_clean());

        return $output !== '' ? $output : 'Seed complete.';
    }

    public static function setup(): array
    {
        $migrate = self::migrate();
        $seed = self::seed();

        return [$migrate, $seed];
    }

    public static function saveEnv(array $input): string
    {
        $host = trim((string) ($input['db_host'] ?? 'localhost'));
        $database = trim((string) ($input['db_database'] ?? ''));
        $username = trim((string) ($input['db_username'] ?? ''));
        $password = (string) ($input['db_password'] ?? '');
        $appUrl = trim((string) ($input['app_url'] ?? ''));

        if ($host === '' || $host === 'db') {
            $host = 'localhost';
        }
        if ($database === '' || $username === '') {
            throw new RuntimeException('Database name and username are required. Use the full names from cPanel → MySQL Databases.');
        }
        if ($appUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $appUrl = rtrim($appUrl, '/');
        $appHost = strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?? ''));
        if ($appHost === '' || str_contains($appHost, 'cpanel.site') || str_ends_with($appHost, 'orionbets.co')) {
            $appUrl = 'https://orionbets.co';
        }

        $existing = Env::all();
        $values = [
            'APP_NAME' => $existing['APP_NAME'] ?? 'Orion Bets',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_URL' => $appUrl,
            'APP_KEY' => $existing['APP_KEY'] ?? ('base64:' . bin2hex(random_bytes(16))),
            'APP_TIMEZONE' => $existing['APP_TIMEZONE'] ?? 'UTC',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_PORT' => $existing['DB_PORT'] ?? '3306',
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
            'MAIL_MAILER' => $existing['MAIL_MAILER'] ?? 'log',
            'MAIL_HOST' => $existing['MAIL_HOST'] ?? '',
            'MAIL_PORT' => $existing['MAIL_PORT'] ?? '587',
            'MAIL_USERNAME' => $existing['MAIL_USERNAME'] ?? '',
            'MAIL_PASSWORD' => $existing['MAIL_PASSWORD'] ?? '',
            'MAIL_FROM_ADDRESS' => $existing['MAIL_FROM_ADDRESS'] ?? 'hello@orionbets.co',
            'MAIL_FROM_NAME' => $existing['MAIL_FROM_NAME'] ?? 'Orion Bets',
            'SESSION_LIFETIME' => $existing['SESSION_LIFETIME'] ?? '120',
            'SESSION_SECURE' => str_starts_with($appUrl, 'https://') ? 'true' : 'false',
            'SESSION_SAME_SITE' => $existing['SESSION_SAME_SITE'] ?? 'Lax',
        ];

        $path = Env::writablePath();
        Env::write($path, $values);

        foreach ($values as $key => $value) {
            Env::set($key, $value);
        }

        return $path;
    }
}
