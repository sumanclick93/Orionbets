<h2>Playbook archive</h2>
<?php if (auth()->isFreeMember()): ?>
    <?= component('pick-gate', ['next' => '/dashboard/picks']) ?>
<?php endif; ?>
<form method="get" class="filter-bar">
    <input name="q" placeholder="Search" value="<?= e($filters['q'] ?? '') ?>">
    <input type="date" name="date" value="<?= e($filters['date'] ?? '') ?>">
    <select name="status">
        <option value="">Status</option>
        <?php foreach (['published','won','lost','push','scheduled'] as $st): ?>
            <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(pick_status_label($st)) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Sport</th><th>Matchup</th><th>Selection</th><th>Status</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($picks as $pick): ?>
            <?php $locked = pick_should_gate($pick); ?>
            <tr class="<?= $locked ? 'is-gated' : '' ?>">
                <td data-label="Date"><?= e(format_date($pick['start_time'] ?? $pick['event_at'] ?? $pick['published_at'])) ?></td>
                <td data-label="Sport"><?= e($pick['sport_name'] ?? $pick['sport'] ?? '') ?></td>
                <td data-label="Matchup"><?= e(pick_matchup_label($pick)) ?></td>
                <td data-label="Selection">
                    <a href="<?= e(url('/picks/' . $pick['slug'])) ?>"><?= e($locked ? 'Locked' : (pick_selection_label($pick) ?: $pick['title'])) ?></a>
                    <?php if ($locked): ?><span class="badge badge-accent">Locked</span><?php endif; ?>
                </td>
                <td data-label="Status"><?= e(pick_status_label((string) $pick['status'])) ?></td>
                <td data-label="Result"><?= e($pick['result'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= component('pagination', ['total' => $total, 'page' => $page, 'perPage' => 20]) ?>
