<h2>Graded notes</h2>
<div class="table-wrap">
    <table class="data-table">
                <thead><tr><th>When</th><th>Matchup</th><th>Selection</th><th>Result</th><th>Units</th></tr></thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <tr>
                <td data-label="When"><?= e(format_datetime($row['result_at'] ?? $row['start_time'] ?? null)) ?></td>
                <td data-label="Matchup"><a href="<?= e(url('/picks/' . $row['slug'])) ?>"><?= e(pick_matchup_label($row)) ?></a></td>
                <td data-label="Selection"><?= e(pick_selection_label($row) ?: '—') ?></td>
                <td data-label="Result"><span class="badge badge-<?= e($row['result'] ?? $row['status']) ?>"><?= e(pick_status_label((string) ($row['result'] ?? $row['status']))) ?></span></td>
                <td data-label="Units"><?= e((string) ($row['result_units'] ?? $row['units'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
