<?php
$intervals = ['month' => 'Monthly', 'year' => 'Yearly', 'season' => 'Season'];
?>
<section class="panel plan-editor">
    <p class="kicker">Upgrade.Chat</p>
    <h2>New plan</h2>
    <p class="lede" style="margin-top:0;">Create the plan people see on the site, then paste the Upgrade.Chat product payment link. Guest details stay on Orion Bets; PayPal and cards open in the Upgrade.Chat window. Upgrade.Chat still records the order and sends webhooks here.</p>
    <form method="post" action="<?= e(url('/admin/subscriptions/plans')) ?>">
        <?= csrf_field() ?>
        <label>Name</label>
        <input name="name" placeholder="The Month-to-Month Pass" required value="<?= e((string) old('name')) ?>">
        <div class="form-row split">
            <div>
                <label>Slug</label>
                <input name="slug" placeholder="premium" value="<?= e((string) old('slug')) ?>">
            </div>
            <div>
                <label>Badge</label>
                <input name="badge" placeholder="Founders rate" value="<?= e((string) old('badge')) ?>">
            </div>
        </div>
        <label>Description</label>
        <textarea name="description" placeholder="What this pass includes."><?= e((string) old('description')) ?></textarea>
        <div class="form-row split">
            <div>
                <label>Price</label>
                <input name="price" type="number" min="0" step="0.01" placeholder="49.99" value="<?= e((string) old('price')) ?>">
            </div>
            <div>
                <label>Currency</label>
                <input name="currency" value="<?= e((string) (old('currency') ?: 'USD')) ?>" maxlength="8">
            </div>
        </div>
        <div class="form-row split">
            <div>
                <label>Billing interval</label>
                <select name="billing_interval">
                    <?php foreach ($intervals as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= (string) old('billing_interval', 'month') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Sort order</label>
                <input name="sort_order" type="number" value="<?= e((string) (old('sort_order') ?: '0')) ?>">
            </div>
        </div>
        <label>Features (one per line)</label>
        <textarea name="features" placeholder="Daily picks — the play, the price, the size"><?= e((string) old('features')) ?></textarea>
        <label>Upgrade.Chat payment link</label>
        <input name="payment_url" type="url" inputmode="url" placeholder="https://upgrade.chat/STORE_ID/p/PRODUCT_ID" value="<?= e((string) old('payment_url')) ?>">
        <p class="field-hint">Copy the product checkout URL from Upgrade.Chat. Guest checkout stays on Orion Bets; PayPal and cards open in the Upgrade.Chat window (no Discord). Turn on Guest Checkout at <a href="https://upgrade.chat/settings" target="_blank" rel="noopener">upgrade.chat/settings</a>, then paste the webhook URL from this page into <a href="https://upgrade.chat/developers/webhooks" target="_blank" rel="noopener">upgrade.chat/developers/webhooks</a>.</p>
        <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= old('is_featured') ? 'checked' : '' ?>> Featured on pricing</label>
        <button class="btn btn-primary" type="submit">Create plan</button>
    </form>
</section>

<?php foreach ($plans as $plan): ?>
<form method="post" action="<?= e(url('/admin/subscriptions/plans/' . $plan['id'])) ?>" class="panel plan-editor" style="margin-top:1rem;">
    <?= csrf_field() ?>
    <div class="plan-editor__head">
        <h3><?= e($plan['name']) ?></h3>
        <?php if (plan_has_checkout($plan)): ?>
            <span class="badge badge-won">Upgrade.Chat linked</span>
        <?php elseif ((int) $plan['price_cents'] > 0): ?>
            <span class="badge badge-push">Needs payment link</span>
        <?php else: ?>
            <span class="badge">Free</span>
        <?php endif; ?>
    </div>
    <label>Name</label>
    <input name="name" value="<?= e($plan['name']) ?>" required>
    <label>Description</label>
    <textarea name="description"><?= e((string) $plan['description']) ?></textarea>
    <div class="form-row split">
        <div>
            <label>Price</label>
            <input name="price" type="number" min="0" step="0.01" value="<?= e((string) ((int) $plan['price_cents'] / 100)) ?>">
        </div>
        <div>
            <label>Currency</label>
            <input name="currency" value="<?= e($plan['currency'] ?? 'USD') ?>" maxlength="8">
        </div>
    </div>
    <div class="form-row split">
        <div>
            <label>Billing interval</label>
            <select name="billing_interval">
                <?php foreach ($intervals as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($plan['billing_interval'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Sort order</label>
            <input name="sort_order" type="number" value="<?= e((string) ($plan['sort_order'] ?? 0)) ?>">
        </div>
    </div>
    <label>Features (one per line)</label>
    <textarea name="features"><?= e(implode("\n", json_decode_array($plan['features']))) ?></textarea>
    <label>Badge</label>
    <input name="badge" value="<?= e((string) $plan['badge']) ?>">
    <label>Upgrade.Chat payment link</label>
    <input name="payment_url" type="url" inputmode="url" placeholder="https://upgrade.chat/STORE_ID/p/PRODUCT_ID" value="<?= e(plan_payment_url($plan)) ?>">
    <p class="field-hint">This is the Upgrade.Chat checkout URL. Customers pay as guests (no Discord); PayPal opens in the Upgrade.Chat window. Turn on Guest Checkout at <a href="https://upgrade.chat/settings" target="_blank" rel="noopener">upgrade.chat/settings</a>.</p>
    <div class="form-row split">
        <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= $plan['is_active'] ? 'checked' : '' ?>> Active</label>
        <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= $plan['is_featured'] ? 'checked' : '' ?>> Featured</label>
    </div>
    <div class="plan-editor__actions">
        <button class="btn btn-primary" type="submit">Save plan</button>
        <?php if (plan_has_checkout($plan)): ?>
            <button
                class="btn btn-ghost"
                type="button"
                data-checkout
                data-checkout-url="<?= e(plan_payment_url($plan)) ?>"
                data-checkout-title="<?= e($plan['name']) ?>"
                data-checkout-price="<?= e(plan_price_label($plan)) ?>"
                data-checkout-interval="<?= e($plan['billing_interval'] ?? 'month') ?>"
                data-checkout-plan-id="<?= (int) $plan['id'] ?>"
            >Preview checkout</button>
        <?php endif; ?>
    </div>
</form>
<?php endforeach; ?>

<h2>Subscriptions</h2>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>Member</th><th>Plan</th><th>Status</th><th>Provider</th><th>Order</th></tr></thead>
    <tbody>
    <?php foreach ($subscriptions as $sub): ?>
        <tr>
            <td data-label="Member"><?= e($sub['email']) ?></td>
            <td data-label="Plan"><?= e($sub['plan_name']) ?></td>
            <td data-label="Status"><?= e($sub['status']) ?></td>
            <td data-label="Provider"><?= e($sub['provider'] ?? '') ?></td>
            <td data-label="Order"><?= e((string) ($sub['provider_subscription_id'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<section class="panel" style="margin-top:1.5rem;">
    <p class="kicker">Webhooks</p>
    <h2>Upgrade.Chat endpoint</h2>
    <p class="lede" style="margin-top:0;">Paste this URL in <a href="https://upgrade.chat/developers/webhooks" target="_blank" rel="noopener">upgrade.chat/developers/webhooks</a> for <code>order.created</code>, <code>order.completed</code>, <code>order.updated</code>, <code>subscription.created</code>, and <code>subscription.renewed</code>. Guest checkouts create an Orion Bets account with the same email so later login or register shows billing history.</p>
    <label>Webhook URL</label>
    <input type="text" readonly value="<?= e($webhookUrl ?? url('/webhooks/upgrade-chat')) ?>" onclick="this.select()">
    <label>Alias</label>
    <input type="text" readonly value="<?= e($webhookAlias ?? url('/api/webhook-upgradechat.php')) ?>" onclick="this.select()">
    <p class="field-hint">Optional: set <code>UPGRADECHAT_CLIENT_ID</code> and <code>UPGRADECHAT_CLIENT_SECRET</code> in <code>.env</code> to validate events. If you set <code>UPGRADECHAT_WEBHOOK_SECRET</code>, send it as header <code>X-Upgrade-Chat-Secret</code> or HMAC <code>X-Upgrade-Chat-Signature</code>.</p>
</section>

<section class="panel" style="margin-top:1.5rem;">
    <p class="kicker">Everflow</p>
    <h2>Affiliate tracking</h2>
    <?php $ef = $everflow ?? everflow_config(); ?>
    <p class="lede" style="margin-top:0;"><?= !empty($ef['enabled']) ? 'Clicks persist 90 days. Funnel postbacks: signup (CPL), checkout started, sale, and rebill. Sale still fires from PayPal capture and Upgrade.Chat webhooks.' : 'Set <code>EVERFLOW_TRACKING_DOMAIN</code> (and optionally <code>EVERFLOW_OFFER_ID</code>, <code>EVERFLOW_NID</code>) in <code>.env</code> so landing pages load the Everflow SDK and S2S postbacks can fire.' ?></p>
    <p class="field-hint">Tracking host: <?= e($ef['host'] !== '' ? $ef['host'] : 'not set') ?> · Offer <?= e($ef['offer_id'] !== '' ? $ef['offer_id'] : '—') ?> · NID <?= e($ef['nid'] !== '' ? $ef['nid'] : '—') ?> · Lead <?= e(($ef['lead_event_id'] ?? '') !== '' ? $ef['lead_event_id'] : 'off') ?> · Checkout <?= e(($ef['checkout_event_id'] ?? '') !== '' ? $ef['checkout_event_id'] : 'off') ?></p>
    <p><a class="btn btn-ghost btn-small" href="<?= e(url('/admin/everflow')) ?>">Open Everflow log</a></p>
</section>

<h2>On-site checkouts</h2>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>When</th><th>Email</th><th>Plan</th><th>Status</th><th>Transaction</th><th>Everflow</th><th>Cookie</th></tr></thead>
    <tbody>
    <?php foreach (($checkouts ?? []) as $row): ?>
        <tr>
            <td data-label="When"><?= e($row['created_at']) ?></td>
            <td data-label="Email"><?= e($row['email']) ?></td>
            <td data-label="Plan"><?= e((string) ($row['plan_name'] ?? '')) ?></td>
            <td data-label="Status"><?= e($row['status']) ?></td>
            <td data-label="Transaction"><?= e((string) ($row['provider_transaction_id'] ?? $row['provider_order_id'] ?? '')) ?></td>
            <td data-label="Everflow"><?= e((string) ($row['everflow_transaction_id'] ?? '')) ?></td>
            <td data-label="Cookie"><?= e(substr((string) ($row['browser_cookie'] ?? ''), 0, 12)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2>Everflow postbacks</h2>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>When</th><th>Kind</th><th>Order</th><th>Amount</th><th>HTTP</th><th>Event</th></tr></thead>
    <tbody>
    <?php foreach (($postbacks ?? []) as $row): ?>
        <tr>
            <td data-label="When"><?= e($row['created_at']) ?></td>
            <td data-label="Kind"><?= e((string) ($row['kind'] ?? '')) ?></td>
            <td data-label="Order"><?= e((string) ($row['order_id'] ?? '')) ?></td>
            <td data-label="Amount"><?= e(($row['currency'] ?? 'USD') . ' ' . (string) ($row['amount'] ?? '')) ?></td>
            <td data-label="HTTP"><?= e((string) ($row['http_status'] ?? '')) ?></td>
            <td data-label="Event"><?= e((string) ($row['event_type'] ?? '')) ?><?php if (!empty($row['everflow_transaction_id'])): ?> · <?= e(substr((string) $row['everflow_transaction_id'], 0, 12)) ?><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2>Webhook calls</h2>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>When</th><th>Type</th><th>Status</th><th>Event</th></tr></thead>
    <tbody>
    <?php foreach (($webhooks ?? []) as $hook): ?>
        <tr>
            <td data-label="When"><?= e($hook['created_at']) ?></td>
            <td data-label="Type"><?= e((string) ($hook['event_type'] ?? '')) ?></td>
            <td data-label="Status"><?= e($hook['status']) ?></td>
            <td data-label="Event"><?= e((string) ($hook['event_id'] ?? '')) ?><?php if (!empty($hook['error'])): ?> · <?= e($hook['error']) ?><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
