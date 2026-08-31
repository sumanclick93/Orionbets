<?php
$plan = $plan ?? [];
$features = json_decode_array($plan['features'] ?? '[]');
$current = $current ?? false;
$hasCheckout = plan_has_checkout($plan);
$isFree = (int) ($plan['price_cents'] ?? 0) === 0;
$cta = $isFree ? 'Stay on Free' : 'Get Access Now';
?>
<article class="pricing-card <?= !empty($plan['is_featured']) ? 'is-featured' : '' ?>">
    <?php if (!empty($plan['badge'])): ?><span class="badge badge-accent"><?= e($plan['badge']) ?></span><?php endif; ?>
    <h3><?= e($plan['name'] ?? '') ?></h3>
    <p class="price">
        <?php if ($isFree): ?>
            Free
        <?php else: ?>
            <?= e(money((int) $plan['price_cents'], $plan['currency'] ?? 'USD')) ?><small>/<?= e($plan['billing_interval'] ?? 'month') ?></small>
        <?php endif; ?>
    </p>
    <p><?= e($plan['description'] ?? '') ?></p>
    <ul>
        <?php foreach ($features as $feature): ?>
            <li><?= e(is_string($feature) ? $feature : '') ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($current): ?>
        <span class="btn btn-ghost" aria-disabled="true">Current plan</span>
    <?php elseif ($hasCheckout): ?>
        <button
            class="btn btn-primary"
            type="button"
            data-checkout
            data-checkout-url="<?= e(plan_payment_url($plan)) ?>"
            data-checkout-title="<?= e($plan['name'] ?? 'Checkout') ?>"
            data-checkout-price="<?= e(plan_price_label($plan)) ?>"
            data-checkout-interval="<?= e($plan['billing_interval'] ?? 'month') ?>"
            data-checkout-plan-id="<?= (int) ($plan['id'] ?? 0) ?>"
        ><?= e($cta) ?></button>
        <p class="fineprint">Opens on-site checkout. Pay with Discord or PayPal — no page redirect.</p>
    <?php elseif (auth()->check()): ?>
        <form method="post" action="<?= e(url('/account/subscription')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
            <input type="hidden" name="action" value="subscribe">
            <button class="btn btn-primary" type="submit"><?= e($cta) ?></button>
        </form>
        <p class="fineprint">Checkout link not set yet. Ask an admin to paste the Upgrade.Chat payment URL on this plan.</p>
    <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('/register')) ?>">Get Access Now</a>
    <?php endif; ?>
</article>
