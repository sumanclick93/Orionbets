<section class="section">
    <div class="container">
        <p class="kicker">Public record<?= empty($stats['is_demo']) ? '' : ' ' . demo_badge() ?></p>
        <h1>See the Record</h1>
        <p class="lede">Transparent tracking of Orion Bets outcomes. Figures update from the local Action Network cache after each sync.</p>
        
        <form method="GET" action="<?= e(url('/performance')) ?>" class="performance-filters-bar" id="performance-filter-form">
            <div class="filter-group filter-buttons">
                <?php foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time'] as $key => $label): ?>
                    <button type="submit" name="range" value="<?= $key ?>" class="filter-btn <?= (($range ?? 'all') === $key && empty($season)) ? 'is-active' : '' ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="filter-group filter-selects">
                <div class="filter-select-wrapper">
                    <select name="season" id="filter-season" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Seasons</option>
                        <?php foreach ($availableSeasons as $s): ?>
                            <option value="<?= $s ?>" <?= ((string)$season === (string)$s || ($range === 'season' && empty($season) && (string)$s === date('Y'))) ? 'selected' : '' ?>>
                                Season <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-select-wrapper">
                    <select name="league" id="filter-league" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Leagues</option>
                        <?php foreach ($availableLeagues as $lg): ?>
                            <?php $val = (string)($lg['slug'] ?? $lg['id']); ?>
                            <option value="<?= e($val) ?>" <?= ((string)$league === $val || (string)$league === (string)($lg['id'] ?? '') || (string)$league === (string)($lg['slug'] ?? '')) ? 'selected' : '' ?>>
                                <?= e($lg['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($season) || !empty($league) || (($range ?? 'all') !== 'all')): ?>
                    <a href="<?= e(url('/performance')) ?>" class="filter-reset-btn" title="Reset all filters">Clear filters</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="stat-grid five">
            <?= component('stat-card', ['label' => 'Notes graded', 'value' => (string) $stats['total']]) ?>
            <?= component('stat-card', ['label' => 'Win rate', 'value' => $stats['win_rate'] . '%']) ?>
            <?= component('stat-card', ['label' => 'ROI', 'value' => ($stats['roi'] > 0 ? '+' : '') . $stats['roi'] . '%']) ?>
            <?= component('stat-card', ['label' => 'Units', 'value' => ($stats['units'] > 0 ? '+' : '') . $stats['units'] . 'u']) ?>
            <?= component('stat-card', ['label' => 'Avg confidence', 'value' => (string) $stats['avg_confidence']]) ?>
            <?= component('stat-card', ['label' => 'Current streak', 'value' => $stats['current_streak'] > 0 ? 'W' . $stats['current_streak'] : ($stats['current_streak'] < 0 ? 'L' . abs((int)$stats['current_streak']) : '0')]) ?>
            <?= component('stat-card', ['label' => 'Best streak', 'value' => 'W' . $stats['best_streak']]) ?>
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
