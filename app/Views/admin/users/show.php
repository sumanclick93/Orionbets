<?php
$user = is_array($user ?? null) ? $user : [];
$history = is_array($history ?? null) ? $history : [];
$isSelf = !empty($isSelf);
$canManage = !empty($canManage);
$deleted = !empty($user['deleted_at']);
$suspended = !$deleted && empty($user['is_active']);
$guest = !empty($user['is_guest']);
$registeredEmail = (string) ($history['registered_email'] ?? $user['email'] ?? '');
$paypalEmails = $history['paypal_emails'] ?? [];
$checkoutEmails = $history['checkout_emails'] ?? [];
$purchases = $history['purchases'] ?? [];
$subscriptions = $history['subscriptions'] ?? [];
$timeline = $history['timeline'] ?? [];
$fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
$id = (int) ($user['id'] ?? 0);
$paypalDiffers = false;
foreach ($paypalEmails as $paypalEmail) {
    if (strtolower((string) $paypalEmail) !== strtolower($registeredEmail)) {
        $paypalDiffers = true;
        break;
    }
}
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Member dossier</p>
        <h2><?= e($fullName !== '' ? $fullName : $registeredEmail) ?></h2>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('/admin/users')) ?>">All members</a>
</div>

<?php if ($deleted): ?>
    <div class="alert alert-danger">This account is soft-deleted. History stays in the database. They cannot sign in until you restore them.</div>
<?php elseif ($suspended): ?>
    <div class="alert alert-danger">This account is suspended. They cannot sign in until you reinstate them.</div>
<?php elseif ($guest): ?>
    <div class="alert">Guest checkout account. Same email can register or continue with Discord to claim this history.</div>
<?php endif; ?>

