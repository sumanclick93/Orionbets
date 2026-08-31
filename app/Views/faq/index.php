<section class="section">
    <div class="container">
        <p class="kicker">FAQ</p>
        <h1>Questions we actually answer</h1>
        <form method="get" class="filter-bar">
            <input data-faq-search type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search questions">
        </form>
        <?php foreach ($grouped as $category => $items): ?>
            <h2><?= e($category) ?></h2>
            <?php foreach ($items as $faq): ?>
                <article class="panel" data-faq-item style="margin-bottom:0.8rem;">
                    <h3><?= e($faq['question']) ?></h3>
                    <p><?= nl2br(e($faq['answer'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</section>
