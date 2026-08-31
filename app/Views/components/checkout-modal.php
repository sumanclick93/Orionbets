<?php
$user = $authUser ?? auth()->user();
$signedIn = is_array($user);
$firstName = $signedIn ? trim((string) ($user['first_name'] ?? '')) : '';
$lastName = $signedIn ? trim((string) ($user['last_name'] ?? '')) : '';
$fullName = trim($firstName . ' ' . $lastName);
$userEmail = $signedIn ? (string) ($user['email'] ?? '') : '';
$hasDiscord = $signedIn && trim((string) ($user['discord_id'] ?? '')) !== '';
$paypalId = paypal_client_id();
$discordNext = intended_path((string) (request()->path() ?: '/')) ?: '/';
?>
<div
    class="ob-checkout"
    hidden
    data-checkout-brand="<?= e(site_name()) ?>"
    data-checkout-signed-in="<?= $signedIn ? '1' : '0' ?>"
    data-checkout-discord="<?= $hasDiscord ? '1' : '0' ?>"
    data-checkout-user-name="<?= e($fullName) ?>"
    data-checkout-user-first="<?= e($firstName) ?>"
    data-checkout-user-last="<?= e($lastName) ?>"
    data-checkout-user-email="<?= e($userEmail) ?>"
    data-checkout-login="<?= e(url('/login')) ?>"
    data-checkout-csrf="<?= e(csrf_token()) ?>"
    data-checkout-start="<?= e(url('/checkout/start')) ?>"
    data-checkout-status="<?= e(url('/checkout/status')) ?>"
    data-checkout-paypal-create="<?= e(url('/checkout/paypal/create-order')) ?>"
    data-checkout-paypal-capture="<?= e(url('/checkout/paypal/capture-order')) ?>"
    data-checkout-thanks="<?= e(url('/thank-you')) ?>"
    data-checkout-frame="<?= e(url('checkout-frame.php')) ?>"
    data-checkout-discord-auth="<?= e(url('/auth/discord')) ?>"
    data-paypal-client-id="<?= e($paypalId) ?>"
    data-paypal-env="<?= e(paypal_env()) ?>"
