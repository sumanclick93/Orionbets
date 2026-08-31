<?php
declare(strict_types=1);

$user = $user ?? auth()->user() ?? [];
$subscription = $subscription ?? null;
$plans = $plans ?? [];
$featuredPlan = featured_paid_plan();
$isPremium = is_premium();
$hasDiscord = !empty($user['discord_id']);

$prefMap = [];
foreach ($prefs ?? [] as $p) {
    $prefMap[$p['channel'] . '.' . $p['event_type']] = (int) ($p['enabled'] ?? 0) === 1;
}
$checked = static fn (string $key): string => !empty($prefMap[$key]) ? 'checked' : '';

$providerLabel = match (strtolower((string) ($subscription['provider'] ?? ''))) {
    'paypal' => 'PayPal',
    'upgradechat', 'upgrade.chat' => 'Upgrade.Chat',
    'stripe' => 'Stripe',
    'demo' => 'Demo / Manual',
    default => !empty($subscription['provider']) ? ucfirst((string) $subscription['provider']) : 'Direct',
};
?>
<div class="dash-grid cols-2">
    <!-- Left Column: Profile Details & Notification Preferences -->
    <div class="panel">
        <h2>Profile Details</h2>
        <form method="post" action="<?= e(url('/account/settings')) ?>" class="flow-form" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Profile Avatar Upload -->
            <div style="margin-bottom:1.25rem;padding:0.85rem;background:var(--color-surface-alt, rgba(255,255,255,0.03));border:1px solid var(--color-border);border-radius:var(--radius);">
                <label for="avatar_file" style="margin-bottom:0.4rem;font-weight:600;display:block;">Profile Picture / Avatar</label>
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= e(url($user['avatar'])) ?>" alt="User Avatar" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--color-primary);">
                    <?php else: ?>
                        <div style="width:52px;height:52px;border-radius:50%;background:var(--color-surface);border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;color:var(--color-text-muted);font-weight:700;font-size:1.1rem;">
                            <?= strtoupper(substr((string) ($user['first_name'] ?? 'U'), 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div style="flex:1;min-width:180px;">
                        <input id="avatar_file" name="avatar_file" type="file" accept="image/*" style="font-size:0.85rem;">
                        <p class="field-hint" style="margin:0.25rem 0 0;">Upload a PNG, JPG, or WEBP photo.</p>
                    </div>
                </div>
                <?php if (!empty($user['avatar'])): ?>
                    <label class="checkbox" style="font-size:0.82rem;color:var(--color-danger, #ef4444);margin:0.25rem 0 0;">
                        <input type="checkbox" name="remove_avatar" value="1">
                        <span>Remove current avatar</span>
                    </label>
                <?php endif; ?>
            </div>

            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" value="<?= e($user['first_name'] ?? '') ?>" required maxlength="80">

            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" value="<?= e($user['last_name'] ?? '') ?>" required maxlength="80">

            <label for="email">Registered Email</label>
            <input id="email" type="email" value="<?= e($user['email'] ?? '') ?>" disabled readonly style="opacity:0.75; cursor:not-allowed;">
            <p class="field-hint">Your email is tied to your login and Discord matching verification.</p>

            <label for="timezone">Time zone</label>
            <input id="timezone" name="timezone" value="<?= e($user['timezone'] ?? 'UTC') ?>" placeholder="UTC, America/New_York, etc.">

            <label for="theme_preference">Theme</label>
            <select id="theme_preference" name="theme_preference">
                <?php foreach (['system' => 'System default', 'dark' => 'Dark', 'light' => 'Light'] as $k => $label): ?>
                    <option value="<?= $k ?>" <?= ($user['theme_preference'] ?? 'system') === $k ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--color-border);">
                <h3>Email notifications</h3>
                <?php foreach (['daily_pick' => 'New daily pick releases', 'pick_result' => 'Pick settlement & results', 'subscription' => 'Subscription & renewal alerts', 'account' => 'Security & account notices'] as $k => $label): ?>
                    <label class="checkbox" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; font-size:0.9rem;">
                        <input type="checkbox" name="email_<?= $k ?>" value="1" <?= $checked('email.' . $k) ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>

                <h3 style="margin-top:1.2rem;">In-app notifications</h3>
                <?php foreach (['daily_pick' => 'New daily pick alerts', 'pick_result' => 'Pick result notifications', 'subscription' => 'Billing status updates', 'account' => 'Account activity'] as $k => $label): ?>
                    <label class="checkbox" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; font-size:0.9rem;">
                        <input type="checkbox" name="inapp_<?= $k ?>" value="1" <?= $checked('in_app.' . $k) ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:1.5rem;">
                <button class="btn btn-primary" type="submit">Save Profile Changes</button>
            </div>
        </form>
    </div>

    <!-- Right Column: Membership Status, Discord Link, Password & Security -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">
        
        <!-- Active Membership & Subscription Status View -->
        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                <h2 style="margin:0;">Membership Status</h2>
                <?php if ($isPremium): ?>
                    <span class="badge badge-accent" style="font-weight:600; font-size:0.8rem; padding:0.25rem 0.75rem;">Paid Member · VIP</span>
                <?php else: ?>
                    <span class="badge badge-demo" style="font-weight:600; font-size:0.8rem; padding:0.25rem 0.75rem;">Free Member</span>
                <?php endif; ?>
            </div>

            <?php if ($subscription): ?>
                <div style="background:var(--color-surface-alt, rgba(255,255,255,0.03)); border:1px solid var(--color-border); border-radius:var(--radius); padding:1rem; margin-bottom:1rem;">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:0.75rem; font-size:0.9rem;">
                        <div>
                            <span class="stat-label">Plan</span>
                            <strong style="display:block; margin-top:0.2rem;"><?= e($subscription['plan_name'] ?? 'Playbook Pass') ?></strong>
                        </div>
                        <div>
                            <span class="stat-label">Billing Interval</span>
                            <strong style="display:block; margin-top:0.2rem; text-transform:capitalize;"><?= e($subscription['billing_interval'] ?? 'month') ?></strong>
                        </div>
                        <div>
                            <span class="stat-label">Status</span>
                            <strong style="display:block; margin-top:0.2rem; text-transform:capitalize; color:<?= ($subscription['status'] ?? '') === 'active' ? 'var(--color-success, #10b981)' : 'var(--color-warning, #f59e0b)' ?>;">
                                <?= e($subscription['status'] ?? 'Active') ?>
                            </strong>
                        </div>
                        <div>
                            <span class="stat-label">Provider</span>
                            <strong style="display:block; margin-top:0.2rem;"><?= e($providerLabel) ?></strong>
                        </div>
                        <div>
                            <span class="stat-label"><?= ($subscription['status'] ?? '') === 'active' ? 'Next Renewal' : 'Expires At' ?></span>
                            <strong style="display:block; margin-top:0.2rem;"><?= e(format_date($subscription['renews_at'] ?? $subscription['ends_at'] ?? null)) ?></strong>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:0.75rem; align-items:center;">
                    <a class="btn btn-ghost btn-small" href="<?= e(url('/account/subscription')) ?>">Manage Subscription & Billing</a>
                </div>
            <?php else: ?>
                <div style="background:var(--color-surface-alt, rgba(255,255,255,0.03)); border:1px solid var(--color-border); border-radius:var(--radius); padding:1rem; margin-bottom:1rem;">
                    <p style="margin:0 0 0.5rem; font-size:0.95rem;">You are currently on the <strong>Free Baseline Tier</strong> with access to daily public research.</p>
                    <p class="fineprint" style="margin:0;">Upgrade to unlock full institutional playbooks, line shopping, model confidence scores, and private Discord VIP channels.</p>
                </div>
                <?php if ($featuredPlan && plan_has_checkout($featuredPlan)): ?>
                    <button
                        class="btn btn-primary"
                        type="button"
                        data-checkout
                        data-checkout-url="<?= e(plan_payment_url($featuredPlan)) ?>"
                        data-checkout-title="<?= e($featuredPlan['name'] ?? 'VIP Access') ?>"
                        data-checkout-price="<?= e(plan_price_label($featuredPlan)) ?>"
                        data-checkout-interval="<?= e($featuredPlan['billing_interval'] ?? 'month') ?>"
                        data-checkout-plan-id="<?= (int) ($featuredPlan['id'] ?? 0) ?>"
                    >Upgrade to VIP Playbook (<?= e(plan_price_label($featuredPlan)) ?>)</button>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= e(url('/pricing')) ?>">View VIP Membership Plans</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- In-Portal Discord Connection -->
        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                <h2 style="margin:0;">Discord Integration</h2>
                <?php if ($hasDiscord): ?>
                    <span class="badge badge-won" style="font-weight:600; font-size:0.8rem; padding:0.25rem 0.65rem;">Connected</span>
                <?php else: ?>
                    <span class="badge badge-scheduled" style="font-weight:600; font-size:0.8rem; padding:0.25rem 0.65rem;">Not Connected</span>
                <?php endif; ?>
            </div>

            <?php if ($hasDiscord): ?>
                <div style="display:flex; align-items:center; gap:0.85rem; padding:0.85rem; background:var(--color-surface-alt, rgba(255,255,255,0.03)); border:1px solid var(--color-border); border-radius:var(--radius); margin-bottom:0.5rem;">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="Discord Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid var(--color-primary);">
                    <?php else: ?>
                        <div style="width:42px; height:42px; border-radius:50%; background:var(--color-surface); display:flex; align-items:center; justify-content:center; color:var(--color-primary);">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M19.27 5.33A17.4 17.4 0 0 0 14.62 4c-.2.36-.43.85-.59 1.23a16.1 16.1 0 0 0-4.06 0A11.3 11.3 0 0 0 9.38 4 17.3 17.3 0 0 0 4.72 5.34C.96 10.96.05 16.44.33 21.87a17.5 17.4 0 0 0 5.28 2.67c.43-.58.81-1.2 1.14-1.84a11.4 11.4 0 0 1-1.8-.86c.15-.11.3-.22.44-.34a12.4 12.4 0 0 0 10.22 0c.15.12.3.23.44.34-.57.34-1.17.63-1.8.86.33.64.71 1.26 1.14 1.84a17.4 17.4 0 0 0 5.29-2.67c.33-6.3-.56-11.74-4.21-16.54ZM8.02 18.05c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.02 2.58-2.3 2.58Zm7.96 0c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.03 2.58-2.3 2.58Z"/></svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong style="display:block; font-size:0.95rem;">Discord Account Linked</strong>
                        <span style="font-size:0.8rem; color:var(--color-text-muted); font-family:var(--font-mono);">ID: <?= e($user['discord_id']) ?></span>
                    </div>
                </div>
                <p class="fineprint" style="margin:0.25rem 0 0;">Your Discord account is connected and synced with your Orion Bets desk access.</p>
            <?php else: ?>
                <p style="font-size:0.9rem; margin-bottom:0.75rem; color:var(--color-text-muted);">
                    Link your Discord account for instant role synchronization and access to community VIP rooms.
                </p>
                <div style="background:rgba(234, 179, 8, 0.08); border:1px solid rgba(234, 179, 8, 0.25); border-radius:var(--radius); padding:0.75rem 0.85rem; margin-bottom:1rem; font-size:0.82rem; color:var(--color-text);">
                    <strong>Email Verification Constraint:</strong> The email address on your Discord account must match your registered account email (<strong><?= e($user['email'] ?? '') ?></strong>) to connect.
                </div>
                <div>
                    <?= component('discord-button', [
                        'label' => 'Connect Discord',
                        'next' => '/account/settings',
                    ]) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Password Reset & Update -->
        <div class="panel">
            <h2>Change Password</h2>
            <form method="post" action="<?= e(url('/account/password')) ?>">
                <?= csrf_field() ?>
                
                <?= component('password-field', [
                    'name' => 'current_password',
                    'label' => 'Current password',
                    'required' => true,
                    'autocomplete' => 'current-password',
                ]) ?>

                <?= component('password-field', [
                    'name' => 'new_password',
                    'label' => 'New password',
                    'required' => true,
                    'minlength' => 10,
                    'autocomplete' => 'new-password',
                    'hint' => 'Minimum 10 characters.',
                ]) ?>

                <?= component('password-field', [
                    'name' => 'confirm_password',
                    'label' => 'Confirm new password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                ]) ?>

                <div style="margin-top:1.25rem;">
                    <button class="btn btn-primary" type="submit">Update Password</button>
                </div>
            </form>

            <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--color-border);">
                <p class="stat-label" style="margin-bottom:0.25rem;">Registered via Discord or forgot current password?</p>
                <p class="fineprint" style="margin-bottom:0.75rem;">You can receive a secure password setup link at your registered email address (<?= e($user['email'] ?? '') ?>) without entering your current password.</p>
                <form method="post" action="<?= e(url('/account/password/reset-request')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-small" type="submit">Send Password Setup Link to Email</button>
                </form>
            </div>
        </div>

        <!-- Deactivate Account (Danger Zone) -->
        <div class="panel" style="border-color:rgba(239, 68, 68, 0.25);">
            <h2 style="color:var(--color-danger, #ef4444);">Danger Zone</h2>
            <p class="fineprint" style="margin-bottom:1rem;">Deactivating your account will disable login sessions. Any active subscriptions should be canceled prior to deactivation.</p>
            <form method="post" action="<?= e(url('/account/delete')) ?>" data-confirm="Deactivate this account?" data-confirm-copy="Login will stop working. Published public desk notes stay intact." data-confirm-ok="Deactivate" data-confirm-tone="danger">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">Deactivate Account</button>
            </form>
        </div>

    </div>
</div>
