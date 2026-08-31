<form method="post" class="panel">
    <?= csrf_field() ?>
    <h2>Add FAQ</h2>
    <input name="category" placeholder="Category" required>
    <input name="question" placeholder="Question" required>
    <textarea name="answer" required></textarea>
    <button class="btn btn-primary" type="submit">Add</button>
</form>
<?php foreach ($faqs as $faq): ?>
    <article class="panel" style="margin-top:0.8rem;">
        <h3><?= e($faq['question']) ?></h3>
        <p class="muted"><?= e($faq['category']) ?></p>
        <form method="post" action="<?= e(url('/admin/faqs/' . $faq['id'] . '/delete')) ?>" data-confirm="Delete this FAQ?" data-confirm-copy="This question will be removed from the public help page." data-confirm-ok="Delete" data-confirm-tone="danger">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-small" type="submit">Delete</button>
        </form>
    </article>
<?php endforeach; ?>
