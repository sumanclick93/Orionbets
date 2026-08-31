<header class="topbar">
    <button class="nav-toggle" type="button" data-sidebar-toggle aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
    <h1><?= e($title ?? 'Desk') ?></h1>
    <div class="topbar-actions">
        <?= component('theme-toggle') ?>
        <a class="btn btn-ghost btn-small topbar-home" href="<?= e(url('/')) ?>"><span class="topbar-home__full">Marketing site</span><span class="topbar-home__short">Site</span></a>
    </div>
</header>
