<?php
$customLogo = cms('site_logo_url', '');
?>
<?php if ($customLogo !== ''): ?>
    <img src="<?= e(url($customLogo)) ?>" alt="<?= e(site_name()) ?>" class="brand-logo-img" style="width:auto;height:28px;max-height:36px;max-width:220px;object-fit:contain;display:inline-block;vertical-align:middle;">
<?php else: ?>
    <svg class="mark" viewBox="0 0 28 28" width="22" height="22" aria-hidden="true">
        <circle cx="14" cy="14" r="12.2" fill="none" stroke="currentColor" stroke-width="1.6"/>
        <path d="M14 4.8v18.4M4.8 14h18.4" stroke="currentColor" stroke-width="1.2"/>
        <circle cx="14" cy="14" r="2.2" fill="currentColor"/>
    </svg>
    <span class="brand-word"><?= e(site_name()) ?></span>
<?php endif; ?>