<div class="dash-grid cols-2 member-dossier">
    <section class="panel">
        <p class="kicker">Identity</p>
        <h3>Emails and account</h3>
        <dl class="member-meta">
            <div>
                <dt>Registered email</dt>
                <dd><?= e($registeredEmail !== '' ? $registeredEmail : '—') ?></dd>
            </div>
            <div>
                <dt>PayPal purchase email<?= $paypalDiffers ? 's' : '' ?></dt>
                <dd>
                    <?php if ($paypalEmails): ?>
                        <?php foreach ($paypalEmails as $paypalEmail): ?>
                            <span class="<?= strtolower((string) $paypalEmail) !== strtolower($registeredEmail) ? 'member-email-mismatch' : '' ?>"><?= e($paypalEmail) ?></span><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Checkout email</dt>
                <dd><?= $checkoutEmails ? e(implode(', ', $checkoutEmails)) : '—' ?></dd>
            </div>
            <div>
                <dt>Discord</dt>
                <dd><?= !empty($user['discord_id']) ? e((string) $user['discord_id']) : 'Not linked' ?></dd>
            </div>
            <div>
                <dt>Joined</dt>
                <dd><?= e(format_datetime($user['created_at'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Last login</dt>
                <dd><?= e(format_datetime($user['last_login_at'] ?? null)) ?><?php if (!empty($user['last_login_ip'])): ?> · <?= e((string) $user['last_login_ip']) ?><?php endif; ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd>
                    <?php if ($deleted): ?>
                        <span class="badge badge-lost">Deleted</span>
                    <?php elseif ($suspended): ?>
                        <span class="badge badge-push">Suspended</span>
                    <?php elseif ($guest): ?>
                        <span class="badge badge-demo">Guest</span>
                    <?php else: ?>
                        <span class="badge badge-won">Active</span>
                    <?php endif; ?>
                    <?php foreach ($user['roles'] ?? [] as $role): ?>
                        <span class="badge"><?= e($role) ?></span>
                    <?php endforeach; ?>
                </dd>
            </div>
        </dl>
        <?php if ($paypalDiffers): ?>
            <p class="muted admin-hint">PayPal wallet email does not match the registered / checkout email. Membership is tied to the registered email.</p>
        <?php endif; ?>
        <?php if (!empty($user['checkout_cookie'])): ?>
            <p class="muted admin-hint">Browser cookie: <?= e((string) $user['checkout_cookie']) ?></p>
        <?php endif; ?>
        <?php if ($deleted): ?>
            <p class="muted admin-hint">Deleted <?= e(format_datetime($user['deleted_at'] ?? null)) ?>. Row stays in <code>users</code>; email was freed for a new signup.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <p class="kicker">Access</p>
        <h3>Role and account actions</h3>
        <?php if ($deleted): ?>
            <p class="muted">Restore the account before changing roles.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/users/' . $id)) ?>">
                <?= csrf_field() ?>
                <label>Role</label>
                <select name="role" <?= $isSelf ? 'disabled' : '' ?>>
                    <?php foreach (['user','premium_user','editor','admin','super_admin'] as $role): ?>
                        <option value="<?= $role ?>" <?= in_array($role, $user['roles'] ?? [], true) ? 'selected' : '' ?>><?= e($role) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$isSelf): ?>
                    <button class="btn btn-primary" type="submit">Save role</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($isSelf): ?>
            <p class="muted admin-hint">You cannot suspend or delete the account you are signed in with.</p>
        <?php elseif (!$canManage): ?>
            <p class="muted admin-hint">Only a super admin can suspend or delete this account.</p>
        <?php else: ?>
            <div class="member-actions">
                <?php if ($deleted): ?>
                    <form method="post" action="<?= e(url('/admin/users/' . $id . '/restore')) ?>" data-confirm="Restore this account?" data-confirm-copy="They can sign in again with the original email, as long as nobody else has registered it." data-confirm-ok="Restore" data-confirm-tone="restore">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit">Restore account</button>
                    </form>
                <?php else: ?>
                    <?php if ($suspended): ?>
                        <form method="post" action="<?= e(url('/admin/users/' . $id . '/unsuspend')) ?>" data-confirm="Reinstate this account?" data-confirm-copy="They will be able to sign in again. Purchase history is unchanged." data-confirm-ok="Reinstate" data-confirm-tone="restore">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary" type="submit">Reinstate</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/admin/users/' . $id . '/suspend')) ?>" data-confirm="Suspend this account?" data-confirm-copy="They cannot sign in. History stays here. You can reinstate them later." data-confirm-ok="Suspend" data-confirm-tone="danger">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost" type="submit">Suspend</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/users/' . $id . '/delete')) ?>" data-confirm="Soft-delete this account?" data-confirm-copy="They cannot sign in. Purchases, subscriptions, and this timeline stay in the database. The email is freed so someone can register it again. You can restore later unless that email is taken." data-confirm-ok="Delete account" data-confirm-tone="danger" data-confirm-kicker="Soft delete">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger" type="submit">Delete account</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel" style="margin-top:1rem;">
    <p class="kicker">Billing</p>
    <h3>Subscriptions</h3>
    <?php if (!$subscriptions): ?>
        <p class="muted">No subscription rows.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Plan</th><th>Provider</th><th>Status</th><th>Started</th><th>Ends</th></tr></thead>
            <tbody>
            <?php foreach ($subscriptions as $sub): ?>
                <?php
                $subProvider = strtolower((string) ($sub['provider'] ?? ''));
                $subProviderLabel = match ($subProvider) {
                    'paypal' => 'PayPal',
                    'upgradechat', 'upgrade.chat', 'upgrade_chat' => 'Upgrade.Chat',
                    'demo' => 'Demo',
                    '' => '—',
                    default => ucfirst($subProvider),
                };
                ?>
                <tr>
                    <td data-label="Plan"><?= e((string) ($sub['plan_name'] ?? '')) ?></td>
                    <td data-label="Provider"><?= e($subProviderLabel) ?></td>
                    <td data-label="Status"><?= e((string) ($sub['status'] ?? '')) ?></td>
                    <td data-label="Started"><?= e(format_datetime($sub['starts_at'] ?? $sub['created_at'] ?? null)) ?></td>
                    <td data-label="Ends"><?= e(format_datetime($sub['ends_at'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h3 style="margin-top:1.4rem;">Purchases</h3>
    <?php if (!$purchases): ?>
        <p class="muted">No purchases recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Mode</th>
                    <th>Provider</th>
                    <th>Plan / amount</th>
                    <th>Registered / checkout email</th>
                    <th>PayPal email</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $purchase): ?>
                <?php
                $mode = (string) ($purchase['mode'] ?? 'member');
                $checkoutEmail = (string) ($purchase['checkout_email'] ?? '');
                $paypalEmail = (string) ($purchase['paypal_email'] ?? '');
                $mismatch = $paypalEmail !== '' && $checkoutEmail !== '' && strtolower($paypalEmail) !== strtolower($checkoutEmail);
                ?>
                <tr>
                    <td data-label="When"><?= e(format_datetime($purchase['at'] ?? null)) ?></td>
                    <td data-label="Mode">
                        <?php if ($mode === 'guest'): ?>
                            <span class="badge badge-demo">Guest</span>
                        <?php else: ?>
                            <span class="badge badge-won">Registered</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Provider"><?= e((string) ($purchase['provider'] ?? '')) ?></td>
                    <td data-label="Plan / amount">
                        <?= e((string) ($purchase['plan_name'] ?? $purchase['description'] ?? '')) ?>
                        <?php if ((int) ($purchase['amount_cents'] ?? 0) > 0): ?>
                            <br><?= e(money((int) $purchase['amount_cents'], (string) ($purchase['currency'] ?? 'USD'))) ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Registered / checkout email"><?= e($checkoutEmail !== '' ? $checkoutEmail : '—') ?></td>
                    <td data-label="PayPal email" class="<?= $mismatch ? 'member-email-mismatch' : '' ?>"><?= e($paypalEmail !== '' ? $paypalEmail : '—') ?></td>
                    <td data-label="Status"><?= e((string) ($purchase['status'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:1rem;">
    <p class="kicker">Activity</p>
    <h3>Timeline</h3>
    <?php if (!$timeline): ?>
        <p class="muted">No events yet.</p>
    <?php else: ?>
        <ol class="member-timeline">
            <?php foreach ($timeline as $event): ?>
                <li class="member-timeline__item is-<?= e((string) ($event['tone'] ?? 'account')) ?>">
                    <span class="member-timeline__dot" aria-hidden="true"></span>
                    <div>
                        <time datetime="<?= e((string) ($event['at'] ?? '')) ?>"><?= e(format_datetime($event['at'] ?? null)) ?></time>
                        <strong><?= e((string) ($event['title'] ?? '')) ?></strong>
                        <?php if (!empty($event['detail'])): ?>
                            <p><?= e((string) $event['detail']) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
