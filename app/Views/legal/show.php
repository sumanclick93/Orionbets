<section class="section">
    <div class="container" style="max-width:760px;margin-inline:auto;">
        <p class="kicker">Legal</p>
        <h1><?= e($page['title'] ?? 'Legal') ?></h1>
        <div class="panel">
            <p><?= nl2br(e($page['content'] ?? '')) ?></p>
        </div>
    </div>
</section>
