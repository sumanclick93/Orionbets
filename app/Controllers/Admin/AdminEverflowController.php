<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Repositories\EverflowRepository;
use App\Services\EverflowService;

final class AdminEverflowController extends Controller
{
    public function index(): string
    {
        $repo = new EverflowRepository($this->db);
        $filters = $this->filters();
        $page = max(1, (int) $this->request->query('page', 1));
        $result = $this->db->tableExists('everflow_postbacks')
            ? $repo->paginatePostbacks($filters, $page, 25)
            : ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 25];
        $stats = $repo->postbackStats($filters);
        $cfg = everflow_config();
        $clicks = $this->db->tableExists('everflow_clicks') ? $repo->recentClicks(50) : [];

        return $this->view('admin/everflow/index', [
            'title' => 'Everflow tracking — Orion Bets',
            'postbacks' => $result['data'],
            'clicks' => $clicks,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'stats' => $stats,
            'filters' => $filters,
            'everflow' => $cfg,
            'exportUrl' => url('/admin/everflow/export-csv?' . http_build_query(array_filter($filters + ['type' => 'postbacks']))),
            'clicksExportUrl' => url('/admin/everflow/export-csv?' . http_build_query(array_filter($filters + ['type' => 'clicks']))),
        ], 'admin');
    }

    public function exportCsv(): never
    {
        $repo = new EverflowRepository($this->db);
        $filters = $this->filters();
        $type = strtolower(trim((string) $this->request->query('type', 'postbacks')));
        if ($type !== 'clicks') {
            $type = 'postbacks';
        }

        $filename = 'everflow-' . $type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new HttpException(500, 'Could not start the CSV export.');
        }

        if ($type === 'clicks') {
            $rows = $this->db->tableExists('everflow_clicks') ? $repo->exportClicks($filters) : [];
            fputcsv($out, [
                'Date', 'Type', 'Transaction ID', 'Impression ID', 'Sub1', 'Sub2', 'Sub3', 'Sub4', 'Sub5',
                'Affiliate ID', 'Offer ID', 'Source ID', 'Creative ID', 'Landing URL', 'IP', 'User Agent',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['created_at'] ?? '',
                    $row['click_type'] ?? '',
                    $row['transaction_id'] ?? '',
                    $row['impression_id'] ?? '',
                    $row['sub1'] ?? '',
                    $row['sub2'] ?? '',
                    $row['sub3'] ?? '',
                    $row['sub4'] ?? '',
                    $row['sub5'] ?? '',
                    $row['affid'] ?? $row['affiliate_id'] ?? '',
                    $row['oid'] ?? $row['offer_id'] ?? '',
                    $row['source_id'] ?? '',
                    $row['creative_id'] ?? '',
                    $row['landing_url'] ?? $row['landing_path'] ?? '',
                    $row['ip_address'] ?? $row['ip'] ?? '',
                    $row['user_agent'] ?? '',
                ]);
            }
        } else {
            $rows = $this->db->tableExists('everflow_postbacks') ? $repo->exportPostbacks($filters) : [];
            fputcsv($out, [
                'Date', 'Kind', 'Order ID', 'Order Number', 'Customer Email', 'User ID', 'Transaction ID',
                'Amount', 'Currency', 'Event Type', 'Sub1', 'Sub2', 'Sub3', 'Sub4', 'Sub5',
                'HTTP Status', 'Everflow Status', 'Postback URL', 'Response Body', 'Error',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['created_at'] ?? '',
                    $row['kind'] ?? '',
                    $row['order_id'] ?? '',
                    $row['order_number'] ?? $row['order_id'] ?? '',
                    $row['user_email'] ?? $row['email'] ?? '',
                    $row['user_id'] ?? '',
                    $row['transaction_id'] ?? $row['everflow_transaction_id'] ?? '',
                    $row['amount'] ?? '',
                    $row['currency'] ?? 'USD',
                    $row['event_type'] ?? '',
                    $row['sub1'] ?? '',
                    $row['sub2'] ?? '',
                    $row['sub3'] ?? '',
                    $row['sub4'] ?? '',
                    $row['sub5'] ?? '',
                    $row['http_status'] ?? '',
                    $this->statusOf($row),
                    $row['postback_url'] ?? $row['url'] ?? '',
                    $row['response_body'] ?? $row['response'] ?? '',
                    $row['error_message'] ?? '',
                ]);
            }
        }

        fclose($out);
        exit;
    }

    public function retryPostback(string $id): never
    {
        $postbackId = (int) $id;
        if ($postbackId < 1) {
            throw new HttpException(404, 'Postback not found.');
        }

        $ok = false;
        try {
            $ok = EverflowService::make($this->db)->retryPostback($postbackId);
        } catch (\Throwable $e) {
            if ($this->request->isAjax()) {
                $this->json(['ok' => false, 'error' => 'Retry failed.'], 500);
            }
            $this->flash('error', 'Everflow retry failed.');
            $this->redirect('/admin/everflow');
        }

        if ($this->request->isAjax()) {
            $row = (new EverflowRepository($this->db))->findPostbackById($postbackId);
            $this->json([
                'ok' => $ok,
                'status' => $this->statusOf($row ?? []),
                'http_status' => $row['http_status'] ?? null,
            ]);
        }

        $this->flash($ok ? 'success' : 'error', $ok
            ? 'Everflow postback retried successfully.'
            : 'Everflow postback retry did not succeed. Check the payload inspector.');
        $this->redirect('/admin/everflow');
    }

    /**
     * @return array{q:string,status:string,from:string,to:string}
     */
    private function filters(): array
    {
        $status = strtolower(trim((string) $this->request->query('status', '')));
        if (!in_array($status, ['', 'success', 'failed', 'pending'], true)) {
            $status = '';
        }

        $from = (string) $this->request->query('from', '');
        $to = (string) $this->request->query('to', '');
        if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

        return [
            'q' => trim((string) $this->request->query('q', '')),
            'status' => $status,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function statusOf(array $row): string
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (in_array($status, ['success', 'failed', 'pending'], true)) {
            return $status;
        }
        $http = (int) ($row['http_status'] ?? 0);
        if ($http >= 200 && $http < 400) {
            return 'success';
        }
        if ($http > 0) {
            return 'failed';
        }

        return 'pending';
    }
}
