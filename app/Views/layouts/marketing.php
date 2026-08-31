<?php
ob_start();
?>
<a class="skip-link" href="#main">Skip to content</a>
<?= component('navbar', ['variant' => 'marketing']) ?>
<main id="main">
    <?= $content ?>
</main>
<?= component('footer') ?>
<?= component('toast') ?>
<?php
$inner = ob_get_clean();
$content = $inner;
$bodyClass = 'is-marketing';
include VIEW_PATH . '/layouts/shell.php';
