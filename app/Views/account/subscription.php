<?php
declare(strict_types=1);

$current = $current ?? null;
$plans = $plans ?? [];
$transactions = $transactions ?? [];
$isPremium = is_premium();

$providerLabel = match (strtolower((string) ($current['provider'] ?? ''))) {
    'paypal' => 'PayPal',
    'upgradechat', 'upgrade.chat' => 'Upgrade.Chat',
    'stripe' => 'Stripe',
    'demo' => 'Demo / Manual',
    default => !empty($current['provider']) ? ucfirst((string) $current['provider']) : 'Direct',
};
?>
<div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
        <h2 style="margin:0;">Active Membership & Subscription</h2>
        <?php if ($isPremium): ?>
            <span class="badge badge-accent" style="font-weight:600; font-size:0.82rem; padding:0.25rem 0.75rem;">Paid Member · VIP</span>
        <?php else: ?>
            <span class="badge badge-demo" style="font-weight:600; font-size:0.82rem; padding:0.25rem 0.75rem;">Free Member</span>
        <?php endif; ?>
    </div>

    <?php if ($current): ?>
        <div style="background:var(--color-surface-alt, rgba(255,255,255,0.03)); border:1px solid var(--color-border); border-radius:var(--radius); padding:1.25rem; margin-bottom:1.25rem;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:1rem; font-size:0.95rem;">
                <div>
                    <span class="stat-label">Plan Name</span>
                    <strong style="display:block; margin-top:0.25rem;"><?= e($current['plan_name'] ?? 'Playbook VIP') ?></strong>
                </div>
                <div>
                    <span class="stat-label">Billing Interval</span>
                    <strong style="display:block; margin-top:0.25rem; text-transform:capitalize;"><?= e($current['billing_interval'] ?? 'month') ?></strong>
                </div>
                <div>
                    <span class="stat-label">Status</span>
                    <strong style="display:block; margin-top:0.25rem; text-transform:capitalize; color:<?= ($current['status'] ?? '') === 'active' ? 'var(--color-success, #10b981)' : 'var(--color-warning, #f59e0b)' ?>;">
                        <?= e($current['status'] ?? 'Active') ?>
                    </strong>
                </div>
                <div>
                    <span class="stat-label">Payment Provider</span>
                    <strong style="display:block; margin-top:0.25rem;"><?= e($providerLabel) ?></strong>
                </div>
                <div>
                    <span class="stat-label">Started On</span>
                    <strong style="display:block; margin-top:0.25rem;"><?= e(format_date($current['starts_at'] ?? null)) ?></strong>
                </div>
                <div>
                    <span class="stat-label"><?= ($current['status'] ?? '') === 'active' ? 'Next Renewal' : 'Expires At' ?></span>
                    <strong style="display:block; margin-top:0.25rem;"><?= e(format_date($current['renews_at'] ?? $current['ends_at'] ?? null)) ?></strong>
                </div>
            </div>
        </div>

        <?php if (($current['status'] ?? '') === 'active'): ?>
            <form method="post" action="<?= e(url('/account/subscription')) ?>" data-confirm="Cancel this subscription?" data-confirm-copy="Your VIP playbook access will remain active until the end of your current billing period. You can resubscribe at any time." data-confirm-ok="Cancel plan" data-confirm-tone="danger">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <div style="max-width:400px; margin-bottom:0.75rem;">
                    <label for="cancel_reason">Reason for cancellation (optional)</label>
                    <input id="cancel_reason" name="cancel_reason" placeholder="Let us know how we can improve...">
                </div>
                <button class="btn btn-ghost btn-small" type="submit">Cancel Subscription</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <div style="background:var(--color-surface-alt, rgba(255,255,255,0.03)); border:1px solid var(--color-border); border-radius:var(--radius); padding:1.25rem; margin-bottom:1rem;">
            <p style="margin:0 0 0.5rem; font-size:1rem;">You are currently browsing on the <strong>Free Baseline Tier</strong>.</p>
            <p class="fineprint" style="margin:0;">Select a plan below to upgrade for full access to institutional playbooks, live closing lines, and private Discord VIP channels.</p>
        </div>
    <?php endif; ?>
</div>

<h2 style="margin-top:2rem; margin-bottom:1rem;">Available Plans & Upgrades</h2>
<div class="pricing-grid">
    <?php foreach ($plans as $plan): ?>
        <?= component('pricing-card', ['plan' => $plan, 'current' => $current && (int) $current['plan_id'] === (int) $plan['id'] && ($current['status'] ?? '') === 'active']) ?>
    <?php endforeach; ?>
</div>

<h2 style="margin-top:2rem; margin-bottom:1rem;">Billing & Payment History</h2>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Transaction ID</th>
                <th>Provider</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($transactions)): ?>
            <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td data-label="Date"><?= e(format_datetime($tx['created_at'])) ?></td>
                    <td data-label="Amount"><?= e(money((int) $tx['amount_cents'], $tx['currency'] ?? 'USD')) ?></td>
                    <td data-label="Status">
                        <span class="badge <?= ($tx['status'] ?? '') === 'completed' ? 'badge-won' : 'badge-scheduled' ?>">
                            <?= e($tx['status'] ?? 'completed') ?>
                        </span>
                    </td>
                    <td data-label="Transaction ID" style="font-family:var(--font-mono); font-size:0.85rem;"><?= e((string) ($tx['provider_transaction_id'] ?? '—')) ?></td>
                    <td data-label="Provider"><?= e(ucfirst((string) ($tx['provider'] ?? 'demo'))) ?></td>
                    <td data-label="Description"><?= e($tx['description'] ?? 'Subscription payment') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center; color:var(--color-text-muted); padding:2rem;">No transaction history found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
