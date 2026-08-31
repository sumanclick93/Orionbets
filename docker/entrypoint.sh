#!/bin/bash
set -e

mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/uploads \
         /var/www/html/storage/cache \
         /var/www/html/storage/rate-limits

chown -R www-data:www-data /var/www/html/storage || true
chmod -R 775 /var/www/html/storage || true

echo "Waiting for database..."
php -r '
$tries = 0;
while ($tries < 40) {
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "db", getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE") ?: "edgeplay"),
            getenv("DB_USERNAME") ?: "edgeplay",
            getenv("DB_PASSWORD") ?: "edgeplay",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "Database ready.\n";
        exit(0);
    } catch (Throwable $e) {
        $tries++;
        sleep(2);
    }
}
fwrite(STDERR, "Database not ready after waiting.\n");
exit(1);
'

php /var/www/html/bin/console.php setup || true

exec apache2-foreground
