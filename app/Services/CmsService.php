<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class CmsService
{
    /** @var array<string, string>|null */
    private static ?array $cached = null;

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        if (!$this->db->tableExists('cms_settings')) {
            self::$cached = [];
            return self::$cached;
        }

        try {
            $rows = $this->db->fetchAll('SELECT `key`, `value` FROM `cms_settings`');
            $out = [];
            foreach ($rows as $row) {
                $out[(string) $row['key']] = (string) ($row['value'] ?? '');
            }
            self::$cached = $out;
        } catch (Throwable) {
            self::$cached = [];
        }

        return self::$cached;
    }

    public function get(string $key, mixed $default = ''): string
    {
        $all = $this->all();
        if (array_key_exists($key, $all) && $all[$key] !== '') {
            return $all[$key];
        }

        return (string) $default;
    }

    public function put(string $key, ?string $value, string $type = 'text'): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        try {
            $existing = $this->db->fetch('SELECT `key` FROM `cms_settings` WHERE `key` = :k LIMIT 1', ['k' => $key]);
            if ($existing) {
                $this->db->update('cms_settings', [
                    'value' => $value,
                    'type' => $type,
                ], '`key` = :k', ['k' => $key]);
            } else {
                $this->db->insert('cms_settings', [
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                ]);
            }

            if (self::$cached !== null) {
                self::$cached[$key] = (string) ($value ?? '');
            }
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, string|null> $entries
     * @param array<string, string> $types
     */
    public function putMany(array $entries, array $types = []): void
    {
        foreach ($entries as $key => $value) {
            $type = $types[$key] ?? 'text';
            $this->put((string) $key, $value !== null ? (string) $value : null, $type);
        }
    }

    public static function clearCache(): void
    {
        self::$cached = null;
    }
}
