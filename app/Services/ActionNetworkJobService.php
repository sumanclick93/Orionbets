<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class ActionNetworkJobService
{
    private const STALE_SECONDS = 120;
    private const MAX_PICK_PAGES = 40;

    public function __construct(private Database $db, private ActionNetworkService $network)
    {
    }

    public static function make(Database $db): self
    {
        return new self($db, ActionNetworkService::make($db));
    }

    /**
     * Resume a paused/stale job, or create a new one for this mode.
     *
     * @return array<string,mixed>
     */
    public function start(string $mode, int $days = 365): array
    {
        $mode = $mode === 'backfill' ? 'backfill' : 'live';
        $this->ensureTables();

        $existing = $this->resumable();
        if ($existing) {
            $this->db->update('action_network_sync_jobs', [
                'status' => 'running',
                'paused_at' => null,
                'error_message' => null,
            ], 'id = :id', ['id' => $existing['id']]);
            $existing['status'] = 'running';
            $existing['paused_at'] = null;

            return $this->present($existing, ['resumed' => true]);
        }

        $steps = $this->buildSteps($mode, $days);
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('action_network_sync_jobs', [
            'mode' => $mode,
            'status' => 'running',
            'cursor_index' => 0,
            'total_steps' => count($steps),
            'completed_steps' => 0,
            'items_synced' => 0,
            'changed_steps' => 0,
            'steps_json' => json_encode($steps, JSON_UNESCAPED_SLASHES),
            'current_label' => $steps !== [] ? $this->labelForStep($steps[0]) : 'Starting',
            'started_at' => $now,
            'paused_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ]);

        $job = $this->find($id);
        if (!$job) {
            return ['ok' => false, 'error' => 'Could not create sync job.'];
        }

        if ($steps === []) {
            return $this->complete($job, true);
        }

        return $this->present($job, ['resumed' => false]);
    }

    /**
     * Process one unit (one league/date, one picks page, or profile).
     *
     * @return array<string,mixed>
     */
    public function tick(int $jobId): array
    {
        $this->ensureTables();
        $job = $this->find($jobId);
        if (!$job) {
            return ['ok' => false, 'error' => 'Sync job not found.'];
        }
        if (($job['status'] ?? '') === 'paused') {
            return $this->present($job);
        }
        if (in_array($job['status'] ?? '', ['completed', 'failed'], true)) {
            return $this->present($job);
        }

        $steps = $this->decodeSteps($job['steps_json'] ?? []);
        $index = (int) ($job['cursor_index'] ?? 0);
        if ($index >= count($steps)) {
            return $this->complete($job, (int) ($job['changed_steps'] ?? 0) === 0);
        }

        $step = $steps[$index];
        $syncType = (($job['mode'] ?? 'live') === 'backfill') ? 'backfill' : 'manual';
        $label = $this->labelForStep($step);

        try {
            $result = $this->runStep($step, $syncType);
        } catch (Throwable $e) {
            $result = [
                'ok' => false,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => $e->getMessage(),
                'skipped' => false,
            ];
        }

        if (empty($result['ok'])) {
            $this->db->update('action_network_sync_jobs', [
                'current_label' => $label,
                'error_message' => mb_substr((string) ($result['error'] ?? 'Step failed'), 0, 1000),
            ], 'id = :id', ['id' => $jobId]);
            $job = $this->find($jobId) ?? $job;

            return $this->present($job, [
                'step_ok' => false,
                'step_error' => (string) ($result['error'] ?? 'Step failed'),
            ]);
        }

        if (($step['type'] ?? '') === 'picks' && !empty($result['has_more'])) {
            $nextPage = (int) ($step['page'] ?? 1) + 1;
            if ($nextPage <= self::MAX_PICK_PAGES && !$this->hasPicksPage($steps, $nextPage)) {
                array_splice($steps, $index + 1, 0, [[
                    'type' => 'picks',
                    'page' => $nextPage,
                    'limit' => (int) ($step['limit'] ?? 50),
                ]]);
            }
        }

        $next = $index + 1;
        $items = (int) ($job['items_synced'] ?? 0) + (int) ($result['items'] ?? 0);
        $changed = (int) ($job['changed_steps'] ?? 0) + (!empty($result['changed']) ? 1 : 0);
        $nextLabel = isset($steps[$next]) ? $this->labelForStep($steps[$next]) : ($result['label'] ?? $label);

        $progress = [
            'cursor_index' => $next,
            'completed_steps' => $next,
            'total_steps' => count($steps),
            'items_synced' => $items,
            'changed_steps' => $changed,
            'steps_json' => json_encode($steps, JSON_UNESCAPED_SLASHES),
            'current_label' => $nextLabel,
            'error_message' => null,
        ];

        $saved = $this->db->update(
            'action_network_sync_jobs',
            $progress + ['status' => 'running'],
            'id = :id AND status = :st',
            ['id' => $jobId, 'st' => 'running']
        );
        if ($saved === 0) {
            $this->db->update('action_network_sync_jobs', $progress, 'id = :id', ['id' => $jobId]);
        }

        $job = $this->find($jobId);
        if (!$job) {
            return ['ok' => false, 'error' => 'Sync job disappeared.'];
        }

        if (($job['status'] ?? '') === 'paused') {
            return $this->present($job);
        }

        if ($next >= count($steps)) {
            return $this->complete($job, $changed === 0);
        }

        return $this->present($job, [
            'step_ok' => true,
            'step_label' => (string) ($result['label'] ?? $label),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function pause(int $jobId): array
    {
        $this->ensureTables();
        $job = $this->find($jobId);
        if (!$job) {
            return ['ok' => false, 'error' => 'Sync job not found.'];
        }
        if (in_array($job['status'] ?? '', ['completed', 'failed'], true)) {
            return $this->present($job);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->update('action_network_sync_jobs', [
            'status' => 'paused',
            'paused_at' => $now,
        ], 'id = :id', ['id' => $jobId]);

        $job = $this->find($jobId) ?? $job;
        $job['status'] = 'paused';
        $job['paused_at'] = $now;

        return $this->present($job);
    }

    /**
     * @return array<string,mixed>
     */
    public function status(?int $jobId = null): array
    {
        $this->ensureTables();
        $job = $jobId ? $this->find($jobId) : $this->resumable();
        if (!$job && !$jobId) {
            $job = $this->db->fetch(
                'SELECT * FROM action_network_sync_jobs ORDER BY id DESC LIMIT 1'
            );
        }
        if (!$job) {
            return ['ok' => true, 'job' => null];
        }

        return $this->present($job);
    }

    public function resumable(): ?array
    {
        if (!$this->db->tableExists('action_network_sync_jobs')) {
            return null;
        }

        $row = $this->db->fetch(
            "SELECT * FROM action_network_sync_jobs
             WHERE status IN ('running', 'paused')
             ORDER BY FIELD(status, 'running', 'paused'), updated_at DESC
             LIMIT 1"
        );
        if (!$row) {
            return null;
        }

        if (($row['status'] ?? '') === 'running') {
            $updated = strtotime((string) ($row['updated_at'] ?? '')) ?: 0;
            if ($updated > 0 && (time() - $updated) > self::STALE_SECONDS) {
                $this->db->update('action_network_sync_jobs', [
                    'status' => 'paused',
                    'paused_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $row['id']]);
                $row['status'] = 'paused';
            }
        }

        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    public function present(array $job, array $extra = []): array
    {
        $total = max(0, (int) ($job['total_steps'] ?? 0));
        $done = max(0, (int) ($job['completed_steps'] ?? 0));
        $left = max(0, $total - $done);
        $status = (string) ($job['status'] ?? 'running');
        $already = $status === 'completed' && (int) ($job['changed_steps'] ?? 0) === 0;

        return array_merge([
            'ok' => true,
            'job_id' => (int) ($job['id'] ?? 0),
            'mode' => (string) ($job['mode'] ?? 'live'),
            'status' => $status,
            'already_synced' => $already,
            'total_steps' => $total,
            'completed_steps' => $done,
            'remaining' => $left,
            'items_synced' => (int) ($job['items_synced'] ?? 0),
            'changed_steps' => (int) ($job['changed_steps'] ?? 0),
            'percent' => $total > 0 ? (int) floor(($done / $total) * 100) : 0,
            'current_label' => (string) ($job['current_label'] ?? ''),
            'started_at' => $job['started_at'] ?? null,
            'paused_at' => $job['paused_at'] ?? null,
            'completed_at' => $job['completed_at'] ?? null,
            'error' => $job['error_message'] ?? null,
        ], $extra);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private function complete(array $job, bool $alreadySynced): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->update('action_network_sync_jobs', [
            'status' => 'completed',
            'completed_at' => $now,
            'completed_steps' => (int) ($job['total_steps'] ?? $job['completed_steps'] ?? 0),
            'current_label' => $alreadySynced ? 'Already synced' : 'Complete',
            'paused_at' => null,
        ], 'id = :id AND status = :st', ['id' => $job['id'], 'st' => 'running']);

        $job = $this->find((int) $job['id']) ?? $job;

        return $this->present($job, [
            'already_synced' => $alreadySynced && ($job['status'] ?? '') === 'completed',
        ]);
    }

    private function find(int $id): ?array
    {
        if ($id < 1 || !$this->db->tableExists('action_network_sync_jobs')) {
            return null;
        }

        return $this->db->fetch('SELECT * FROM action_network_sync_jobs WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /**
     * @param array<string,mixed> $step
     * @return array{ok:bool,items:int,changed:bool,has_more:bool,label:string,error:?string,skipped:bool}
     */
    private function runStep(array $step, string $syncType): array
    {
        $type = (string) ($step['type'] ?? '');
        if ($type === 'picks') {
            return $this->network->runPicksPageUnit((int) ($step['page'] ?? 1), (int) ($step['limit'] ?? 50), $syncType);
        }
        if ($type === 'performance') {
            return $this->network->runPerformanceUnit($syncType);
        }

        return $this->network->runScoreboardUnit(
            (string) ($step['league'] ?? 'nfl'),
            (string) ($step['date'] ?? date('Ymd')),
            $syncType
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildSteps(string $mode, int $days): array
    {
        $cfg = ActionNetworkService::config();
        $leagues = $cfg['leagues'] !== [] ? $cfg['leagues'] : ActionNetworkService::ALL_LEAGUES;
        $steps = [];

        if ($mode === 'backfill') {
            $days = max(1, min(730, $days));
            for ($offset = $days; $offset >= 0; $offset--) {
                $date = date('Ymd', strtotime('-' . $offset . ' days'));
                foreach ($leagues as $league) {
                    $steps[] = ['type' => 'scoreboard', 'league' => (string) $league, 'date' => $date];
                }
            }
        } else {
            foreach ([date('Ymd', strtotime('-1 day')), date('Ymd')] as $date) {
                foreach ($leagues as $league) {
                    $steps[] = ['type' => 'scoreboard', 'league' => (string) $league, 'date' => $date];
                }
            }
        }

        if (trim((string) $cfg['user_id']) !== '') {
            $steps[] = ['type' => 'picks', 'page' => 1, 'limit' => 50];
            $steps[] = ['type' => 'performance'];
        }

        return $steps;
    }

    /**
     * @param list<array<string,mixed>> $steps
     */
    private function hasPicksPage(array $steps, int $page): bool
    {
        foreach ($steps as $step) {
            if (($step['type'] ?? '') === 'picks' && (int) ($step['page'] ?? 0) === $page) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $step
     */
    private function labelForStep(array $step): string
    {
        $type = (string) ($step['type'] ?? '');
        if ($type === 'picks') {
            return 'Picks · page ' . (int) ($step['page'] ?? 1);
        }
        if ($type === 'performance') {
            return 'Profile metrics';
        }
        $league = strtoupper((string) ($step['league'] ?? ''));
        $date = (string) ($step['date'] ?? '');

        return trim($league . ' · ' . $date, ' ·');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function decodeSteps(mixed $raw): array
    {
        $decoded = json_decode_array($raw);
        $out = [];
        foreach ($decoded as $step) {
            if (is_array($step)) {
                $out[] = $step;
            }
        }

        return $out;
    }

    private function ensureTables(): void
    {
        if (!$this->db->tableExists('action_network_sync_jobs') || !$this->db->tableExists('action_network_fingerprints')) {
            \App\Setup\Schema::ensureActionNetwork($this->db);
        }
    }
}
