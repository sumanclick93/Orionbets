<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= e(cms('site_favicon_url') ?: asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? site_name()) ?></title>
    <meta name="description" content="<?= e($metaDescription ?? (string) settings('seo_description')) ?>">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <meta property="og:title" content="<?= e($title ?? site_name()) ?>">
    <meta property="og:description" content="<?= e($metaDescription ?? (string) settings('seo_description')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title ?? site_name()) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription ?? (string) settings('seo_description')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Archivo:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&family=Permanent+Marker&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20260829g">
    <?php if (paypal_client_id() !== ''): ?>
    <link rel="preconnect" href="https://www.paypal.com">
    <link rel="preconnect" href="https://www.sandbox.paypal.com">
    <link rel="preconnect" href="https://www.paypalobjects.com">
    <?php endif; ?>
    <?php if (settings('primary_color') && ($bodyClass ?? 'is-marketing') !== 'is-marketing'): ?>
    <style>:root, html[data-theme="light"], html[data-theme="dark"] { --color-primary: <?= e((string) settings('primary_color')) ?>; }</style>
    <?php endif; ?>
    <script>
      (function () {
        var stored = localStorage.getItem('edgeplay-theme');
        var user = <?= json_encode($authUser['theme_preference'] ?? null) ?>;
        var fallback = <?= json_encode(settings('dark_mode_default') ?: 'dark') ?>;
        var mode = stored || user || fallback || 'dark';
        if (mode === 'system') {
          mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', mode);
        var phpOk = <?= json_encode(($_COOKIE['orion_cookie_consent'] ?? '') === 'accepted') ?>;
        var localOk = false;
        try { localOk = localStorage.getItem('orion-cookie-consent') === 'accepted'; } catch (e) {}
        document.documentElement.classList.add(phpOk || localOk ? 'cookie-ok' : 'cookie-pending');
      })();
    </script>
</head>
<body class="<?= e($bodyClass ?? 'is-marketing') ?>">
<?php if (cms('promo_banner_enabled') === '1' && cms('promo_banner_text') !== '' && ($bodyClass ?? 'is-marketing') === 'is-marketing'): ?>
<div style="background:var(--color-primary);color:var(--ink);padding:0.45rem 1rem;text-align:center;font-size:0.82rem;font-weight:600;letter-spacing:0.04em;position:relative;z-index:40;">
    <?php if (cms('promo_banner_url') !== ''): ?>
        <a href="<?= e(url(cms('promo_banner_url'))) ?>" style="color:inherit;text-decoration:underline;">
            <?= e(cms('promo_banner_text')) ?> →
        </a>
    <?php else: ?>
        <?= e(cms('promo_banner_text')) ?>
    <?php endif; ?>
</div>
<?php endif; ?>
<?= $content ?? '' ?>
<?php
$everflowCfg = everflow_config();
$everflowSdk = everflow_sdk_url();
?>
<script type="application/json" id="orion-everflow-config"><?= json_encode([
    'enabled' => !empty($everflowCfg['enabled']),
    'capture' => in_array(($bodyClass ?? 'is-marketing'), ['is-marketing', 'is-auth'], true),
    'offer_id' => $everflowCfg['offer_id'] !== '' ? (int) $everflowCfg['offer_id'] : null,
    'advertiser_id' => $everflowCfg['advertiser_id'] !== '' ? (int) $everflowCfg['advertiser_id'] : null,
    'affiliate_id' => ($everflowCfg['affiliate_id'] ?? '') !== '' ? (int) $everflowCfg['affiliate_id'] : null,
    'nid' => $everflowCfg['nid'] !== '' ? $everflowCfg['nid'] : null,
    'ingest_url' => url('/everflow/ingest'),
], JSON_UNESCAPED_SLASHES) ?></script>
<?php if ($everflowSdk !== ''): ?>
<script src="<?= e($everflowSdk) ?>" async></script>
<?php endif; ?>
<script src="<?= e(asset('js/everflow.js')) ?>?v=20260829f" defer></script>
    <script src="<?= e(asset('js/app.js')) ?>?v=20260829g" defer></script>
<?= component('confirm-dialog') ?>
<?= component('checkout-modal') ?>
<?php if (($_COOKIE['orion_cookie_consent'] ?? '') !== 'accepted'): ?>
<?= component('cookie-consent') ?>
<?php endif; ?>
<?php if (!empty($charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script src="<?= e(asset('js/charts.js')) ?>?v=20260821n" defer></script>
<?php endif; ?>
</body>
</html>
