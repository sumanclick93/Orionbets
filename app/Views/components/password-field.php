<?php
$name = (string) ($name ?? 'password');
$label = (string) ($label ?? 'Password');
$required = !empty($required);
$minlength = isset($minlength) ? (int) $minlength : null;
$autocomplete = (string) ($autocomplete ?? 'current-password');
$errorKey = (string) ($errorKey ?? $name);
$hint = (string) ($hint ?? '');
$id = preg_replace('/[^a-z0-9_-]/i', '-', $name) ?: 'password';
?>
<label for="<?= e($id) ?>"><?= e($label) ?></label>
<div class="password-field">
    <input
        type="password"
        id="<?= e($id) ?>"
        name="<?= e($name) ?>"
        <?= $required ? 'required' : '' ?>
        <?= $minlength ? 'minlength="' . $minlength . '"' : '' ?>
        autocomplete="<?= e($autocomplete) ?>"
        spellcheck="false"
    >
    <button type="button" class="password-toggle" data-password-toggle aria-pressed="false" aria-label="Show <?= e($label) ?>">Show</button>
</div>
<?php if (error($errorKey)): ?>
    <p class="field-error"><?= e((string) error($errorKey)) ?></p>
<?php elseif ($hint !== ''): ?>
    <p class="field-hint"><?= e($hint) ?></p>
<?php endif; ?>
