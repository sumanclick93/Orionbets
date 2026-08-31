<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SettingsService
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        if (!$this->db->tableExists('site_settings')) {
            return [];
        }

        $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM site_settings');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public function put(string $key, ?string $value): void
    {
        $existing = $this->db->fetch('SELECT id FROM site_settings WHERE setting_key = :k', ['k' => $key]);
        if ($existing) {
            $this->db->update('site_settings', ['setting_value' => $value], 'setting_key = :k', ['k' => $key]);
            return;
        }
        $this->db->insert('site_settings', [
            'setting_key' => $key,
            'setting_value' => $value,
            'type' => 'text',
        ]);
    }

    public function legal(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM legal_pages WHERE slug = :slug', ['slug' => $slug]);
    }

    public function saveLegal(string $slug, string $title, string $content): void
    {
        $row = $this->legal($slug);
        if ($row) {
            $this->db->update('legal_pages', compact('title', 'content'), 'slug = :slug', ['slug' => $slug]);
            return;
        }
        $this->db->insert('legal_pages', compact('slug', 'title', 'content'));
    }
}
