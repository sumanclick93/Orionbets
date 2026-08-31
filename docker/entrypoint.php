<?php

declare(strict_types=1);

$tries = 0;
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_DATABASE') ?: 'edgeplay';
$user = getenv('DB_USERNAME') ?: 'edgeplay';
$pass = getenv('DB_PASSWORD') ?: 'edgeplay';

while ($tries < 40) {
    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $name),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "Database ready.\n";
        break;
    } catch (Throwable $e) {
        $tries++;
        if ($tries >= 40) {
            fwrite(STDERR, "Database not ready: " . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        sleep(2);
    }
}

foreach (['/var/www/html/storage/logs', '/var/www/html/storage/uploads', '/var/www/html/storage/cache', '/var/www/html/storage/rate-limits'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

passthru('chown -R www-data:www-data /var/www/html/storage');
passthru('php /var/www/html/bin/console.php setup');

passthru('apache2-foreground', $apache);
exit($apache ?: 0);
