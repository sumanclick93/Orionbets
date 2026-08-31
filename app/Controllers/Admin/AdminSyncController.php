<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\ActionNetworkJobService;
use App\Services\ActionNetworkService;
use App\Services\AuditService;

final class AdminSyncController extends Controller
{
    public function index(): string
    {
        $service = ActionNetworkService::make($this->db);
        $jobs = ActionNetworkJobService::make($this->db);

        return $this->view('admin/sync/index', [
            'title' => 'Action Network sync — Orion Bets',
            'config' => ActionNetworkService::config(),
            'lastSync' => $service->lastSync(),
            'logs' => $service->recentLogs(40),
            'activeJob' => $jobs->resumable(),
        ], 'admin');
    }

    public function run(): never
    {
        $this->startJob('live');
    }

    public function backfill(): never
    {
        $days = max(1, min(730, (int) $this->request->post('days', $this->request->query('days', 365))));
        $this->startJob('backfill', $days);
    }

    public function tick(): never
    {
        @set_time_limit(45);
        $jobId = $this->jobIdFromRequest();
        if ($jobId < 1) {
            $this->json(['ok' => false, 'error' => 'Missing sync job.'], 422);
        }

        $payload = ActionNetworkJobService::make($this->db)->tick($jobId);
        $this->auditIfComplete($payload);
        $this->json($payload, !empty($payload['ok']) ? 200 : 422);
    }

    public function pause(): never
    {
        $jobId = $this->jobIdFromRequest();
        if ($jobId < 1) {
            $this->json(['ok' => false, 'error' => 'Missing sync job.'], 422);
        }

        $this->json(ActionNetworkJobService::make($this->db)->pause($jobId));
    }

    public function status(): never
    {
        $raw = $this->request->query('job_id', 0);
        $jobId = is_numeric($raw) ? (int) $raw : 0;
        $this->json(ActionNetworkJobService::make($this->db)->status($jobId > 0 ? $jobId : null));
    }

    private function startJob(string $mode, int $days = 365): never
    {
        @set_time_limit(20);
        $jobs = ActionNetworkJobService::make($this->db);
        $payload = $jobs->start($mode, $days);

        if (!empty($payload['ok'])) {
            (new AuditService($this->db))->log(
                $this->auth->id(),
                !empty($payload['resumed']) ? 'an_sync_resume' : 'an_sync_start',
                'action_network',
                (string) ($payload['job_id'] ?? ''),
                $this->request,
                [
                    'mode' => $payload['mode'] ?? $mode,
                    'remaining' => $payload['remaining'] ?? 0,
                    'resumed' => !empty($payload['resumed']),
                ]
            );
        }

        if ($this->request->isAjax()) {
            $this->json($payload, !empty($payload['ok']) ? 200 : 422);
        }

        $this->flash(
            !empty($payload['ok']) ? 'success' : 'error',
            !empty($payload['ok'])
                ? 'Batched sync is ready. Keep this page open — the progress modal needs JavaScript.'
                : (string) ($payload['error'] ?? 'Could not start sync.')
        );
        $this->redirect('/admin/sync');
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function auditIfComplete(array $payload): void
    {
        if (($payload['status'] ?? '') !== 'completed') {
            return;
        }
        (new AuditService($this->db))->log(
            $this->auth->id(),
            !empty($payload['already_synced']) ? 'an_sync_noop' : 'an_sync',
            'action_network',
            (string) ($payload['job_id'] ?? ''),
            $this->request,
            [
                'items' => $payload['items_synced'] ?? 0,
                'already_synced' => !empty($payload['already_synced']),
                'mode' => $payload['mode'] ?? 'live',
            ]
        );
    }

    private function jobIdFromRequest(): int
    {
        $json = $this->request->json();
        $raw = $this->request->post('job_id', $json['job_id'] ?? $this->request->query('job_id', 0));

        return is_numeric($raw) ? (int) $raw : 0;
    }
}
