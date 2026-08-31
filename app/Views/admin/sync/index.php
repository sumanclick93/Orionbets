<?php
$config = $config ?? action_network_config();
$lastSync = $lastSync ?? null;
$logs = $logs ?? [];
$syncedAt = $lastSync['created_at'] ?? null;
$userSet = trim((string) ($config['user_id'] ?? '')) !== '';
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Action Network</p>
        <h2>Data sync</h2>
        <p class="muted admin-hint">Public pages read the local cache only. Use Sync Now for today/yesterday, or backfill to import historical scoreboards and picks.</p>
        <?php if ($syncedAt): ?>
            <span class="sync-badge <?= ($lastSync['status'] ?? '') === 'failed' ? 'is-failed' : 'is-ok' ?>">Last sync <?= e(format_datetime($syncedAt)) ?> · <?= e((string) ($lastSync['endpoint'] ?? '')) ?></span>
        <?php else: ?>
            <span class="sync-badge">Never synced</span>
        <?php endif; ?>
    </div>
</div>

<div class="dash-grid cols-2">
    <section class="panel">
        <h3>Live sync</h3>
        <p class="muted">Pulls yesterday and today across every configured league (<?= e(strtoupper(implode(', ', $config['leagues'] ?? []))) ?>), then the playbook and profile metrics. Runs in small batches so the request cannot time out — pause anytime and the next sync resumes where it stopped.</p>
        <?php if (!$userSet): ?>
            <p class="field-hint">Set <code>ACTION_NETWORK_USER_ID</code> in <code>.env</code> to import picks and ROI. Scoreboards still sync without it.</p>
        <?php else: ?>
            <p class="field-hint">User <?= e((string) $config['user_id']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/sync/action-network')) ?>" data-an-sync="live">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Sync Action Network Now</button>
        </form>
        <span class="sync-badge" data-an-sync-hint hidden></span>
    </section>
    <section class="panel">
        <h3>Historical backfill</h3>
        <p class="muted">Walks every league day by day, then paginates picks. Each day is its own batch so you can pause and resume without a timeout.</p>
        <form method="post" action="<?= e(url('/admin/sync/action-network-backfill')) ?>" class="filter-bar" data-an-sync="backfill" data-confirm="Run a historical backfill?" data-confirm-copy="This walks Action Network day by day in small batches. You can pause and resume later from the same point." data-confirm-ok="Start backfill">
            <?= csrf_field() ?>
            <label>Days
                <input type="number" name="days" min="1" max="730" value="365">
            </label>
            <button class="btn btn-ghost" type="submit">Run Historical Backfill</button>
        </form>
    </section>
</div>

<section class="panel" style="margin-top:1rem;">
    <h3>Recent logs</h3>
    <?php if (!$logs): ?>
        <p class="muted">No syncs yet.</p>
    <?php else: ?>
        <div class="table-wrap" data-admin-table>
            <label class="admin-table-search">
                <span class="muted">Filter</span>
                <input type="search" data-table-filter placeholder="Filter logs…">
            </label>
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort>When</th>
                        <th data-sort>Type</th>
                        <th data-sort>Endpoint</th>
                        <th data-sort>Items</th>
                        <th data-sort>Status</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr data-row>
                        <td data-label="When"><?= e(format_datetime($log['created_at'] ?? null)) ?></td>
                        <td data-label="Type"><?= e((string) ($log['sync_type'] ?? '')) ?></td>
                        <td data-label="Endpoint"><?= e((string) ($log['endpoint'] ?? '')) ?></td>
                        <td data-label="Items"><?= (int) ($log['items_synced'] ?? 0) ?></td>
                        <td data-label="Status"><span class="badge <?= ($log['status'] ?? '') === 'success' ? 'badge-won' : 'badge-lost' ?>"><?= e((string) ($log['status'] ?? '')) ?></span></td>
                        <td data-label="Error"><?= e((string) ($log['error_message'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
