<?php
$emailLocked = !empty($emailLocked) && ($prefillEmail ?? '') !== '';
$emailValue = (string) old('email', $prefillEmail ?? '');
?>
<h1>Create account</h1>
<p class="muted">21+ only. Informational use only, not betting advice. If you already paid as a guest, use the same email — your purchases stay on this account.</p>
<?php if ($emailLocked): ?>
<p class="muted">Guest payment is on <strong><?= e((string) $prefillEmail) ?></strong>. Set a password to claim it. This email cannot be changed.</p>
<?php endif; ?>
<?= component('discord-button', ['label' => 'Continue with Discord', 'next' => '/dashboard']) ?>
<p class="auth-divider"><span>or register with email</span></p>
<form method="post" action="<?= e($emailLocked ? url('/register?' . http_build_query(['email' => (string) $prefillEmail, 'next' => '/dashboard'])) : url('/register')) ?>" data-password-match>
    <?= csrf_field() ?>
    <div class="form-row split">
        <div>
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" required value="<?= e((string) old('first_name')) ?>" autocomplete="given-name">
            <?php if (error('first_name')): ?><p class="field-error"><?= e((string) error('first_name')) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" required value="<?= e((string) old('last_name')) ?>" autocomplete="family-name">
            <?php if (error('last_name')): ?><p class="field-error"><?= e((string) error('last_name')) ?></p><?php endif; ?>
        </div>
    </div>
    <label for="email">Email</label>
    <input
        type="email"
        id="email"
        name="email"
        required
        value="<?= e($emailValue) ?>"
        autocomplete="email"
        <?= $emailLocked ? 'readonly' : '' ?>
        class="<?= $emailLocked ? 'is-locked' : '' ?>"
    >
    <?php if (error('email')): ?><p class="field-error"><?= e((string) error('email')) ?></p><?php endif; ?>
    <?= component('password-field', [
        'name' => 'password',
        'label' => 'Password',
        'required' => true,
        'minlength' => 10,
        'autocomplete' => 'new-password',
        'hint' => 'At least 10 characters.',
    ]) ?>
    <?= component('password-field', [
        'name' => 'password_confirmation',
        'label' => 'Confirm password',
        'required' => true,
        'minlength' => 10,
        'autocomplete' => 'new-password',
    ]) ?>
    <p class="field-error" data-password-match-error hidden>Password and confirm password must match.</p>
    <label class="checkbox"><input type="checkbox" name="age" value="1" required <?= old('age') ? 'checked' : '' ?>> I confirm I am 21 or older</label>
    <?php if (error('age')): ?><p class="field-error"><?= e((string) error('age')) ?></p><?php endif; ?>
    <label class="checkbox"><input type="checkbox" name="terms" value="1" required <?= old('terms') ? 'checked' : '' ?>> I accept the <a href="<?= e(url('/terms')) ?>">Terms</a></label>
    <?php if (error('terms')): ?><p class="field-error"><?= e((string) error('terms')) ?></p><?php endif; ?>
    <label class="checkbox"><input type="checkbox" name="privacy" value="1" required <?= old('privacy') ? 'checked' : '' ?>> I accept the <a href="<?= e(url('/privacy')) ?>">Privacy Policy</a></label>
    <?php if (error('privacy')): ?><p class="field-error"><?= e((string) error('privacy')) ?></p><?php endif; ?>
    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:1rem;">Create account</button>
</form>
<p>Already registered? <a href="<?= e(url('/login')) ?>">Sign in</a></p>
