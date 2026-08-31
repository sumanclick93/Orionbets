<section class="section">
    <div class="container">
        <p class="kicker">The Playbook</p>
        <h1><?= !empty($playbook) ? 'Active Playbook' : 'Daily picks' ?></h1>
        <p class="lede">Upcoming games and live odds. Paid Members see the full play, price, and write-up. Free Members and guests see the slate with selections locked. Informational use only, not betting advice.</p>
        <div class="filter-bar">
            <form method="get">
                <div><label>Sport</label>
                    <select name="sport">
                        <option value="">All</option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?= e($sport['slug']) ?>" <?= ($filters['sport'] ?? '') === $sport['slug'] ? 'selected' : '' ?>><?= e($sport['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>League</label>
                    <select name="league">
                        <option value="">All</option>
                        <?php foreach ($leagues as $league): ?>
                            <option value="<?= e($league['slug']) ?>" <?= ($filters['league'] ?? '') === $league['slug'] ? 'selected' : '' ?>><?= e($league['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach (['pending','scheduled','published','in_progress'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(pick_status_label($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Access</label>
                    <select name="access">
                        <option value="">All</option>
                        <option value="free" <?= ($filters['access'] ?? '') === 'free' ? 'selected' : '' ?>>Free</option>
                        <option value="premium" <?= ($filters['access'] ?? '') === 'premium' ? 'selected' : '' ?>>Playbook</option>
                    </select>
                </div>
                <div><label>Date</label><input type="date" name="date" value="<?= e($filters['date'] ?? '') ?>"></div>
                <div><label>Search</label><input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Event or title"></div>
                <button class="btn btn-primary" type="submit">Filter</button>
            </form>
        </div>
        <?php if (!$picks): ?>
            <?= component('empty-state') ?>
        <?php else: ?>
            <div class="pick-grid">
                <?php foreach ($picks as $pick): ?>
                    <?= component('pick-card', ['pick' => $pick]) ?>
                <?php endforeach; ?>
            </div>
            <?= component('pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
        <?php endif; ?>
    </div>
</section>
