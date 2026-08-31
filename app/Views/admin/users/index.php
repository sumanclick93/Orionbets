<?php
$q = (string) ($q ?? '');
$status = (string) ($status ?? '');
$role = (string) ($role ?? '');
$tier = (string) ($tier ?? '');
$perPage = (int) ($perPage ?? 20);

$statusFilters = [
    '' => 'All status',
    'active' => 'Active',
    'guest' => 'Guest',
    'suspended' => 'Suspended',
    'deleted' => 'Deleted',
];

$roleFilters = [
    '' => 'All roles',
    'user' => 'User / Free Member',
    'premium_user' => 'Paid Member (Playbook)',
    'editor' => 'Editor',
    'admin' => 'Admin',
    'super_admin' => 'Super Admin',
];

$tierFilters = [
    '' => 'All tiers',
    'paid' => 'Paid Member',
    'free' => 'Free Member',
];

$filterUrl = static function (array $overrides) use ($q, $status, $role, $tier, $perPage): string {
    $merged = array_merge([
        'q' => $q,
        'status' => $status,
        'role' => $role,
        'tier' => $tier,
        'per_page' => $perPage,
    ], $overrides);

    $clean = array_filter($merged, static fn ($v) => $v !== '' && $v !== 'all');
    return url('/admin/users' . ($clean ? '?' . http_build_query($clean) : ''));
};
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Operations</p>
        <h2>Members & Users (<?= number_format((int) ($total ?? 0)) ?>)</h2>
    </div>
    <div class="page-toolbar__actions">
        <a class="btn btn-ghost btn-small" href="<?= e($exportUrl ?? url('/admin/users/export-csv')) ?>" download>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.35rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export to CSV
        </a>
    </div>
</div>

<div class="range-tabs" style="margin-bottom:0.75rem;">
    <?php foreach ($statusFilters as $value => $label): ?>
        <a class="<?= $status === $value ? 'is-active' : '' ?>" href="<?= e($filterUrl(['status' => $value, 'page' => 1])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('/admin/users')) ?>" class="filter-bar" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:1.25rem;">
    <?php if ($status !== ''): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
    <?php endif; ?>

    <input style="flex:1;min-width:14rem;" name="q" value="<?= e($q) ?>" placeholder="Search name, email, Discord ID, User ID...">

    <select name="tier" style="width:auto;min-width:8.5rem;" onchange="this.form.submit()">
        <?php foreach ($tierFilters as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= $tier === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="role" style="width:auto;min-width:9rem;" onchange="this.form.submit()">
        <?php foreach ($roleFilters as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= $role === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($q !== '' || $status !== '' || $role !== '' || $tier !== ''): ?>
        <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/users')) ?>">Reset</a>
    <?php endif; ?>
</form>

<?php if (!$users): ?>
    <?= component('empty-state', [
        'title' => 'No members found',
        'body' => ($q !== '' || $status !== '' || $role !== '' || $tier !== '')
            ? 'Try adjusting your search terms or filter selections.'
            : 'New registrations and guest checkouts will appear here.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table" data-interactive-table>
            <thead>
                <tr>
                    <th data-sort="number" style="width:4.5rem;">User ID</th>
                    <th data-sort="text">Full Name</th>
                    <th data-sort="text">Email</th>
                    <th data-sort="text">Discord Status</th>
                    <th>Assigned Roles</th>
                    <th data-sort="text">Tier Badge</th>
                    <th data-sort="text">Account Status</th>
                    <th data-sort="date">Registered Date</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                $deleted = !empty($user['deleted_at']);
                $suspended = !$deleted && empty($user['is_active']);
                $guest = !$deleted && !empty($user['is_guest']);
                $isPaid = !empty($user['is_paid']);
                $hasDiscord = !empty($user['discord_id']);
                ?>
                <tr>
                    <td data-label="User ID" style="font-family:var(--font-mono);font-size:0.85rem;">
                        #<?= (int) ($user['id'] ?? 0) ?>
                    </td>
                    <td data-label="Full Name">
                        <strong><?= e(trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')) ?: '—') ?></strong>
                        <?php if ($guest): ?>
                            <span class="badge badge-demo" style="margin-left:0.35rem;font-size:0.7rem;">Guest</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Email">
                        <?= e((string) ($user['display_email'] ?? $user['email'] ?? '')) ?>
                    </td>
                    <td data-label="Discord Status">
                        <?php if ($hasDiscord): ?>
                            <span class="badge badge-won" title="Discord ID: <?= e((string) $user['discord_id']) ?>" style="display:inline-flex;align-items:center;gap:0.3rem;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994.021-.041.001-.09-.041-.106a13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.894.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                Linked
                            </span>
                        <?php else: ?>
                            <span class="muted" style="font-size:0.85rem;">None</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Assigned Roles">
                        <?php if (!empty($user['roles'])): ?>
                            <?php foreach ($user['roles'] as $r): ?>
                                <span class="badge" style="font-size:0.75rem;margin:0.1rem;"><?= e($r) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted">user</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tier Badge">
                        <?php if ($isPaid): ?>
                            <span class="badge badge-won" style="font-weight:600;letter-spacing:0.02em;">★ Paid Member</span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--color-surface-alt);color:var(--color-text-muted);">Free Member</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Account Status">
                        <?php if ($deleted): ?>
                            <span class="badge badge-lost">Deleted</span>
                        <?php elseif ($suspended): ?>
                            <span class="badge badge-push">Suspended</span>
                        <?php elseif ($guest): ?>
                            <span class="badge badge-demo">Guest</span>
                        <?php else: ?>
                            <span class="badge badge-won">Active</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Registered Date" style="font-size:0.85rem;white-space:nowrap;">
                        <?= e(format_datetime((string) ($user['created_at'] ?? ''))) ?>
                    </td>
                    <td data-label="Actions" style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/users/' . $user['id'])) ?>">View History</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
<?php endif; ?>
