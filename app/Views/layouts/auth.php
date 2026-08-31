<?php
ob_start();
?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="auth-shell">
    <a class="brand brand-lockup" href="<?= e(url('/')) ?>"><?= component('logo') ?></a>
    <main id="main" class="auth-card">
        <?= component('alert') ?>
        <?= $content ?>
    </main>
    <p class="auth-disclaimer"><?= e((string) settings('disclaimer')) ?></p>
</div>
<?= component('toast') ?>
<?php
$inner = ob_get_clean();
$content = $inner;
$bodyClass = 'is-auth';
include VIEW_PATH . '/layouts/shell.php';
