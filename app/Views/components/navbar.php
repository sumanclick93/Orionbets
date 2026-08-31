<?php
$user = $authUser ?? auth()->user();
$variant = $variant ?? 'marketing';
$playbookActive = is_active_path('/the-playbook') || is_active_path('/pricing') || is_active_path('/theplaybook-store');
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand brand-lockup" href="<?= e(url('/')) ?>"><?= component('logo') ?></a>
        <nav class="nav-desktop" aria-label="Primary">
            <a class="<?= nav_class('/') ?>" href="<?= e(url('/')) ?>">Home</a>
            <a class="<?= nav_class('/about') ?>" href="<?= e(url('/about')) ?>">About Us</a>
            <a class="<?= nav_class('/affiliates') ?>" href="<?= e(url('/affiliates')) ?>">Affiliates</a>
            <a class="<?= $playbookActive ? 'is-active' : '' ?>" href="<?= e(url('/the-playbook')) ?>">The Playbook</a>
        </nav>
        <div class="header-actions">
            <?php if ($user): ?>
                <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>">Desk</a>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= e(url('/login')) ?>">Login</a>
                <a class="btn btn-primary" href="<?= e(url('/the-playbook')) ?>">Get the picks</a>
            <?php endif; ?>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" data-nav-toggle>
                <span></span><span></span><span></span>
                <span class="sr-only">Menu</span>
            </button>
        </div>
    </div>
</header>
<button class="nav-scrim" type="button" data-nav-toggle data-nav-scrim aria-label="Close navigation" hidden></button>
<div class="mobile-nav" id="mobile-nav" hidden>
    <button class="sidebar-close" type="button" data-nav-toggle aria-label="Close navigation">×</button>
    <a class="brand brand-lockup" href="<?= e(url('/')) ?>"><?= component('logo') ?></a>
    <p class="sidebar-kicker">The desk</p>
    <nav aria-label="Mobile">
        <a class="<?= nav_class('/') ?>" href="<?= e(url('/')) ?>">Home</a>
        <a class="<?= nav_class('/about') ?>" href="<?= e(url('/about')) ?>">About Us</a>
        <a class="<?= nav_class('/affiliates') ?>" href="<?= e(url('/affiliates')) ?>">Affiliates</a>
        <a class="<?= $playbookActive ? 'is-active' : '' ?>" href="<?= e(url('/the-playbook')) ?>">The Playbook</a>
    </nav>
    <div class="mobile-nav__ctas">
        <?php if ($user): ?>
            <a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">Open desk</a>
        <?php else: ?>
            <a class="btn btn-ghost" href="<?= e(url('/login')) ?>">Login</a>
            <a class="btn btn-primary" href="<?= e(url('/the-playbook')) ?>">Get the picks</a>
        <?php endif; ?>
    </div>
</div>
