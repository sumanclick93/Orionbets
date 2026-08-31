<?php
$path = request()->path();
$isLegal = in_array($path, ['/cookies', '/privacy', '/terms', '/disclaimer'], true);
$copy = cookie_consent();
?>
<div
    class="ob-cookies<?= $isLegal ? ' is-legal' : '' ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ob-cookies-title"
    aria-describedby="ob-cookies-copy"
    data-cookie-consent
>
    <div class="ob-cookies__scrim" aria-hidden="true"></div>
    <div class="ob-cookies__panel">
        <p class="ob-cookies__kicker"><?= e((string) $copy['cookie_kicker']) ?></p>
        <h2 class="ob-cookies__title" id="ob-cookies-title"><?= e((string) $copy['cookie_title']) ?></h2>
        <p class="ob-cookies__copy" id="ob-cookies-copy"><?= e((string) $copy['cookie_copy']) ?></p>
        <?php if (!empty($copy['cookie_items'])): ?>
        <ul class="ob-cookies__list">
            <?php foreach ($copy['cookie_items'] as $item): ?>
                <li><span><?= e((string) $item['label']) ?></span> <?= e((string) $item['text']) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <p class="ob-cookies__deny" data-cookie-deny-msg hidden>
            <?= e((string) $copy['cookie_deny']) ?>
        </p>
        <div class="ob-cookies__actions">
            <button type="button" class="btn btn-ghost" data-cookie-decline><?= e((string) $copy['cookie_decline']) ?></button>
            <button type="button" class="btn btn-primary" data-cookie-allow><?= e((string) $copy['cookie_allow']) ?></button>
        </div>
        <p class="ob-cookies__links">
            <a href="<?= e(url('/cookies')) ?>"><?= e((string) $copy['cookie_policy_link']) ?></a>
            <a href="<?= e(url('/privacy')) ?>"><?= e((string) $copy['cookie_privacy_link']) ?></a>
        </p>
    </div>
</div>
