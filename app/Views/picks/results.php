<section class="section">
    <div class="container">
        <p class="kicker">Public record</p>
        <h1>Results</h1>
        <p class="lede">Graded outcomes are open to everyone — guests, Free Members, and Paid Members. Live Playbook picks stay behind a membership gate.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>Matchup</th><th>Selection</th><th>Odds</th><th>Result</th><th>Units</th></tr></thead>
                <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td data-label="When"><?= e(format_datetime($row['result_at'] ?? $row['start_time'] ?? $row['event_at'] ?? null)) ?></td>
                        <td data-label="Matchup"><a href="<?= e(url('/picks/' . $row['slug'])) ?>"><?= e(pick_matchup_label($row)) ?></a></td>
                        <td data-label="Selection"><?= e(pick_selection_label($row) ?: '—') ?></td>
                        <td data-label="Odds"><?= e((string) ($row['odds'] ?? '—')) ?></td>
                        <td data-label="Result"><span class="badge badge-<?= e($row['result'] ?? $row['status']) ?>"><?= e(pick_status_label((string) ($row['result'] ?? $row['status']))) ?></span></td>
                        <td data-label="Units"><?= e((string) ($row['result_units'] ?? $row['units'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$results): ?>
            <?= component('empty-state', ['title' => 'No graded notes yet', 'body' => 'Results post here after picks are settled.']) ?>
        <?php endif; ?>
    </div>
</section>
