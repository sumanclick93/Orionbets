<?php
$pick = $pick ?? [];
$gated = $gated ?? pick_should_gate($pick);
$matchup = pick_matchup_label($pick);
$selection = pick_selection_label($pick);
$odds = trim((string) ($pick['odds'] ?? ''));
$units = $pick['units'] ?? null;
$book = trim((string) ($pick['sportsbook'] ?? ''));
$start = $pick['start_time'] ?? $pick['event_at'] ?? $pick['published_at'] ?? null;
?>
<article class="pick-card <?= !empty($pick['is_premium']) ? 'is-premium' : '' ?><?= $gated ? ' is-gated' : '' ?>">
    <header>
        <span class="badge"><?= e($pick['sport_name'] ?? $pick['sport'] ?? '') ?></span>
        <?php if (!empty($pick['league_name']) || !empty($pick['league'])): ?><span class="muted"><?= e($pick['league_name'] ?? $pick['league']) ?></span><?php endif; ?>
        <?php if (!empty($pick['is_premium'])): ?><span class="badge badge-accent">Premium</span><?php endif; ?>
        <span class="badge badge-<?= e($pick['status'] ?? 'published') ?>"><?= e(pick_status_label((string) ($pick['status'] ?? ''))) ?></span>
    </header>
    <h3><a href="<?= e(url('/picks/' . $pick['slug'])) ?>"><?= e($matchup) ?></a></h3>
    <p class="event-line"><?= e($start ? format_datetime($start) : 'Desk note') ?><?= $book !== '' && !$gated ? ' · ' . e($book) : '' ?></p>
    <div class="pick-card__body<?= $gated ? ' is-locked' : '' ?>">
        <p class="pick-card__line">
            <strong><?= $gated ? 'Selection locked' : e($selection !== '' ? $selection : 'Playbook pick') ?></strong>
            <?php if (!$gated && $odds !== ''): ?> <span class="muted"><?= e($odds) ?></span><?php endif; ?>
            <?php if (!$gated && $units !== null && $units !== ''): ?> <span class="muted"><?= e((string) $units) ?>u</span><?php endif; ?>
        </p>
        <p><?= e($gated ? 'Full analysis is reserved for Premium members.' : (string) ($pick['analysis_excerpt'] ?? '')) ?></p>
        <footer>
            <span class="confidence">Confidence <strong><?= $gated ? '—' : (int) ($pick['confidence'] ?? 0) ?></strong></span>
            <time datetime="<?= e($pick['published_at'] ?? '') ?>">Published <?= e(format_datetime($pick['published_at'] ?? null)) ?></time>
        </footer>
    </div>
    <?php if ($gated): ?>
        <?= component('pick-gate', ['next' => '/picks/' . ($pick['slug'] ?? '')]) ?>
    <?php endif; ?>
</article>
