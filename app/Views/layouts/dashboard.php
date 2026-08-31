<?php
ob_start();
?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-shell">
    <?= component('sidebar', ['variant' => 'dashboard']) ?>
    <button class="sidebar-scrim" type="button" data-sidebar-toggle aria-label="Close navigation" hidden></button>
    <div class="app-main">
        <?= component('topbar', ['title' => $title ?? 'Desk']) ?>
        <main id="main" class="app-content">
            <?= component('alert') ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?= component('toast') ?>
<?php
$inner = ob_get_clean();
$content = $inner;
$bodyClass = 'is-app';
include VIEW_PATH . '/layouts/shell.php';
