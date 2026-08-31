<div class="stat-card">
    <p class="stat-label"><?= e($label ?? '') ?></p>
    <p class="stat-value"><?= e((string) ($value ?? '—')) ?></p>
    <?php if (!empty($hint)): ?><p class="muted"><?= e($hint) ?></p><?php endif; ?>
</div>
