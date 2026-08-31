<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Repositories\TransactionRepository;

final class AdminTransactionController extends Controller
{
    public function index(): string
    {
        $filters = $this->filters();
        $repo = new TransactionRepository($this->db);
        $result = $repo->paginate($filters, $filters['page'], $filters['per_page']);
        $stats = $repo->stats($filters);

        $exportParams = array_filter($filters, static fn ($v) => $v !== '' && $v !== 'all');

        return $this->view('admin/transactions/index', [
            'title' => 'Transactions & Orders — Orion Bets',
            'transactions' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'stats' => $stats,
            'exportUrl' => url('/admin/transactions/export-csv' . ($exportParams ? '?' . http_build_query($exportParams) : '')),
        ], 'admin');
    }

    public function exportCsv(): never
    {
        $filters = $this->filters();
        $repo = new TransactionRepository($this->db);
        $rows = $repo->exportTransactions($filters);

        $filename = 'transactions-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new HttpException(500, 'Could not start the CSV export.');
        }

        fputcsv($out, [
            'Transaction Record ID',
            'Transaction ID / Order UUID',
            'Provider Order ID',
            'Customer Name',
            'Customer Email',
            'User ID',
            'Payment Provider',
            'Amount ($)',
            'Currency',
            'Payment Status',
            'Everflow Transaction ID',
            'Description',
            'Created Date',
        ]);

        foreach ($rows as $tx) {
            $amountFormatted = number_format(((int) ($tx['amount_cents'] ?? 0)) / 100, 2, '.', '');
            fputcsv($out, [
                $tx['id'] ?? '',
                $tx['transaction_id'] ?? '',
                $tx['order_id'] ?? '',
                $tx['customer_name'] ?? '',
                $tx['customer_email'] ?? '',
                $tx['user_id'] ?? '',
                $tx['provider'] ?? '',
                $amountFormatted,
                $tx['currency'] ?? 'USD',
                $tx['status'] ?? '',
                $tx['everflow_transaction_id'] ?? '',
                $tx['description'] ?? '',
                $tx['created_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * @return array{q: string, provider: string, status: string, from: string, to: string, page: int, per_page: int}
     */
    private function filters(): array
    {
        $provider = strtolower(trim((string) $this->request->query('provider', '')));
        if (!in_array($provider, ['', 'all', 'paypal', 'upgradechat', 'demo'], true)) {
            $provider = '';
        }

        $status = strtolower(trim((string) $this->request->query('status', '')));
        if (!in_array($status, ['', 'all', 'completed', 'pending', 'failed', 'refunded'], true)) {
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
            'provider' => $provider,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'page' => max(1, (int) $this->request->query('page', 1)),
            'per_page' => max(10, min(100, (int) $this->request->query('per_page', 25))),
        ];
    }
}
