<?php
ob_start();
?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-shell">
    <?= component('sidebar', ['variant' => 'admin']) ?>
    <button class="sidebar-scrim" type="button" data-sidebar-toggle aria-label="Close navigation" hidden></button>
    <div class="app-main">
        <?= component('topbar', ['title' => $title ?? 'Operations']) ?>
        <main id="main" class="app-content">
            <?= component('alert') ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?= component('toast') ?>
<?= component('an-sync-modal') ?>
<?php
$inner = ob_get_clean();
$content = $inner;
$bodyClass = 'is-app';
include VIEW_PATH . '/layouts/shell.php';
