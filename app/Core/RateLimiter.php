<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public static function tooMany(string $key, int $max, int $decaySeconds): bool
    {
        $dir = STORAGE_PATH . '/rate-limits';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $hits = [];

        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            $hits = is_array($decoded) ? $decoded : [];
        }

        $hits = array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $decaySeconds));
        $hits[] = $now;

        file_put_contents($file, json_encode($hits), LOCK_EX);

        return count($hits) > $max;
    }
}
