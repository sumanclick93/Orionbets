<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Repositories\PickRepository;

final class PickService
{
    public function __construct(
        private Database $db,
        private PickRepository $picks,
        private AuditService $audit
    ) {
    }

    public function save(array $input, int $actorId, Request $request, ?int $id = null): int
    {
            $slugSource = trim((string) ($input['slug'] ?? ''));
        $slug = $this->slugify($slugSource !== '' ? $slugSource : (string) $input['title']);
        if ($id === null) {
            $base = $slug;
            $i = 1;
            while ($this->picks->findBySlug($slug)) {
                $slug = $base . '-' . $i++;
            }
        }

        $payload = [
            'slug' => $slug,
            'title' => trim((string) $input['title']),
            'sport_id' => (int) $input['sport_id'],
            'league_id' => !empty($input['league_id']) ? (int) $input['league_id'] : null,
            'event_id' => !empty($input['event_id']) ? (int) $input['event_id'] : null,
            'analysis' => (string) ($input['analysis'] ?? ''),
            'analysis_excerpt' => excerpt((string) ($input['analysis'] ?? ''), 220),
            'key_factors' => json_encode(array_values(array_filter(array_map('trim', explode("\n", (string) ($input['key_factors'] ?? '')))))),
            'supporting_stats' => json_encode($this->parseStats((string) ($input['supporting_stats'] ?? ''))),
            'historical_context' => (string) ($input['historical_context'] ?? ''),
            'confidence' => max(1, min(100, (int) ($input['confidence'] ?? 60))),
            'status' => (string) $input['status'],
            'is_premium' => !empty($input['is_premium']) ? 1 : 0,
            'published_at' => !empty($input['published_at']) ? $input['published_at'] : null,
            'scheduled_at' => !empty($input['scheduled_at']) ? $input['scheduled_at'] : null,
            'updated_by' => $actorId,
            'matchup' => trim((string) ($input['matchup'] ?? '')) ?: null,
            'bet_type' => trim((string) ($input['bet_type'] ?? '')) ?: null,
            'selection_line' => trim((string) ($input['selection_line'] ?? '')) ?: null,
            'odds' => trim((string) ($input['odds'] ?? '')) ?: null,
            'units' => ($input['units'] ?? '') !== '' && $input['units'] !== null ? (float) $input['units'] : null,
            'sportsbook' => trim((string) ($input['sportsbook'] ?? '')) ?: null,
            'is_published' => array_key_exists('is_published', $input) ? (!empty($input['is_published']) ? 1 : 0) : 1,
            'is_active' => array_key_exists('is_active', $input) ? (!empty($input['is_active']) ? 1 : 0) : 1,
            'is_custom' => 1,
        ];

        if ($id) {
            $this->picks->update($id, $payload);
            $this->audit->log($actorId, 'pick_updated', 'pick', (string) $id, $request);
            $pickId = $id;
        } else {
            $payload['created_by'] = $actorId;
            $pickId = $this->picks->create($payload);
            $this->audit->log($actorId, 'pick_created', 'pick', (string) $pickId, $request);
        }

        if (!empty($input['result']) && $input['result'] !== '') {
            $this->recordResult($pickId, (string) $input['result'], (float) ($input['units'] ?? 0), (string) ($input['closing_notes'] ?? ''), $actorId, $request);
        }

        return $pickId;
    }

    public function recordResult(int $pickId, string $result, float $units, string $notes, int $actorId, Request $request): void
    {
        $existing = $this->db->fetch('SELECT id FROM pick_results WHERE pick_id = :id', ['id' => $pickId]);
        $data = [
            'result' => $result,
            'units' => $units,
            'closing_notes' => $notes,
            'recorded_at' => date('Y-m-d H:i:s'),
            'recorded_by' => $actorId,
        ];
        if ($existing) {
            $this->db->update('pick_results', $data, 'pick_id = :id', ['id' => $pickId]);
        } else {
            $this->db->insert('pick_results', $data + ['pick_id' => $pickId]);
        }

        $this->picks->update($pickId, ['status' => $result]);
        $this->audit->log($actorId, 'result_updated', 'pick', (string) $pickId, $request, ['result' => $result]);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? $value, '-'));
        return $slug !== '' ? $slug : 'analysis-' . bin2hex(random_bytes(3));
    }

    private function parseStats(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$label, $value] = explode(':', $line, 2);
            $out[] = ['label' => trim($label), 'value' => trim($value)];
        }
        return $out;
    }
}
