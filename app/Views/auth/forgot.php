<h1>Reset password</h1>
<p>We’ll send a link if the email exists. In local development, check <code>storage/logs</code>.</p>
<form method="post" action="<?= e(url('/forgot-password')) ?>">
    <?= csrf_field() ?>
    <label>Email</label>
    <input type="email" name="email" required>
    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:1rem;">Send link</button>
</form>
