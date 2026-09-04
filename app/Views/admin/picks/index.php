<?php
$archived = !empty($archived);
$q = (string) ($_GET['q'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$league = (string) ($_GET['league'] ?? '');
$active = (string) ($_GET['active'] ?? '');
$lastSync = $lastSync ?? null;
$syncedAt = $lastSync['created_at'] ?? null;
$availableLeagues = $availableLeagues ?? [];
$hasFilters = !empty($q) || !empty($status) || !empty($league) || $active !== '';
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Playbook</p>
        <h2>Picks</h2>
    </div>
</div>
<div class="range-tabs">
    <a class="<?= !$archived ? 'is-active' : '' ?>" href="<?= e(url('/admin/picks' . ($q !== '' ? '?' . http_build_query(['q' => $q]) : ''))) ?>">Active</a>
    <a class="<?= $archived ? 'is-active' : '' ?>" href="<?= e(url('/admin/picks?' . http_build_query(array_filter(['view' => 'archived', 'q' => $q])))) ?>">Archived</a>
</div>
<form method="get" class="filter-bar admin-table-tools" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center;">
    <?php if ($archived): ?>
        <input type="hidden" name="view" value="archived">
    <?php endif; ?>
    <input name="q" placeholder="Search matchup, selection, or synced ID" value="<?= e($q) ?>" style="flex:1; min-width:200px;">

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
        <?php foreach (['pending','published','won','lost','push','canceled'] as $st): ?>
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
        <a href="<?= e(url('/admin/picks' . ($archived ? '?view=archived' : ''))) ?>" class="btn btn-ghost" style="color:var(--color-text-muted);">Clear filters</a>
    <?php endif; ?>
</form>
<?php if (!$picks): ?>
    <?= component('empty-state', [
        'title' => $archived ? 'No archived picks' : 'No synced picks',
        'body' => $archived ? 'Archived picks will appear here so you can restore them.' : 'Run Sync Now to pull the Action Network playbook.',
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
                <th data-sort>Selection</th>
                <th data-sort>Odds</th>
                <th data-sort>Units</th>
                <th data-sort>Sportsbook</th>
                <th data-sort>Status</th>
                <th>Visibility</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($picks as $pick): ?>
            <?php $active = (int) ($pick['is_active'] ?? 1) === 1; ?>
            <tr data-row>
                <td data-label="Synced ID"><?= e((string) ($pick['action_network_pick_id'] ?? '—')) ?></td>
                <td data-label="League"><?= e(strtoupper((string) ($pick['league_name'] ?? $pick['league'] ?? $pick['sport_name'] ?? '—'))) ?></td>
                <td data-label="Matchup"><?= e(pick_matchup_label($pick)) ?></td>
                <td data-label="Selection"><?= e(pick_selection_label($pick) ?: '—') ?></td>
                <td data-label="Odds"><?= e((string) ($pick['odds'] ?? '—')) ?></td>
                <td data-label="Units"><?= e($pick['units'] !== null && $pick['units'] !== '' ? (string) $pick['units'] : '—') ?></td>
                <td data-label="Sportsbook"><?= e((string) ($pick['sportsbook'] ?? '—')) ?></td>
                <td data-label="Status"><span class="badge badge-<?= e($pick['status']) ?>"><?= e(pick_status_label((string) $pick['status'])) ?></span></td>
                <td data-label="Visibility">
                    <?php if (!$archived): ?>
                        <form method="post" action="<?= e(url('/admin/picks/' . $pick['id'] . '/toggle-status')) ?>" data-toggle-active>
                            <?= csrf_field() ?>
                            <label class="geo-switch is-row">
                                <input type="checkbox" <?= $active ? 'checked' : '' ?> onchange="this.form.requestSubmit()">
                                <span class="geo-switch__ui"></span>
                                <span class="vis-label"><?= $active ? 'Active' : 'Hidden' ?></span>
                            </label>
                        </form>
                    <?php else: ?>
                        <span class="muted">Archived</span>
                    <?php endif; ?>
                </td>
                <td data-label="Actions">
                    <?php if ($archived): ?>
                        <form method="post" action="<?= e(url('/admin/picks/' . $pick['id'] . '/restore')) ?>" style="display:inline;" data-confirm="Restore this pick?" data-confirm-copy="It returns to the Active list and the member Playbook." data-confirm-ok="Restore" data-confirm-tone="restore">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary btn-small" type="submit">Restore</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/picks/' . $pick['id'] . '/edit')) ?>">Edit</a>
                        <form method="post" action="<?= e(url('/admin/picks/' . $pick['id'] . '/delete')) ?>" style="display:inline;" data-confirm="Archive this pick?" data-confirm-copy="It leaves the public Playbook. You can restore it later." data-confirm-ok="Archive">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-small" type="submit">Archive</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= component('pagination', ['total' => $total, 'page' => $page, 'perPage' => 50]) ?>
<?php endif; ?>
