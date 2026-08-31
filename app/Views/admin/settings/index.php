<?php
$defaults = cookie_consent_defaults();
$legalLabels = [
    'cookies' => 'Public Cookie Policy page',
    'privacy' => 'Public Privacy page',
    'terms' => 'Public Terms page',
    'disclaimer' => 'Public Disclaimer page',
];
$val = static function (array $settings, array $defaults, string $key): string {
    return (string) ($settings[$key] ?? $defaults[$key] ?? '');
};
?>
<form method="post" class="panel">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="site">
    <h2>Site settings</h2>
    <?php foreach ([
        'site_name' => 'Site name',
        'tagline' => 'Tagline',
        'primary_color' => 'Primary color',
        'contact_email' => 'Contact email',
        'timezone' => 'Timezone',
        'social_x' => 'X / Twitter',
        'social_instagram' => 'Instagram',
        'social_youtube' => 'YouTube',
        'social_discord' => 'Discord',
        'countdown_label' => 'Countdown label',
        'countdown_at' => 'Countdown date/time',
        'seo_title' => 'SEO title',
        'dark_mode_default' => 'Default theme (light/dark/system)',
    ] as $key => $label): ?>
        <label><?= e($label) ?></label>
        <input name="<?= e($key) ?>" value="<?= e((string) ($settings[$key] ?? '')) ?>">
    <?php endforeach; ?>
    <label>SEO description</label>
    <textarea name="seo_description"><?= e((string) ($settings['seo_description'] ?? '')) ?></textarea>
    <label>Footer text</label>
    <textarea name="footer_text"><?= e((string) ($settings['footer_text'] ?? '')) ?></textarea>
    <label>Disclaimer</label>
    <textarea name="disclaimer"><?= e((string) ($settings['disclaimer'] ?? '')) ?></textarea>
    <button class="btn btn-primary" type="submit">Save settings</button>
</form>

<form method="post" class="panel" id="cookie-consent" style="margin-top:1rem;">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="cookie_consent">
    <p class="kicker">Cookies</p>
    <h2>Cookie consent modal</h2>
    <p class="muted admin-hint">This copy appears on the blocking cookie gate. Visitors stay locked out until they click Allow. Use <code>{site}</code> to insert the site name.</p>
    <label>Kicker</label>
    <input name="cookie_kicker" value="<?= e($val($settings, $defaults, 'cookie_kicker')) ?>">
    <label>Title</label>
    <input name="cookie_title" value="<?= e($val($settings, $defaults, 'cookie_title')) ?>">
    <label>Body copy</label>
    <textarea name="cookie_copy" class="is-short"><?= e($val($settings, $defaults, 'cookie_copy')) ?></textarea>
    <div class="form-row split">
        <div>
            <label>Allow button</label>
            <input name="cookie_allow" value="<?= e($val($settings, $defaults, 'cookie_allow')) ?>">
        </div>
        <div>
            <label>Decline button</label>
            <input name="cookie_decline" value="<?= e($val($settings, $defaults, 'cookie_decline')) ?>">
        </div>
    </div>
    <label>Decline message</label>
    <textarea name="cookie_deny" class="is-short"><?= e($val($settings, $defaults, 'cookie_deny')) ?></textarea>
    <p class="muted admin-hint" style="margin-top:1rem;">Cookie types shown in the modal. Leave a row blank to hide it.</p>
    <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="form-row split">
            <div>
                <label>Type <?= $i ?> label</label>
                <input name="cookie_item_<?= $i ?>_label" value="<?= e($val($settings, $defaults, 'cookie_item_' . $i . '_label')) ?>">
            </div>
            <div>
                <label>Type <?= $i ?> description</label>
                <input name="cookie_item_<?= $i ?>_text" value="<?= e($val($settings, $defaults, 'cookie_item_' . $i . '_text')) ?>">
            </div>
        </div>
    <?php endfor; ?>
    <div class="form-row split">
        <div>
            <label>Cookie Policy link label</label>
            <input name="cookie_policy_link" value="<?= e($val($settings, $defaults, 'cookie_policy_link')) ?>">
        </div>
        <div>
            <label>Privacy link label</label>
            <input name="cookie_privacy_link" value="<?= e($val($settings, $defaults, 'cookie_privacy_link')) ?>">
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Save cookie consent</button>
</form>

<div id="legal-pages"></div>
<?php foreach ($legal as $page): ?>
    <?php $isCookies = ($page['slug'] ?? '') === 'cookies'; ?>
    <form method="post" action="<?= e(url('/admin/legal')) ?>" class="panel" <?= $isCookies ? 'id="cookie-policy"' : '' ?> style="margin-top:1rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
        <p class="kicker"><?= e($legalLabels[$page['slug']] ?? 'Legal page') ?></p>
        <h3><?= e($page['title']) ?></h3>
        <p class="muted admin-hint">Public URL: <a href="<?= e(url('/' . ltrim((string) $page['slug'], '/'))) ?>"><?= e(url('/' . ltrim((string) $page['slug'], '/'))) ?></a></p>
        <label>Page title</label>
        <input name="title" value="<?= e($page['title']) ?>">
        <label>Page content</label>
        <textarea name="content"><?= e($page['content']) ?></textarea>
        <button class="btn btn-primary" type="submit">Save <?= e($isCookies ? 'cookie policy' : 'legal page') ?></button>
    </form>
<?php endforeach; ?>
