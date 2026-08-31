<?php
$plan = $plan ?? featured_paid_plan();
$guest = !auth()->check();
$planId = (int) ($plan['id'] ?? 0);
$hasCheckout = is_array($plan) && plan_has_checkout($plan);
$next = $next ?? request()->path();
$loginUrl = url('/login' . (intended_path($next) ? ('?next=' . rawurlencode($next)) : ''));
?>
<div class="pick-gate">
    <?php if ($guest): ?>
        <p class="pick-gate__title">Log In or Subscribe to Access Live Playbook</p>
        <p class="pick-gate__copy">Create a free desk or subscribe to unlock upcoming lines, units, sportsbook odds, and analysis.</p>
        <div class="pick-gate__actions">
            <a class="btn btn-ghost" href="<?= e($loginUrl) ?>">Sign In</a>
            <?php if ($hasCheckout): ?>
                <button
                    class="btn btn-primary"
                    type="button"
                    data-checkout
                    data-plan-id="<?= $planId ?>"
                    data-checkout-plan-id="<?= $planId ?>"
                    data-checkout-url="<?= e(plan_payment_url($plan)) ?>"
                    data-checkout-title="<?= e((string) ($plan['name'] ?? 'Get Access Now')) ?>"
                    data-checkout-price="<?= e(plan_price_label($plan)) ?>"
                    data-checkout-interval="<?= e((string) ($plan['billing_interval'] ?? 'month')) ?>"
                >Get Access Now</button>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('/the-playbook')) ?>">Get Access Now</a>
            <?php endif; ?>
        </div>
    <?php elseif (auth()->isFreeMember()): ?>
        <p class="pick-gate__title">Upgrade to Premium to Unlock Live Picks &amp; Lines</p>
        <p class="pick-gate__copy">Free members can browse the slate and past results. Live lines, units, sportsbook odds, and write-ups unlock with a Paid Member plan.</p>
        <div class="pick-gate__actions">
            <?php if ($hasCheckout): ?>
                <button
                    class="btn btn-primary"
                    type="button"
                    data-checkout
                    data-plan-id="<?= $planId ?>"
                    data-checkout-plan-id="<?= $planId ?>"
                    data-checkout-url="<?= e(plan_payment_url($plan)) ?>"
                    data-checkout-title="<?= e((string) ($plan['name'] ?? 'Upgrade to Premium')) ?>"
                    data-checkout-price="<?= e(plan_price_label($plan)) ?>"
                    data-checkout-interval="<?= e((string) ($plan['billing_interval'] ?? 'month')) ?>"
                >Upgrade to Premium</button>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('/the-playbook')) ?>">Upgrade to Premium</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
