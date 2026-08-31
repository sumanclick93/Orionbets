<?php
$success = flash('success');
$error = flash('error');
$bag = errors();
?>
<?php if ($success || $error): ?>
    <div class="alert <?= $error ? 'alert-danger' : 'alert-success' ?>" role="status">
        <?= e((string) ($success ?? $error)) ?>
    </div>
<?php endif; ?>
<?php if ($bag): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($bag as $messages): ?>
                <?php foreach ((array) $messages as $msg): ?>
                    <li><?= e($msg) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
