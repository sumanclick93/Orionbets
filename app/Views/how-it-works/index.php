<section class="section">
    <div class="container">
        <p class="kicker">02 · Our process</p>
        <h1>What lands in your inbox.</h1>
        <p class="lede">The system runs overnight. In the morning our desk writes it up in plain English — the game, and the odds as we see them. It reaches you before kickoff.</p>
    </div>
</section>
<section class="section section-alt">
    <div class="container">
        <div class="hero-grid">
            <div>
                <h2>Overnight. Morning. Before kickoff.</h2>
                <div class="timeline">
                    <?php foreach (['Overnight model run', 'Morning desk write-up', 'Sent before kickoff', 'Action Network grades the result', 'Public record, win or lose'] as $i => $step): ?>
                        <div class="timeline-item">
                            <div>
                                <span></span>
                                <?php if ($i < 4): ?><div class="line"></div><?php endif; ?>
                            </div>
                            <p><strong><?= e($step) ?></strong></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="stage-grid" style="grid-template-columns:1fr;">
                <article class="stage-card"><h3>Overnight</h3><p>The system runs the rules across every game we cover while the rest of the world sleeps. No favorite teams. No bad nights to avenge.</p></article>
                <article class="stage-card"><h3>Morning</h3><p>Someone on the desk writes it up in plain English: the game, and the odds as we see them. If a call can't be explained in a sentence, it doesn't go out.</p></article>
                <article class="stage-card"><h3>Before kickoff</h3><p>Everything is sent before the games start, with a timestamp. When the games end, Action Network records the result. They keep the count, so we can't touch it.</p></article>
            </div>
        </div>
        <p style="margin-top:2rem;"><a class="ob-btn" href="<?= e(url('/the-playbook')) ?>">Get the Picks</a></p>
    </div>
</section>