>
    <div class="ob-checkout__scrim" data-checkout-dismiss></div>
    <div class="ob-checkout__stage" role="dialog" aria-modal="true" aria-labelledby="ob-checkout-title">
        <div class="ob-checkout__top">
        <header class="ob-checkout__rail">
            <div>
                <p class="ob-checkout__kicker">Choose payment method</p>
                <h2 class="ob-checkout__title" id="ob-checkout-title">Complete payment</h2>
                <p class="ob-checkout__meta" data-checkout-meta></p>
            </div>
            <button type="button" class="ob-checkout__close" data-checkout-dismiss aria-label="Close checkout">
                <span aria-hidden="true">×</span>
            </button>
        </header>

        <div class="ob-checkout__chooser" data-checkout-chooser role="tablist" aria-label="Payment methods">
            <button
                type="button"
                class="ob-checkout__option is-active"
                data-checkout-tab="discord"
                role="tab"
                aria-selected="true"
                id="ob-checkout-tab-discord"
            >
                <span class="ob-checkout__option-kicker">Option A</span>
                <span class="ob-checkout__option-title">Pay via Discord</span>
                <span class="ob-checkout__option-copy">Upgrade.Chat embedded checkout</span>
            </button>
            <button
                type="button"
                class="ob-checkout__option<?= $paypalId === '' ? ' is-disabled' : '' ?>"
                data-checkout-tab="paypal"
                role="tab"
                aria-selected="false"
                id="ob-checkout-tab-paypal"
                <?= $paypalId === '' ? 'disabled' : '' ?>
            >
                <span class="ob-checkout__option-kicker">Option B</span>
                <span class="ob-checkout__option-title">Pay with PayPal</span>
                <span class="ob-checkout__option-copy">No Discord required</span>
            </button>
        </div>
        </div>

        <div class="ob-checkout__framewrap" data-checkout-viewport>
            <p class="ob-checkout__banner" data-checkout-error hidden role="alert"></p>

            <section class="ob-checkout__panel" data-checkout-panel="discord" role="tabpanel" aria-labelledby="ob-checkout-tab-discord">
                <div class="ob-checkout__discord" data-checkout-discord-gate<?= $hasDiscord ? ' hidden' : '' ?>>
                    <p class="ob-checkout__panel-copy">Connect Discord to continue. Checkout stays in this window.</p>
                    <a
                        class="btn btn-discord"
                        data-auth="discord"
                        data-checkout-discord-connect
                        href="<?= e(url('/auth/discord?next=' . rawurlencode($discordNext))) ?>"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M19.27 5.33A17.4 17.4 0 0 0 14.62 4c-.2.36-.43.85-.59 1.23a16.1 16.1 0 0 0-4.06 0A11.3 11.3 0 0 0 9.38 4 17.3 17.3 0 0 0 4.72 5.34C.96 10.96.05 16.44.33 21.87a17.5 17.5 0 0 0 5.28 2.67c.43-.58.81-1.2 1.14-1.84a11.4 11.4 0 0 1-1.8-.86c.15-.11.3-.22.44-.34a12.4 12.4 0 0 0 10.22 0c.15.12.3.23.44.34-.57.34-1.17.63-1.8.86.33.64.71 1.26 1.14 1.84a17.4 17.4 0 0 0 5.29-2.67c.33-6.3-.56-11.74-4.21-16.54ZM8.02 18.05c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.02 2.58-2.3 2.58Zm7.96 0c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.03 2.58-2.3 2.58Z"/>
                        </svg>
                        Connect with Discord to Continue
                    </a>
                </div>
                <iframe
                    class="ob-checkout__frame"
                    data-checkout-frame
                    hidden
                    title="Secure Discord checkout"
                    allow="payment *; publickey-credentials-get *"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
            </section>

            <section class="ob-checkout__panel" data-checkout-panel="paypal" role="tabpanel" aria-labelledby="ob-checkout-tab-paypal" hidden>
                <form class="ob-checkout__paypal" data-checkout-paypal-form novalidate>
                    <p class="ob-checkout__panel-copy">Pay without Discord. Enter the email you want for your Orion Bets account, then continue with PayPal.</p>
                    <div class="ob-checkout__fields">
                        <label>
                            First name
                            <input type="text" name="first_name" data-checkout-first-name autocomplete="given-name" required value="<?= e($firstName) ?>">
                        </label>
                        <label>
                            Last name
                            <input type="text" name="last_name" data-checkout-last-name autocomplete="family-name" required value="<?= e($lastName) ?>">
                        </label>
                    </div>
                    <label>
                        Email address
                        <input type="email" name="email" data-checkout-paypal-email autocomplete="email" inputmode="email" required value="<?= e($userEmail) ?>">
                    </label>
                    <p class="ob-checkout__hint">This email is locked to your membership. You can still log in to PayPal with a different wallet — the account is created with this address, not the PayPal login email.</p>
                    <div id="paypal-button-container" class="ob-checkout__paypal-buttons"></div>
                </form>
            </section>

            <div class="ob-checkout__load" data-checkout-load hidden>
                <span class="ob-checkout__spinner" aria-hidden="true"></span>
                <p data-checkout-load-copy>Opening checkout</p>
            </div>

            <div class="ob-checkout__done" data-checkout-done hidden>
                <p class="ob-checkout__done-kicker">Payment confirmed</p>
                <h3 data-checkout-done-title>You are in</h3>
                <p data-checkout-done-copy>Your order is confirmed. You can open billing history from your desk.</p>
                <p class="ob-checkout__done-meta" data-checkout-done-meta></p>
                <div class="ob-checkout__done-actions">
                    <a class="btn btn-primary" data-checkout-done-account href="<?= e(url('/account/subscription')) ?>">View billing history</a>
                    <a class="btn btn-primary" data-checkout-done-claim href="<?= e(url('/register')) ?>">Create account (using this email)</a>
                    <a class="btn btn-discord" data-auth="discord" data-checkout-done-discord href="<?= e(url('/auth/discord?next=' . rawurlencode('/dashboard'))) ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M19.27 5.33A17.4 17.4 0 0 0 14.62 4c-.2.36-.43.85-.59 1.23a16.1 16.1 0 0 0-4.06 0A11.3 11.3 0 0 0 9.38 4 17.3 17.3 0 0 0 4.72 5.34C.96 10.96.05 16.44.33 21.87a17.5 17.4 0 0 0 5.28 2.67c.43-.58.81-1.2 1.14-1.84a11.4 11.4 0 0 1-1.8-.86c.15-.11.3-.22.44-.34a12.4 12.4 0 0 0 10.22 0c.15.12.3.23.44.34-.57.34-1.17.63-1.8.86.33.64.71 1.26 1.14 1.84a17.4 17.4 0 0 0 5.29-2.67c.33-6.3-.56-11.74-4.21-16.54ZM8.02 18.05c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.02 2.58-2.3 2.58Zm7.96 0c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.03 2.58-2.3 2.58Z"/>
                        </svg>
                        Sign up using Discord
                    </a>
                </div>
            </div>
        </div>

        <footer class="ob-checkout__foot">
            <p>Checkout stays on this page. Discord uses Upgrade.Chat. PayPal Smart Buttons run on-site.</p>
        </footer>
    </div>
</div>
