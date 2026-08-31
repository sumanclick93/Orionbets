<?php foreach ($messages as $message): ?>
    <article class="panel" style="margin-bottom:1rem;">
        <h3><?= e($message['subject']) ?></h3>
        <p class="muted"><?= e($message['name'] . ' · ' . $message['email'] . ' · ' . $message['created_at']) ?></p>
        <p><?= nl2br(e($message['message'])) ?></p>
        <form method="post" action="<?= e(url('/admin/messages/' . $message['id'])) ?>">
            <?= csrf_field() ?>
            <select name="status">
                <?php foreach (['new','read','closed'] as $st): ?>
                    <option value="<?= $st ?>" <?= $message['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="admin_notes" placeholder="Internal notes"><?= e((string) $message['admin_notes']) ?></textarea>
            <button class="btn btn-primary" type="submit">Update</button>
        </form>
    </article>
<?php endforeach; ?>
