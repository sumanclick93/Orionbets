<?php
$filters = $filters ?? ['q' => '', 'status' => '', 'from' => '', 'to' => ''];
$q = (string) ($filters['q'] ?? '');
$status = (string) ($filters['status'] ?? '');
$from = (string) ($filters['from'] ?? '');
$to = (string) ($filters['to'] ?? '');
$stats = $stats ?? ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0, 'revenue' => 0];
$failedPending = (int) ($stats['failed'] ?? 0) + (int) ($stats['pending'] ?? 0);
$tabs = [
    '' => 'All',
    'success' => 'Success',
    'failed' => 'Failed',
    'pending' => 'Pending',
];
$filterUrl = static function (string $value) use ($q, $from, $to): string {
    $params = array_filter(['status' => $value, 'q' => $q, 'from' => $from, 'to' => $to], static fn ($v) => $v !== '');
    return url('/admin/everflow' . ($params ? '?' . http_build_query($params) : ''));
};
$statusOf = static function (array $row): string {
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
};
$badgeClass = static function (string $status): string {
    return match ($status) {
        'success' => 'badge-won',
        'failed' => 'badge-lost',
        default => 'badge-push',
    };
};
$customer = static function (array $row): string {
    $email = trim((string) ($row['user_email'] ?? $row['email'] ?? ''));
    $userId = (int) ($row['user_id'] ?? 0);
    if ($email !== '' && $userId > 0) {
        return $email . ' · #' . $userId;
    }
    if ($email !== '') {
        return $email;
    }
    if ($userId > 0) {
        return 'User #' . $userId;
    }
    return '—';
};
$subsLabel = static function (array $row): string {
    $parts = [];
    for ($i = 1; $i <= 5; $i++) {
        $value = trim((string) ($row['sub' . $i] ?? ''));
        if ($value !== '') {
            $parts[] = $i . ':' . $value;
        }
    }
    return $parts !== [] ? implode(' · ', $parts) : '—';
};
$ef = $everflow ?? everflow_config();
?>
<div
    class="ef-admin"
    data-everflow-admin
    data-csrf="<?= e(csrf_token()) ?>"
