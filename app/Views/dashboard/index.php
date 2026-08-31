<div class="dash-grid cols-3">
    <?= component('stat-card', ['label' => 'Plan', 'value' => $subscription['plan_name'] ?? 'Free']) ?>
    <?= component('stat-card', ['label' => 'Today’s notes', 'value' => (string) count($today)]) ?>
    <?= component('stat-card', ['label' => '30-day win rate', 'value' => ($stats['win_rate'] ?? 0) . '%', 'hint' => 'Demo board']) ?>
    <?= component('stat-card', ['label' => 'Account', 'value' => auth()->user()['email_verified_at'] ? 'Verified' : 'Pending email']) ?>
</div>

<section class="panel" style="margin-top:1rem;">
    <h2>Today’s Playbook</h2>
    <?php if (!$today): ?>
        <?= component('empty-state', ['title' => 'No notes in the morning window', 'body' => 'Check the archive or wait for the next publish.']) ?>
    <?php else: ?>
        <div class="pick-grid">
            <?php foreach ($today as $pick): ?>
                <?= component('pick-card', ['pick' => $pick]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="dash-grid cols-2" style="margin-top:1rem;">
    <section class="panel">
        <h2>Recent results</h2>
        <?php foreach ($results as $row): ?>
            <p><span class="badge badge-<?= e($row['status']) ?>"><?= e(pick_status_label($row['status'])) ?></span> <?= e($row['title']) ?></p>
        <?php endforeach; ?>
    </section>
    <section class="panel">
        <h2>Notifications</h2>
        <?php foreach ($notifications as $n): ?>
            <p><strong><?= e($n['title']) ?></strong><br><span class="muted"><?= e($n['body']) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$notifications): ?><p class="muted">No alerts yet.</p><?php endif; ?>
    </section>
</div>

<section class="panel" style="margin-top:1rem;">
    <h2>Subscription</h2>
    <p><?= e($subscription['plan_name'] ?? 'Free public access') ?> · <?= e($subscription['status'] ?? 'none') ?></p>
    <a class="btn btn-ghost" href="<?= e(url('/account/subscription')) ?>">Manage</a>
</section>
