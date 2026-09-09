<?php
declare(strict_types=1);

$user = $user ?? auth()->user() ?? [];
$roles = $user['roles'] ?? [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$roleLabel = $isSuperAdmin ? 'Super Admin' : (in_array('admin', $roles, true) ? 'Admin' : 'Administrator');
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Operations</p>
        <h2>Profile & Security Settings</h2>
    </div>
</div>

<div class="dash-grid cols-2" style="margin-top:1rem;">
    <!-- Card 1: Admin Account Details (Email Update) -->
    <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            <h2 style="margin:0;">Account Information</h2>
            <span class="badge badge-accent" style="font-weight:600; font-size:0.8rem; padding:0.25rem 0.75rem;">
                <?= e($roleLabel) ?>
            </span>
        </div>

        <form method="post" action="<?= e(url('/admin/profile/update-profile')) ?>" class="flow-form">
            <?= csrf_field() ?>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom:1.25rem;">
                <div class="field-group">
                    <label for="first_name">First Name</label>
                    <input id="first_name" type="text" name="first_name" value="<?= e($user['first_name'] ?? '') ?>" required autocomplete="given-name" maxlength="80">
                    <?php if (error('first_name')): ?>
                        <p class="field-error"><?= e((string) error('first_name')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="last_name">Last Name</label>
                    <input id="last_name" type="text" name="last_name" value="<?= e($user['last_name'] ?? '') ?>" autocomplete="family-name" maxlength="80">
                    <?php if (error('last_name')): ?>
                        <p class="field-error"><?= e((string) error('last_name')) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field-group" style="margin-bottom:1.25rem;">
                <label for="email">Admin Email Address</label>
                <input id="email" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required autocomplete="email">
                <p class="field-hint">Use this email address to log in to the admin portal.</p>
                <?php if (error('email')): ?>
                    <p class="field-error"><?= e((string) error('email')) ?></p>
                <?php endif; ?>
            </div>

            <div style="margin-top:1.5rem;">
                <button class="btn btn-primary" type="submit">Update Account Details</button>
            </div>
        </form>
    </div>

    <!-- Card 2: Security & Change Password -->
    <div class="panel">
        <h2 style="margin-bottom:1rem;">Change Password</h2>

        <form method="post" action="<?= e(url('/admin/profile/update-password')) ?>" class="flow-form">
            <?= csrf_field() ?>

            <div style="margin-bottom:1rem;">
                <?= component('password-field', [
                    'name' => 'current_password',
                    'label' => 'Current Password',
                    'required' => true,
                    'autocomplete' => 'current-password',
                ]) ?>
            </div>

            <div style="margin-bottom:1rem;">
                <?= component('password-field', [
                    'name' => 'new_password',
                    'label' => 'New Password',
                    'required' => true,
                    'minlength' => 8,
                    'autocomplete' => 'new-password',
                    'hint' => 'Minimum 8 characters.',
                ]) ?>
            </div>

            <div style="margin-bottom:1rem;">
                <?= component('password-field', [
                    'name' => 'confirm_password',
                    'label' => 'Confirm New Password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                ]) ?>
            </div>

            <div style="margin-top:1.5rem;">
                <button class="btn btn-primary" type="submit">Update Password</button>
            </div>
        </form>
    </div>
</div>
