<?php
$variant = $variant ?? 'dashboard';
$user = $authUser ?? auth()->user();
$admin = $variant === 'admin';
?>
<aside class="sidebar">
    <button class="sidebar-close" type="button" data-sidebar-toggle aria-label="Close navigation">×</button>
    <a class="brand brand-lockup" href="<?= e(url($admin ? '/admin' : '/dashboard')) ?>"><?= component('logo') ?></a>
    <p class="sidebar-kicker"><?= $admin ? 'Operations' : 'Member desk' ?></p>
    <nav>
        <?php if ($admin): ?>
            <a class="<?= nav_class('/admin', true) ?>" href="<?= e(url('/admin')) ?>">Overview</a>
            <?php if (auth()->isAdmin()): ?>
                <a class="<?= nav_class('/admin/users') ?>" href="<?= e(url('/admin/users')) ?>">Members</a>
                <a class="<?= nav_class('/admin/transactions') ?>" href="<?= e(url('/admin/transactions')) ?>">Transactions</a>
                <a class="<?= nav_class('/admin/plans') ?>" href="<?= e(url('/admin/plans')) ?>">Plans</a>
                <a class="<?= nav_class('/admin/cms') ?>" href="<?= e(url('/admin/cms')) ?>">CMS & Assets</a>
            <?php endif; ?>
            <a class="<?= nav_class('/admin/picks') ?>" href="<?= e(url('/admin/picks')) ?>">Picks</a>
            <a class="<?= nav_class('/admin/events') ?>" href="<?= e(url('/admin/events')) ?>">Events</a>
            <?php if (auth()->isAdmin()): ?>
                <a class="<?= nav_class('/admin/sync') ?>" href="<?= e(url('/admin/sync')) ?>">Sync</a>
                <a class="<?= nav_class('/admin/geo') ?>" href="<?= e(url('/admin/geo')) ?>">Geo block</a>
                <a class="<?= nav_class('/admin/everflow') ?>" href="<?= e(url('/admin/everflow')) ?>">Everflow</a>
                <a class="<?= nav_class('/admin/settings') ?>" href="<?= e(url('/admin/settings')) ?>">Settings</a>
            <?php endif; ?>
            <a class="<?= nav_class('/admin/faqs') ?>" href="<?= e(url('/admin/faqs')) ?>">FAQs</a>
            <a class="<?= nav_class('/admin/messages') ?>" href="<?= e(url('/admin/messages')) ?>">Inbox</a>
            <a href="<?= e(url('/dashboard')) ?>">Member view</a>
        <?php else: ?>
            <a class="<?= nav_class('/dashboard', true) ?>" href="<?= e(url('/dashboard')) ?>">Today</a>
            <a class="<?= nav_class('/dashboard/picks') ?>" href="<?= e(url('/dashboard/picks')) ?>">Playbook</a>
            <a class="<?= nav_class('/dashboard/results') ?>" href="<?= e(url('/dashboard/results')) ?>">Results</a>
            <a class="<?= nav_class('/performance') ?>" href="<?= e(url('/performance')) ?>">Performance</a>
            <a class="<?= nav_class('/account/subscription') ?>" href="<?= e(url('/account/subscription')) ?>">Subscription</a>
            <a class="<?= nav_class('/account/settings') ?>" href="<?= e(url('/account/settings')) ?>">Settings</a>
            <?php if (auth()->isEditor()): ?>
                <a href="<?= e(url('/admin')) ?>">Operations</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
    <div class="sidebar-user">
        <strong><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></strong>
        <span><?= e($user['email'] ?? '') ?></span>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-small" type="submit">Sign out</button>
        </form>
    </div>
</aside>
