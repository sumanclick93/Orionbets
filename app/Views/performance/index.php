<section class="section">
    <div class="container">
        <p class="kicker">Public record<?= empty($stats['is_demo']) ? '' : ' ' . demo_badge() ?></p>
        <h1>See the Record</h1>
            <p class="lede">Transparent tracking of Orion Bets outcomes. Figures update from the local Action Network cache after each sync.</p>
        <div class="range-tabs">
            <?php foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'season' => 'Season', 'all' => 'All time'] as $key => $label): ?>
                <a class="<?= ($range ?? 'all') === $key ? 'is-active' : '' ?>" href="?range=<?= $key ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <div class="stat-grid five">
            <?= component('stat-card', ['label' => 'Notes graded', 'value' => (string) $stats['total']]) ?>
            <?= component('stat-card', ['label' => 'Win rate', 'value' => $stats['win_rate'] . '%']) ?>
            <?= component('stat-card', ['label' => 'ROI', 'value' => $stats['roi'] . '%']) ?>
            <?= component('stat-card', ['label' => 'Units', 'value' => (string) $stats['units']]) ?>
            <?= component('stat-card', ['label' => 'Avg confidence', 'value' => (string) $stats['avg_confidence']]) ?>
            <?= component('stat-card', ['label' => 'Current streak', 'value' => (string) $stats['current_streak']]) ?>
            <?= component('stat-card', ['label' => 'Best streak', 'value' => (string) $stats['best_streak']]) ?>
        </div>
        <div class="chart-grid" style="margin-top:1.5rem;">
            <div class="panel chart-card"><h3>Cumulative performance</h3><canvas id="chart-cumulative"></canvas></div>
            <div class="panel chart-card"><h3>Monthly performance</h3><canvas id="chart-monthly"></canvas></div>
            <div class="panel chart-card"><h3>Win / loss distribution</h3><canvas id="chart-wl"></canvas></div>
            <div class="panel chart-card"><h3>Sport performance</h3><canvas id="chart-sports"></canvas></div>
            <div class="panel chart-card"><h3>League performance</h3><canvas id="chart-leagues"></canvas></div>
        </div>
        <script type="application/json" id="chart-payload"><?= json_encode($charts) ?></script>
    </div>
</section>
