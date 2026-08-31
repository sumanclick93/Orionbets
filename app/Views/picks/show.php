<?php
$pick = $pick ?? [];
$factors = json_decode_array($pick['key_factors'] ?? '[]');
$stats = json_decode_array($pick['supporting_stats'] ?? '[]');
$gated = $gated ?? pick_should_gate($pick);
$matchup = pick_matchup_label($pick);
$selection = pick_selection_label($pick);
$start = $pick['start_time'] ?? $pick['event_at'] ?? null;
?>
<article class="section">
    <div class="container">
        <p class="kicker"><?= e($pick['sport_name'] ?? $pick['sport'] ?? '') ?> · <?= e($pick['league_name'] ?? $pick['league'] ?? '') ?></p>
        <h1><?= e($matchup) ?></h1>
        <p class="event-line"><?= e(format_datetime($start)) ?><?= !empty($pick['sportsbook']) && !$gated ? ' · ' . e((string) $pick['sportsbook']) : '' ?></p>
        <p>
            <span class="badge"><?= e(pick_status_label((string) $pick['status'])) ?></span>
            <?php if (!empty($pick['is_premium'])): ?><span class="badge badge-accent">Premium</span><?php endif; ?>
        </p>

        <?php if ($gated): ?>
            <div class="gate">
                <p class="pick-card__line is-locked" style="filter:blur(6px);user-select:none;">Selection locked · odds hidden · units hidden</p>
                <?= component('pick-gate', ['next' => '/picks/' . ($pick['slug'] ?? '')]) ?>
            </div>
        <?php else: ?>
            <div class="panel">
                <p class="pick-card__line"><strong><?= e($selection !== '' ? $selection : $pick['title']) ?></strong>
                    <?php if (!empty($pick['odds'])): ?> · <?= e((string) $pick['odds']) ?><?php endif; ?>
                    <?php if ($pick['units'] !== null && $pick['units'] !== ''): ?> · <?= e((string) $pick['units']) ?>u<?php endif; ?>
                </p>
                <p>Confidence <strong class="confidence"><?= (int) $pick['confidence'] ?></strong> · Published <?= e(format_datetime($pick['published_at'] ?? null)) ?></p>
                <h2>Analysis</h2>
                <p><?= nl2br(e((string) $pick['analysis'])) ?></p>
                <?php if ($factors): ?>
                    <h3>Key factors</h3>
                    <ul><?php foreach ($factors as $factor): ?><li><?= e(is_string($factor) ? $factor : '') ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($stats): ?>
                    <h3>Supporting statistics</h3>
                    <div class="stat-grid">
                        <?php foreach ($stats as $row): ?>
                            <?= component('stat-card', ['label' => $row['label'] ?? '', 'value' => $row['value'] ?? '']) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pick['historical_context'])): ?>
                    <h3>Historical context</h3>
                    <p><?= nl2br(e((string) $pick['historical_context'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pick['result']) || in_array((string) $pick['status'], ['won','lost','push','canceled','cancelled'], true)): ?>
            <div class="panel" style="margin-top:1rem;">
                <h2>Final result</h2>
                <p><span class="badge badge-<?= e($pick['result'] ?? $pick['status']) ?>"><?= e(pick_status_label((string) ($pick['result'] ?? $pick['status']))) ?></span> · <?= e(format_datetime($pick['result_at'] ?? null)) ?></p>
                <?php if (!$gated): ?>
                    <p><?= e(pick_selection_label($pick)) ?> <?= e((string) ($pick['odds'] ?? '')) ?> · <?= e((string) ($pick['result_units'] ?? $pick['units'] ?? '')) ?>u</p>
                    <p><?= e((string) ($pick['closing_notes'] ?? '')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</article>
