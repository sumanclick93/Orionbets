<?php
$intervals = ['month' => 'Monthly', 'year' => 'Yearly', 'season' => 'Season'];
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Catalog & Billing</p>
        <h2>Subscription Plans Management (<?= count($plans) ?>)</h2>
    </div>
    <div class="page-toolbar__actions">
        <a class="btn btn-ghost btn-small" href="<?= e($exportUrl ?? url('/admin/plans/export-csv')) ?>" download>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.35rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export Plans to CSV
        </a>
    </div>
</div>

<div class="dash-grid" style="grid-template-columns: 1fr; margin-bottom: 2rem;">
    <!-- Inline Plan Creation & Edit Form -->
    <section class="panel plan-editor" id="plan-form-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <p class="kicker" id="plan-form-kicker" style="margin:0 0 0.2rem;">Create or Edit</p>
                <h3 id="plan-form-title" style="margin:0;">Create New Plan</h3>
            </div>
            <button class="btn btn-ghost btn-small" type="button" id="plan-form-reset" onclick="resetPlanForm()" style="display:none;">
                + Switch to Create Mode
            </button>
        </div>
        <p class="lede" style="margin-top:0;font-size:0.95rem;">Configure the plan details and pricing interval. For paid plans, paste the Upgrade.Chat product checkout link to enable the instant modal checkout.</p>

        <form method="post" action="<?= e(url('/admin/plans/store')) ?>" id="plan-editor-form">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_id" id="field-plan-id" value="">

            <div class="form-row split">
                <div>
                    <label for="field-name">Plan Name</label>
                    <input id="field-name" name="name" placeholder="e.g. Month-to-Month All Access Pass" required value="<?= e((string) old('name')) ?>">
                </div>
                <div>
                    <label for="field-slug">URL Slug</label>
                    <input id="field-slug" name="slug" placeholder="e.g. monthly-pass" value="<?= e((string) old('slug')) ?>">
                </div>
            </div>

            <div class="form-row split">
                <div>
                    <label for="field-price">Price ($)</label>
                    <input id="field-price" name="price" type="number" min="0" step="0.01" placeholder="49.99" value="<?= e((string) old('price')) ?>">
                </div>
                <div>
                    <label for="field-currency">Currency</label>
                    <input id="field-currency" name="currency" value="<?= e((string) (old('currency') ?: 'USD')) ?>" maxlength="8">
                </div>
            </div>

            <div class="form-row split">
                <div>
                    <label for="field-interval">Billing Interval</label>
                    <select id="field-interval" name="billing_interval">
                        <?php foreach ($intervals as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) old('billing_interval', 'month') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="field-badge">Badge (Optional Highlight)</label>
                    <input id="field-badge" name="badge" placeholder="e.g. Most Popular / Founders Rate" value="<?= e((string) old('badge')) ?>">
                </div>
            </div>

            <div class="form-row split">
                <div>
                    <label for="field-sort">Sort Order</label>
                    <input id="field-sort" name="sort_order" type="number" value="<?= e((string) (old('sort_order') ?: '0')) ?>">
                </div>
                <div style="display:flex;gap:1.5rem;align-items:flex-end;padding-bottom:0.75rem;">
                    <label class="checkbox" style="margin:0;">
                        <input id="field-is-active" type="checkbox" name="is_active" value="1" <?= old('is_active', '1') ? 'checked' : '' ?>>
                        Active in catalog
                    </label>
                    <label class="checkbox" style="margin:0;">
                        <input id="field-is-featured" type="checkbox" name="is_featured" value="1" <?= old('is_featured') ? 'checked' : '' ?>>
                        Featured
                    </label>
                </div>
            </div>

            <label for="field-features">Features (one item per line)</label>
            <textarea id="field-features" name="features" rows="4" placeholder="Daily NFL & NBA best bets&#10;Access to the private Playbook desk&#10;Real-time Action Network tracking"><?= e((string) old('features')) ?></textarea>

            <label for="field-payment-url">Provider Checkout Link (Upgrade.Chat)</label>
            <input id="field-payment-url" name="payment_url" type="url" inputmode="url" placeholder="https://upgrade.chat/SERVER_ID/p/PRODUCT_ID" value="<?= e((string) old('payment_url')) ?>">
            <p class="field-hint">Paste the Upgrade.Chat product checkout link. On-site guest checkouts automatically link with Orion Bets accounts.</p>

            <div style="display:flex;gap:0.75rem;align-items:center;margin-top:1.25rem;">
                <button class="btn btn-primary" type="submit" id="plan-form-submit">Create Plan</button>
                <button class="btn btn-ghost" type="button" onclick="resetPlanForm()">Clear Form</button>
            </div>
        </form>
    </section>

    <!-- Interactive Plans DataTable -->
    <section class="panel" style="padding:1.2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <p class="kicker" style="margin:0 0 0.2rem;">All Plans</p>
                <h3 style="margin:0;">Catalog & Subscriptions</h3>
            </div>
        </div>

        <?php if (!$plans): ?>
            <?= component('empty-state', [
                'title' => 'No subscription plans created yet',
                'body' => 'Use the form above to add your first subscription plan.',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" data-interactive-table>
                    <thead>
                        <tr>
                            <th data-sort="number" style="width:4.5rem;">ID</th>
                            <th data-sort="text">Plan Name</th>
                            <th data-sort="number">Price ($)</th>
                            <th data-sort="text">Interval</th>
                            <th>Provider Checkout Link</th>
                            <th data-sort="number">Active Subscribers</th>
                            <th data-sort="text">Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                        $isActive = !empty($plan['is_active']);
                        $hasCheckout = plan_has_checkout($plan);
                        $featuresList = json_decode_array($plan['features'] ?? null);
                        ?>
                        <tr id="plan-row-<?= (int) $plan['id'] ?>">
                            <td data-label="ID" style="font-family:var(--font-mono);font-size:0.85rem;">
                                #<?= (int) $plan['id'] ?>
                            </td>
                            <td data-label="Plan Name">
                                <strong><?= e($plan['name']) ?></strong>
                                <?php if (!empty($plan['badge'])): ?>
                                    <span class="badge" style="font-size:0.7rem;margin-left:0.35rem;background:var(--color-surface-alt);"><?= e($plan['badge']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($plan['is_featured'])): ?>
                                    <span class="badge badge-won" style="font-size:0.7rem;margin-left:0.25rem;">Featured</span>
                                <?php endif; ?>
                                <div class="muted" style="font-size:0.78rem;">Slug: <code><?= e($plan['slug']) ?></code></div>
                            </td>
                            <td data-label="Price" style="font-family:var(--font-mono);font-weight:600;">
                                <?= money((int) ($plan['price_cents'] ?? 0), $plan['currency'] ?? 'USD') ?>
                            </td>
                            <td data-label="Interval" style="text-transform:capitalize;">
                                <?= e($plan['billing_interval'] ?? 'month') ?>
                            </td>
                            <td data-label="Provider Checkout Link">
                                <?php if ($hasCheckout): ?>
                                    <span class="badge badge-won">Upgrade.Chat Linked</span>
                                    <a href="<?= e(plan_payment_url($plan)) ?>" target="_blank" rel="noopener" class="fineprint" style="display:block;overflow:hidden;text-overflow:ellipsis;max-width:14rem;">
                                        <?= e(plan_payment_url($plan)) ?>
                                    </a>
                                <?php elseif ((int) ($plan['price_cents'] ?? 0) > 0): ?>
                                    <span class="badge badge-push">Needs payment link</span>
                                <?php else: ?>
                                    <span class="badge">Free Plan</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Active Subscribers" style="font-family:var(--font-mono);font-size:0.95rem;">
                                <strong><?= number_format((int) ($plan['active_subscribers_count'] ?? 0)) ?></strong>
                                <span class="muted" style="font-size:0.75rem;">(<?= (int) ($plan['total_subscribers_count'] ?? 0) ?> all-time)</span>
                            </td>
                            <td data-label="Status">
                                <span class="badge <?= $isActive ? 'badge-won' : 'badge-lost' ?>">
                                    <?= $isActive ? 'Active' : 'Archived' ?>
                                </span>
                            </td>
                            <td data-label="Actions" style="text-align:right;white-space:nowrap;">
                                <div style="display:inline-flex;gap:0.35rem;align-items:center;justify-content:flex-end;">
                                    <button
                                        class="btn btn-ghost btn-small"
                                        type="button"
                                        onclick="populatePlanEdit(<?= htmlspecialchars(json_encode($plan, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)"
                                    >
                                        Edit Inline
                                    </button>

                                    <form method="post" action="<?= e(url('/admin/plans/' . $plan['id'] . '/toggle-status')) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-small" type="submit" title="<?= $isActive ? 'Archive this plan' : 'Activate this plan' ?>">
                                            <?= $isActive ? 'Archive' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <?php if ($hasCheckout): ?>
                                        <button
                                            class="btn btn-ghost btn-small"
                                            type="button"
                                            data-checkout
                                            data-checkout-url="<?= e(plan_payment_url($plan)) ?>"
                                            data-checkout-title="<?= e($plan['name']) ?>"
                                            data-checkout-price="<?= e(plan_price_label($plan)) ?>"
                                            data-checkout-interval="<?= e($plan['billing_interval'] ?? 'month') ?>"
                                            data-checkout-plan-id="<?= (int) $plan['id'] ?>"
                                        >
                                            Preview
                                        </button>
                                    <?php endif; ?>

                                    <form method="post" action="<?= e(url('/admin/plans/' . $plan['id'] . '/delete')) ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete or archive plan #<?= (int) $plan['id'] ?>?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-small" type="submit" style="color:var(--color-danger);" title="Delete or Archive Plan">
                                            ×
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function populatePlanEdit(plan) {
    const form = document.getElementById('plan-editor-form');
    if (!form) return;

    form.action = '<?= url('/admin/plans') ?>/' + plan.id + '/update';
    document.getElementById('plan-form-title').textContent = 'Edit Plan #' + plan.id + ' (' + plan.name + ')';
    document.getElementById('plan-form-kicker').textContent = 'Editing Mode';
    document.getElementById('plan-form-submit').textContent = 'Update Plan #' + plan.id;
    document.getElementById('plan-form-reset').style.display = 'inline-block';

    document.getElementById('field-plan-id').value = plan.id;
    document.getElementById('field-name').value = plan.name || '';
    document.getElementById('field-slug').value = plan.slug || '';
    document.getElementById('field-price').value = plan.price_cents ? (plan.price_cents / 100).toFixed(2) : '0.00';
    document.getElementById('field-currency').value = plan.currency || 'USD';
    document.getElementById('field-interval').value = plan.billing_interval || 'month';
    document.getElementById('field-badge').value = plan.badge || '';
    document.getElementById('field-sort').value = plan.sort_order || 0;
    document.getElementById('field-payment-url').value = plan.payment_url || '';
    document.getElementById('field-is-active').checked = Boolean(Number(plan.is_active));
    document.getElementById('field-is-featured').checked = Boolean(Number(plan.is_featured));

    let features = [];
    try {
        if (typeof plan.features === 'string' && plan.features) {
            features = JSON.parse(plan.features);
        } else if (Array.isArray(plan.features)) {
            features = plan.features;
        }
    } catch (e) {}
    document.getElementById('field-features').value = Array.isArray(features) ? features.join('\n') : '';

    const panel = document.getElementById('plan-form-panel');
    if (panel) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        panel.style.outline = '2px solid var(--color-primary)';
        setTimeout(() => { panel.style.outline = ''; }, 1500);
    }
}

function resetPlanForm() {
    const form = document.getElementById('plan-editor-form');
    if (!form) return;

    form.reset();
    form.action = '<?= url('/admin/plans/store') ?>';
    document.getElementById('plan-form-title').textContent = 'Create New Plan';
    document.getElementById('plan-form-kicker').textContent = 'Create or Edit';
    document.getElementById('plan-form-submit').textContent = 'Create Plan';
    document.getElementById('plan-form-reset').style.display = 'none';
    document.getElementById('field-plan-id').value = '';
    document.getElementById('field-is-active').checked = true;
    document.getElementById('field-currency').value = 'USD';
    document.getElementById('field-sort').value = '0';
}
</script>
