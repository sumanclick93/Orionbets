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
<form method="get" id="filter-form" class="admin-filter-row" style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-bottom:1.25rem;">
    <?php if ($archived): ?>
        <input type="hidden" name="view" value="archived">
    <?php endif; ?>
    <input name="q" placeholder="Search matchup, selection, or synced ID" value="<?= e($q) ?>" style="flex:1; min-width:200px;">

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
        <?php foreach (['pending','published','won','lost','push','canceled'] as $st): ?>
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
        <a href="<?= e(url('/admin/picks' . ($archived ? '?view=archived' : ''))) ?>" class="btn btn-ghost" style="color:var(--color-text-muted);">Clear filters</a>
    <?php endif; ?>
</form>
<?php if (!$picks): ?>
    <?= component('empty-state', [
        'title' => $archived ? 'No archived picks' : 'No synced picks',
        'body' => $archived ? 'Archived picks will appear here so you can restore them.' : 'Run Sync Now to pull the Action Network playbook.',
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
                <th data-sort="text">Selection</th>
                <th data-sort="number">Odds</th>
                <th data-sort="number">Units</th>
                <th data-sort="text">Sportsbook</th>
                <th data-sort="text">Status</th>
                <th>Visibility</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($picks as $index => $pick): ?>
            <?php
            $slNo = ((($page ?? 1) - 1) * $perPageInt) + $index + 1;
            $active = (int) ($pick['is_active'] ?? 1) === 1;
            ?>
            <tr data-row>
                <td data-label="Sl. No." style="font-family:var(--font-mono); font-size:0.85rem; color:var(--color-text-muted);"><?= $slNo ?></td>
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
                <td data-label="Actions" style="text-align:right; white-space:nowrap;">
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
