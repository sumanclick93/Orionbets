<h1>Choose a new password</h1>
<form method="post" action="<?= e(url('/reset-password')) ?>" data-password-match>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <?= component('password-field', [
        'name' => 'password',
        'label' => 'New password',
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
    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:1rem;">Update password</button>
</form>
