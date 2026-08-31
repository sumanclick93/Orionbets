<?php
$q = (string) ($filters['q'] ?? '');
$provider = (string) ($filters['provider'] ?? '');
$status = (string) ($filters['status'] ?? '');
$from = (string) ($filters['from'] ?? '');
$to = (string) ($filters['to'] ?? '');
$perPage = (int) ($perPage ?? 25);

$providers = [
    '' => 'All providers',
    'paypal' => 'PayPal',
    'upgradechat' => 'Upgrade.Chat',
    'demo' => 'Demo / Manual',
];

$statuses = [
    '' => 'All statuses',
    'completed' => 'Completed',
    'pending' => 'Pending',
    'failed' => 'Failed',
    'refunded' => 'Refunded',
];
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Operations</p>
        <h2>Transactions & Orders (<?= number_format((int) ($total ?? 0)) ?>)</h2>
    </div>
    <div class="page-toolbar__actions">
        <a class="btn btn-ghost btn-small" href="<?= e($exportUrl ?? url('/admin/transactions/export-csv')) ?>" download>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.35rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export to CSV
        </a>
    </div>
</div>

<div class="dash-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.25rem;">
    <div class="panel" style="padding: 1rem 1.2rem;">
        <span class="vis-label">Total Transactions</span>
        <div style="font-size: 1.7rem; font-family: var(--font-display); margin-top: 0.2rem;">
            <?= number_format((int) ($stats['total_count'] ?? 0)) ?>
        </div>
    </div>
    <div class="panel" style="padding: 1rem 1.2rem;">
        <span class="vis-label">Completed Orders</span>
        <div style="font-size: 1.7rem; font-family: var(--font-display); color: var(--color-success); margin-top: 0.2rem;">
            <?= number_format((int) ($stats['completed_count'] ?? 0)) ?>
        </div>
    </div>
    <div class="panel" style="padding: 1rem 1.2rem;">
        <span class="vis-label">Total Volume</span>
        <div style="font-size: 1.7rem; font-family: var(--font-display); color: var(--color-primary); margin-top: 0.2rem;">
            <?= money((int) ($stats['total_amount_cents'] ?? 0)) ?>
        </div>
    </div>
</div>

<form method="get" action="<?= e(url('/admin/transactions')) ?>" class="filter-bar" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:1.25rem;">
    <input style="flex:1;min-width:14rem;" name="q" value="<?= e($q) ?>" placeholder="Search Order UUID, Tx ID, Email, Customer...">

    <select name="provider" style="width:auto;min-width:9rem;" onchange="this.form.submit()">
        <?php foreach ($providers as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= $provider === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="status" style="width:auto;min-width:9rem;" onchange="this.form.submit()">
        <?php foreach ($statuses as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= $status === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>

    <div style="display:inline-flex;gap:0.35rem;align-items:center;">
        <input type="date" name="from" value="<?= e($from) ?>" title="From date" style="width:auto;padding:0.45rem 0.6rem;">
        <span class="muted">to</span>
        <input type="date" name="to" value="<?= e($to) ?>" title="To date" style="width:auto;padding:0.45rem 0.6rem;">
    </div>

    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($q !== '' || $provider !== '' || $status !== '' || $from !== '' || $to !== ''): ?>
        <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/transactions')) ?>">Reset</a>
    <?php endif; ?>
</form>

<?php if (!$transactions): ?>
    <?= component('empty-state', [
        'title' => 'No transactions found',
        'body' => ($q !== '' || $provider !== '' || $status !== '' || $from !== '' || $to !== '')
            ? 'Try adjusting your search criteria or date filters.'
            : 'Payment orders from PayPal and Upgrade.Chat will appear here.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table" data-interactive-table>
            <thead>
                <tr>
                    <th data-sort="text">Transaction ID / Order</th>
                    <th data-sort="text">Customer</th>
                    <th data-sort="text">Provider</th>
                    <th data-sort="number">Amount</th>
                    <th data-sort="text">Status</th>
                    <th data-sort="text">Everflow TID</th>
                    <th data-sort="date">Created Date</th>
                    <th style="text-align:right;">Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $tx): ?>
                <?php
                $st = strtolower((string) ($tx['status'] ?? 'completed'));
                $statusBadge = match ($st) {
                    'completed', 'succeeded', 'paid' => 'badge-won',
                    'pending', 'processing' => 'badge-push',
                    'failed', 'declined' => 'badge-lost',
                    'refunded' => 'badge-demo',
                    default => '',
                };

                $providerKey = strtolower((string) ($tx['provider_key'] ?? ''));
                $providerBadge = match ($providerKey) {
                    'paypal' => 'style="background:#003087;color:#fff;border-color:#003087;"',
                    'upgradechat', 'upgrade.chat', 'upgrade_chat' => 'style="background:#6c5ce7;color:#fff;border-color:#6c5ce7;"',
                    default => '',
                };
                ?>
                <tr>
                    <td data-label="Transaction ID / Order" style="font-family:var(--font-mono);font-size:0.85rem;">
                        <strong><?= e($tx['order_id'] ?: $tx['transaction_id'] ?: '#' . $tx['id']) ?></strong>
                        <?php if (!empty($tx['transaction_id']) && $tx['transaction_id'] !== $tx['order_id']): ?>
                            <div class="muted" style="font-size:0.75rem;">Tx: <?= e($tx['transaction_id']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Customer">
                        <div><strong><?= e((string) ($tx['customer_name'] ?: 'Guest Customer')) ?></strong></div>
                        <div class="muted" style="font-size:0.82rem;"><?= e((string) ($tx['customer_email'] ?: '—')) ?></div>
                        <?php if (!empty($tx['user_id'])): ?>
                            <a href="<?= e(url('/admin/users/' . $tx['user_id'])) ?>" class="fineprint" style="text-decoration:underline;">Member #<?= (int) $tx['user_id'] ?></a>
                        <?php endif; ?>
                    </td>
                    <td data-label="Provider">
                        <span class="badge" <?= $providerBadge ?>><?= e($tx['provider']) ?></span>
                    </td>
                    <td data-label="Amount" style="font-family:var(--font-mono);font-weight:600;">
                        <?= money((int) ($tx['amount_cents'] ?? 0), (string) ($tx['currency'] ?? 'USD')) ?>
                    </td>
                    <td data-label="Status">
                        <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($st)) ?></span>
                    </td>
                    <td data-label="Everflow TID" style="font-family:var(--font-mono);font-size:0.8rem;">
                        <?php if (!empty($tx['everflow_transaction_id'])): ?>
                            <span title="<?= e((string) $tx['everflow_transaction_id']) ?>"><?= e(substr((string) $tx['everflow_transaction_id'], 0, 12)) ?>…</span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Created Date" style="font-size:0.85rem;white-space:nowrap;">
                        <?= e(format_datetime((string) ($tx['created_at'] ?? ''))) ?>
                    </td>
                    <td data-label="Details" style="text-align:right;">
                        <button
                            class="btn btn-ghost btn-small"
                            type="button"
                            onclick="alert('Order Payload / Metadata:\n\n' + JSON.stringify(<?= json_encode($tx['payload'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_PRETTY_PRINT) ?>, null, 2))"
                        >
                            JSON
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
<?php endif; ?>
