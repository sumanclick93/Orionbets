<div class="stat-grid five">
    <?= component('stat-card', ['label' => 'Users', 'value' => (string) $users]) ?>
    <?= component('stat-card', ['label' => 'Active subscribers', 'value' => (string) $subscribers]) ?>
    <?= component('stat-card', ['label' => 'DAU', 'value' => (string) $dau]) ?>
    <?= component('stat-card', ['label' => 'Published', 'value' => (string) $published]) ?>
    <?= component('stat-card', ['label' => 'Completed', 'value' => (string) $completed]) ?>
    <?= component('stat-card', ['label' => 'Revenue (demo)', 'value' => money((int) $revenue)]) ?>
    <?= component('stat-card', ['label' => 'Win rate', 'value' => ($stats['win_rate'] ?? 0) . '%']) ?>
</div>
<section class="panel" style="margin-top:1rem;">
    <h2>Recent registrations</h2>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td data-label="Name"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td data-label="Email"><?= e($row['email']) ?></td>
                <td data-label="Joined"><?= e(format_datetime($row['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