>
    <div class="page-toolbar">
        <div>
            <p class="kicker">Tracking</p>
            <h2>Everflow</h2>
            <p class="muted admin-hint">Every public visit is stored under Recent visits. Conversion postbacks (signup, contact, checkout, sale, rebill) appear in the table below after those actions.</p>
            <p class="field-hint">Host <?= e($ef['host'] !== '' ? $ef['host'] : 'not set') ?> · NID <?= e($ef['nid'] !== '' ? $ef['nid'] : '—') ?> · Offer <?= e($ef['offer_id'] !== '' ? $ef['offer_id'] : '—') ?> · Lead <?= e($ef['lead_event_id'] !== '' ? $ef['lead_event_id'] : 'off') ?> · Checkout <?= e($ef['checkout_event_id'] !== '' ? $ef['checkout_event_id'] : 'off') ?></p>
        </div>
        <div class="ef-admin__actions">
            <a class="btn btn-primary" href="<?= e($exportUrl ?? url('/admin/everflow/export-csv')) ?>">Export postbacks CSV</a>
            <a class="btn btn-ghost" href="<?= e($clicksExportUrl ?? url('/admin/everflow/export-csv?type=clicks')) ?>">Export clicks CSV</a>
        </div>
    </div>

    <div class="stat-grid">
        <?= component('stat-card', ['label' => 'Postbacks sent', 'value' => (string) (int) ($stats['total'] ?? 0)]) ?>
        <?= component('stat-card', ['label' => 'Successful', 'value' => (string) (int) ($stats['success'] ?? 0)]) ?>
        <?= component('stat-card', ['label' => 'Failed / pending', 'value' => (string) $failedPending]) ?>
        <?= component('stat-card', [
            'label' => 'Tracked revenue',
            'value' => '$' . number_format((float) ($stats['revenue'] ?? 0), 2),
        ]) ?>
    </div>

    <div class="range-tabs">
        <?php foreach ($tabs as $value => $label): ?>
            <a class="<?= $status === $value ? 'is-active' : '' ?>" href="<?= e($filterUrl($value)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="<?= e(url('/admin/everflow')) ?>" class="filter-bar ef-admin__filters">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?= e($status) ?>">
        <?php endif; ?>
        <div>
            <label for="ef-search">Search</label>
            <input id="ef-search" name="q" value="<?= e($q) ?>" data-ef-search placeholder="Order, email, transaction, sub ID" autocomplete="off">
        </div>
        <div>
            <label for="ef-from">From</label>
            <input id="ef-from" name="from" type="date" value="<?= e($from) ?>">
        </div>
        <div>
            <label for="ef-to">To</label>
            <input id="ef-to" name="to" type="date" value="<?= e($to) ?>">
        </div>
        <div class="ef-admin__filter-submit">
            <button class="btn btn-primary" type="submit">Apply</button>
        </div>
    </form>

    <?php $clicks = $clicks ?? []; ?>
        <p class="kicker">Inbound</p>
        <h3>Recent visits</h3>
        <p class="muted admin-hint">Every public page open is stamped here after Everflow returns a click id (including homepage visits with no affiliate params).</p>
        <?php if (!$clicks): ?>
            <?= component('empty-state', [
                'title' => 'No visits yet',
                'body' => 'Open the public site (homepage, pricing, login) in a browser. A row should appear here within a few seconds.',
            ]) ?>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Transaction ID</th>
                        <th>Affiliate / Offer</th>
                        <th>Sub-IDs</th>
                        <th>Landing</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clicks as $click): ?>
                    <?php
                    $clickTid = (string) ($click['transaction_id'] ?? '');
                    $clickType = (string) ($click['click_type'] ?? '');
                    $aff = trim((string) ($click['affid'] ?? $click['affiliate_id'] ?? ''));
                    $oid = trim((string) ($click['oid'] ?? $click['offer_id'] ?? ''));
                    $affLabel = $aff !== '' || $oid !== ''
                        ? trim(($aff !== '' ? 'aff ' . $aff : '') . ($oid !== '' ? ' · oid ' . $oid : ''))
                        : '—';
                    $landing = (string) ($click['landing_path'] ?? $click['landing_url'] ?? '');
                    ?>
                    <tr>
                        <td data-label="Date"><?= e(format_datetime($click['updated_at'] ?? $click['created_at'] ?? null)) ?></td>
                        <td data-label="Type"><?= e($clickType !== '' ? $clickType : '—') ?></td>
                        <td data-label="Transaction ID"><code class="ef-mono"><?= e($clickTid !== '' ? $clickTid : '—') ?></code></td>
                        <td data-label="Affiliate / Offer"><?= e($affLabel) ?></td>
                        <td data-label="Sub-IDs"><?= e($subsLabel($click)) ?></td>
                        <td data-label="Landing"><?= e($landing !== '' ? $landing : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php if (!$postbacks): ?>
        <?= component('empty-state', [
            'title' => 'No Everflow postbacks yet',
            'body' => $q !== '' || $status !== '' || $from !== '' || $to !== ''
                ? 'Nothing matches this filter. Clear search or widen the date range.'
                : 'Postbacks appear here after a tracked signup, checkout start, PayPal capture, or Upgrade.Chat sale. Inbound clicks show in the table above even before a conversion.',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-ef-table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Kind</th>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Transaction ID</th>
                        <th>Amount</th>
                        <th>Sub-IDs</th>
                        <th>HTTP</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($postbacks as $row): ?>
                    <?php
                    $rowStatus = $statusOf($row);
                    $detail = [
                        'id' => (int) ($row['id'] ?? 0),
                        'url' => (string) ($row['postback_url'] ?? $row['url'] ?? ''),
                        'payload' => [
                            'nid' => $ef['nid'] ?? '',
                            'transaction_id' => (string) ($row['transaction_id'] ?? $row['everflow_transaction_id'] ?? ''),
                            'order_id' => (string) ($row['order_id'] ?? ''),
                            'order_number' => (string) ($row['order_number'] ?? $row['order_id'] ?? ''),
                            'email' => (string) ($row['email'] ?? $row['user_email'] ?? ''),
                            'amount' => $row['amount'] ?? null,
                            'currency' => (string) ($row['currency'] ?? 'USD'),
                            'event_type' => (string) ($row['event_type'] ?? ''),
                            'sub1' => (string) ($row['sub1'] ?? ''),
                            'sub2' => (string) ($row['sub2'] ?? ''),
                            'sub3' => (string) ($row['sub3'] ?? ''),
                            'sub4' => (string) ($row['sub4'] ?? ''),
                            'sub5' => (string) ($row['sub5'] ?? ''),
                        ],
                        'http_status' => $row['http_status'] ?? null,
                        'response' => (string) ($row['response_body'] ?? $row['response'] ?? ''),
                        'error' => (string) ($row['error_message'] ?? ''),
                        'status' => $rowStatus,
                    ];
                    $tid = (string) ($row['transaction_id'] ?? $row['everflow_transaction_id'] ?? '');
                    $kind = (string) ($row['kind'] ?? $row['event_type'] ?? 'sale');
                    $amount = $row['amount'] ?? null;
                    $amountLabel = $amount === null || $amount === ''
                        ? '—'
                        : strtoupper((string) ($row['currency'] ?? 'USD')) . ' ' . number_format((float) $amount, 2);
                    ?>
                    <tr data-ef-row data-status="<?= e($rowStatus) ?>" data-search="<?= e(strtolower(implode(' ', [
                        (string) ($row['order_id'] ?? ''),
                        (string) ($row['order_number'] ?? ''),
                        $customer($row),
                        $tid,
                        $subsLabel($row),
                    ]))) ?>">
                        <td data-label="Date"><?= e(format_datetime($row['created_at'] ?? null)) ?></td>
                        <td data-label="Kind"><?= e($kind !== '' ? $kind : '—') ?></td>
                        <td data-label="Order ID">
                            <code class="ef-mono"><?= e((string) ($row['order_id'] ?? '—')) ?></code>
                            <?php if (!empty($row['order_number']) && $row['order_number'] !== ($row['order_id'] ?? '')): ?>
                                <br><small class="muted"><code class="ef-mono"><?= e((string) $row['order_number']) ?></code></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Customer"><?= e($customer($row)) ?></td>
                        <td data-label="Transaction ID"><code class="ef-mono"><?= e($tid !== '' ? $tid : '—') ?></code></td>
                        <td data-label="Amount"><?= e($amountLabel) ?></td>
                        <td data-label="Sub-IDs"><?= e($subsLabel($row)) ?></td>
                        <td data-label="HTTP"><?= e((string) ($row['http_status'] ?? '—')) ?></td>
                        <td data-label="Status"><span class="badge <?= e($badgeClass($rowStatus)) ?>" data-ef-status><?= e($rowStatus) ?></span></td>
                        <td data-label="Actions" class="table-actions">
                            <button
                                class="btn btn-ghost btn-small"
                                type="button"
                                data-ef-view
                                data-detail="<?= e(json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                            >View</button>
                            <?php if ($rowStatus !== 'success'): ?>
                                <form method="post" action="<?= e(url('/admin/everflow/retry-postback/' . (int) $row['id'])) ?>" data-ef-retry>
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-small" type="submit">Retry</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= component('pagination', ['total' => $total ?? 0, 'page' => $page ?? 1, 'perPage' => $perPage ?? 25]) ?>
    <?php endif; ?>
</div>

<dialog class="ef-dialog" data-ef-dialog>
    <div class="ef-dialog__panel">
        <div class="ef-dialog__head">
            <div>
                <p class="kicker">Payload inspector</p>
                <h3>Everflow postback</h3>
            </div>
            <button class="btn btn-ghost btn-small" type="button" data-ef-close>Close</button>
        </div>
        <p class="muted admin-hint">Outbound request fired to Everflow, plus the HTTP body it returned.</p>
        <label>Outbound URL</label>
        <pre class="ef-pre" data-ef-url></pre>
        <label>Request payload</label>
        <pre class="ef-pre" data-ef-payload></pre>
        <label>HTTP status</label>
        <p class="ef-http" data-ef-http></p>
        <label>Everflow response</label>
        <pre class="ef-pre" data-ef-response></pre>
        <label>Error</label>
        <pre class="ef-pre" data-ef-error></pre>
    </div>
</dialog>
