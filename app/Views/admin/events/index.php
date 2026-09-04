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
        <h2>Events</h2>
    </div>
</div>
<form method="get" id="filter-form" class="admin-filter-row" style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-bottom:1.25rem;">
    <input name="q" placeholder="Search matchup, teams, or synced ID" value="<?= e($q) ?>" style="flex:1; min-width:200px;">
    
    <select name="league" style="width:auto; min-width:130px;">
        <option value="">All Leagues</option>
        <?php foreach ($availableLeagues as $lg): ?>
            <?php $lgVal = (string) ($lg['slug'] ?? $lg['id']); ?>
            <option value="<?= e($lgVal) ?>" <?= ($league === $lgVal || $league === (string) $lg['id'] || $league === (string) $lg['slug']) ? 'selected' : '' ?>>
                <?= e($lg['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status" style="width:auto; min-width:120px;">
        <option value="">All statuses</option>
        <?php foreach (['scheduled','in_progress','completed','canceled'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(pick_status_label($st)) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="active" style="width:auto; min-width:110px;">
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
<?php
$perPageVal = (string) ($perPage ?? '10');
$perPageInt = strtolower($perPageVal) === 'all' ? 10000 : max(1, (int) $perPageVal);
$start = ($total ?? 0) > 0 ? ((($page ?? 1) - 1) * $perPageInt) + 1 : 0;
$end = min($total ?? 0, ($page ?? 1) * $perPageInt);
?>
<div class="datatable-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:0.85rem;">
    <div class="datatable-length muted" style="font-size:0.85rem; display:inline-flex; align-items:center; gap:0.45rem;">
        <span>Show</span>
        <select name="per_page" form="filter-form" style="width:auto; padding:0.25rem 0.6rem; height:34px; min-height:34px; font-size:0.85rem; border-radius:6px;" onchange="document.getElementById('filter-form').submit()">
            <?php foreach ([5, 10, 15, 20, 50, 100, 'all'] as $opt): ?>
                <option value="<?= $opt ?>" <?= strtolower($perPageVal) === (string) $opt ? 'selected' : '' ?>>
                    <?= $opt === 'all' ? 'All' : $opt ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span>entries</span>
    </div>
    <div class="datatable-info muted" style="font-size:0.85rem;">
        Showing <?= number_format($start) ?> to <?= number_format($end) ?> of <?= number_format($total ?? 0) ?> entries
    </div>
</div>
<div class="table-wrap">
    <table class="data-table" data-interactive-table>
        <thead>
            <tr>
                <th data-sort="number" style="width:3.5rem;">Sl. No.</th>
                <th data-sort="text">Synced ID</th>
                <th data-sort="text">League</th>
                <th data-sort="text">Matchup</th>
                <th data-sort="date">Start</th>
                <th data-sort="text">Score</th>
                <th data-sort="text">Status</th>
                <th>Visibility</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $index => $event): ?>
            <?php
            $slNo = ((($page ?? 1) - 1) * $perPageInt) + $index + 1;
            $active = (int) ($event['is_active'] ?? 1) === 1;
            ?>
            <tr data-row>
                <td data-label="Sl. No." style="font-family:var(--font-mono); font-size:0.85rem; color:var(--color-text-muted);"><?= $slNo ?></td>
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
                <td data-label="Actions" style="text-align:right; white-space:nowrap;">
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
