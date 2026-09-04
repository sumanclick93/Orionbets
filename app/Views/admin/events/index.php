<?php
$q = (string) ($_GET['q'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$league = (string) ($_GET['league'] ?? '');
$active = (string) ($_GET['active'] ?? '');
$lastSync = $lastSync ?? null;
$syncedAt = $lastSync['created_at'] ?? null;
$total = $total ?? count($events ?? []);
$page = $page ?? 1;
$availableLeagues = $availableLeagues ?? [];
$hasFilters = !empty($q) || !empty($status) || !empty($league) || $active !== '';
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Scoreboard</p>
        <h2>Synced events</h2>
        <p class="muted admin-hint">Upcoming and completed games are pulled from Action Network. Toggle visibility to hide a game from public pages.</p>
        <?php if ($syncedAt): ?>
            <span class="sync-badge <?= ($lastSync['status'] ?? '') === 'failed' ? 'is-failed' : 'is-ok' ?>">Last sync <?= e(format_datetime($syncedAt)) ?></span>
        <?php else: ?>
            <span class="sync-badge">Never synced</span>
        <?php endif; ?>
    </div>
    <div class="page-toolbar__actions">
        <form method="post" action="<?= e(url('/admin/sync/action-network')) ?>" data-an-sync="live">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Sync Action Network Now</button>
        </form>
        <span class="sync-badge" data-an-sync-hint hidden></span>
        <a class="btn btn-ghost" href="<?= e(url('/admin/sync')) ?>">Sync logs</a>
    </div>
</div>
<form method="get" class="filter-bar admin-table-tools" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center;">
    <input name="q" placeholder="Search matchup, teams, or synced ID" value="<?= e($q) ?>" style="flex:1; min-width:200px;">
    
    <select name="league" style="min-width:140px;">
        <option value="">All Leagues</option>
        <?php foreach ($availableLeagues as $lg): ?>
            <?php $lgVal = (string) ($lg['slug'] ?? $lg['id']); ?>
            <option value="<?= e($lgVal) ?>" <?= ($league === $lgVal || $league === (string) $lg['id'] || $league === (string) $lg['slug']) ? 'selected' : '' ?>>
                <?= e($lg['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status" style="min-width:130px;">
        <option value="">All statuses</option>
        <?php foreach (['scheduled','in_progress','completed','canceled'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(pick_status_label($st)) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="active" style="min-width:120px;">
        <option value="">All visibility</option>
        <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Hidden</option>
    </select>

    <button class="btn btn-primary" type="submit">Search</button>

    <?php if ($hasFilters): ?>
        <a href="<?= e(url('/admin/events')) ?>" class="btn btn-ghost" style="color:var(--color-text-muted);">Clear filters</a>
    <?php endif; ?>
</form>
<?php if (!$events): ?>
    <?= component('empty-state', [
        'title' => 'No synced events',
        'body' => 'Run Sync Now to pull today’s scoreboard from Action Network.',
    ]) ?>
<?php else: ?>
<div class="table-wrap" data-admin-table>
    <label class="admin-table-search">
        <span class="muted">Filter this page</span>
        <input type="search" data-table-filter placeholder="Filter columns…">
    </label>
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort>Synced ID</th>
                <th data-sort>League</th>
                <th data-sort>Matchup</th>
                <th data-sort>Start</th>
                <th data-sort>Score</th>
                <th data-sort>Status</th>
                <th>Visibility</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php $active = (int) ($event['is_active'] ?? 1) === 1; ?>
            <tr data-row>
                <td data-label="Synced ID"><?= e((string) ($event['action_network_event_id'] ?? '—')) ?></td>
                <td data-label="League"><?= e(strtoupper((string) ($event['league_name'] ?? $event['sport_name'] ?? '—'))) ?></td>
                <td data-label="Matchup"><?= e($event['name'] ?? trim(($event['away_team'] ?? '') . ' @ ' . ($event['home_team'] ?? ''))) ?></td>
                <td data-label="Start"><?= e(format_datetime($event['start_time'] ?? $event['event_at'] ?? null)) ?></td>
                <td data-label="Score">
                    <?php if ($event['away_score'] !== null || $event['home_score'] !== null): ?>
                        <?= e((string) ($event['away_score'] ?? '—')) ?>–<?= e((string) ($event['home_score'] ?? '—')) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td data-label="Status"><span class="badge"><?= e(pick_status_label((string) ($event['status'] ?? ''))) ?></span></td>
                <td data-label="Visibility">
                    <form method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/toggle-status')) ?>" data-toggle-active>
                        <?= csrf_field() ?>
                        <label class="geo-switch is-row">
                            <input type="checkbox" <?= $active ? 'checked' : '' ?> onchange="this.form.requestSubmit()">
                            <span class="geo-switch__ui"></span>
                            <span class="vis-label"><?= $active ? 'Active' : 'Hidden' ?></span>
                        </label>
                    </form>
                </td>
                <td data-label="Actions">
                    <form method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/delete')) ?>" data-confirm="Delete this event?" data-confirm-copy="Linked picks stay, but the scoreboard row is removed." data-confirm-ok="Delete">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-small" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= component('pagination', ['total' => $total, 'page' => $page, 'perPage' => 50]) ?>
<?php endif; ?>
