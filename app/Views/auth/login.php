<h1>Sign in</h1>
<p class="muted">Member desk access. Informational product only. If you paid as a guest, use the same email — register or reset a password to see billing history.</p>
<?= component('discord-button', ['label' => 'Continue with Discord', 'next' => '/dashboard']) ?>
<p class="auth-divider"><span>or sign in with email</span></p>
<form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>" autocomplete="username">
    <?= component('password-field', [
        'name' => 'password',
        'label' => 'Password',
        'required' => true,
        'autocomplete' => 'current-password',
    ]) ?>
    <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember this browser</label>
    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:1rem;">Sign in</button>
</form>
<p><a href="<?= e(url('/forgot-password')) ?>">Forgot password</a></p>
<p>New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
