<h1>Email verification</h1>
<?php if (!empty($ok)): ?>
    <p>Your email is verified. Welcome to the desk.</p>
    <a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">Open dashboard</a>
<?php else: ?>
    <p>That verification link is invalid or expired.</p>
    <a class="btn btn-ghost" href="<?= e(url('/login')) ?>">Sign in</a>
<?php endif; ?>
