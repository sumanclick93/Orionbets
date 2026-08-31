<?php
$session = is_array($session ?? null) ? $session : [];
$email = (string) ($session['email'] ?? '');
$orderId = (string) ($session['order_id'] ?? $session['transaction_id'] ?? '');
$amount = $session['amount'] ?? null;
$currency = (string) ($session['currency'] ?? 'USD');
$efCfg = $everflow ?? everflow_config();
$confirmed = !empty($paid);
$waiting = !empty($pending);
$loggedIn = auth()->check();
$guestCheckout = !$loggedIn;
$registerQuery = ['next' => '/dashboard'];
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $registerQuery['email'] = $email;
}
$registerUrl = url('/register?' . http_build_query($registerQuery));
?>
<section class="ob-thanks">
    <div class="container ob-thanks__card">
        <p class="kicker"><?= $confirmed ? 'Payment confirmed' : ($waiting ? 'Confirming payment' : 'Thanks for checking out') ?></p>
        <h1><?= $confirmed ? 'You are in.' : ($waiting ? 'Hang tight — we are confirming your payment.' : 'Check your email.') ?></h1>
        <?php if ($confirmed && $guestCheckout): ?>
            <p class="lede">Your guest checkout went through. Claim this purchase by creating an account with this email, or sign up with Discord if that Discord account uses the same email — subscriptions and payment history stay on this account.</p>
        <?php elseif ($confirmed): ?>
            <p class="lede">Payment is on this account. Open the desk for Playbook access and billing history.</p>
        <?php elseif ($waiting): ?>
            <p class="lede">Stay on this page. We are confirming the order. This usually takes a few seconds.</p>
        <?php else: ?>
            <p class="lede">If you just paid as a guest, use the same email or Discord to claim billing history on this account.</p>
        <?php endif; ?>

        <dl class="ob-thanks__meta">
            <?php if ($email !== ''): ?>
                <div><dt>Email</dt><dd><?= e($email) ?></dd></div>
            <?php endif; ?>
            <?php if ($orderId !== ''): ?>
                <div><dt>Order</dt><dd><?= e($orderId) ?></dd></div>
            <?php endif; ?>
            <?php if ($amount !== null && $amount !== ''): ?>
                <div><dt>Amount</dt><dd><?= e(is_numeric($amount) ? money((int) round(((float) $amount) * 100), $currency) : (string) $amount) ?></dd></div>
            <?php endif; ?>
        </dl>

        <div class="ob-thanks__actions">
            <?php if ($guestCheckout): ?>
                <a class="ob-btn" href="<?= e($registerUrl) ?>">Create account (using this email)</a>
                <?= component('discord-button', [
                    'label' => 'Sign up using Discord',
                    'next' => '/dashboard',
                    'class' => 'ob-btn ob-btn--discord',
                ]) ?>
            <?php else: ?>
                <a class="ob-btn" href="<?= e(url('/dashboard')) ?>">Open the desk</a>
                <a class="ob-btn ob-btn--ghost" href="<?= e(url('/account/subscription')) ?>">View billing history</a>
            <?php endif; ?>
        </div>
        <?php if ($guestCheckout): ?>
            <p class="fineprint">Use this email — or a Discord account with this email — so billing history stays on this account. 21+. Informational use only, not betting advice.</p>
        <?php else: ?>
            <p class="fineprint">21+. Informational use only, not betting advice.</p>
        <?php endif; ?>
    </div>
</section>

<script>
window.orionThankYou = <?= json_encode([
    'token' => $token ?? '',
    'paid' => $confirmed,
    'pending' => $waiting,
    'guest' => $guestCheckout,
    'amount' => is_numeric($amount) ? (float) $amount : null,
    'order_id' => $orderId,
    'email' => $email,
    'currency' => $currency,
    'everflow_transaction_id' => $everflowTid ?? '',
    'status_url' => url('/checkout/status'),
    'offer_id' => $efCfg['offer_id'] ?? null,
    'advertiser_id' => $efCfg['advertiser_id'] ?? null,
], JSON_UNESCAPED_SLASHES) ?>;
</script>
