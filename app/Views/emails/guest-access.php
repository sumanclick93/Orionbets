<p>Hello <?= e($user['first_name'] ?? 'there') ?>,</p>
<p>Your guest payment is confirmed. Orion Bets stored this checkout against <strong><?= e($user['email'] ?? '') ?></strong>, including the transaction and browser cookie from this purchase.</p>
<p>Create a password with the same email to sign in and see your billing history. If you already started an account, sign in with that email.</p>
<p><a href="<?= e($url ?? url('/forgot-password')) ?>">Create a password</a></p>
<p>Or <a href="<?= e($register_url ?? url('/register')) ?>">finish registration</a> with the same email — your guest purchases stay on the account.</p>
