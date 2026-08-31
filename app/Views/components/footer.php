<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand brand-lockup" href="<?= e(url('/')) ?>"><?= component('logo') ?></a>
            <p class="footer-blurb"><?= e((string) (cms('footer_text') ?: settings('footer_text') ?: 'Daily picks. Public record. No excuses.')) ?></p>
        </div>
        <div>
            <h2>The desk</h2>
            <a href="<?= e(url('/')) ?>">Home</a>
            <a href="<?= e(url('/about')) ?>">About Us</a>
            <a href="<?= e(url('/affiliates')) ?>">Affiliates</a>
            <a href="<?= e(url('/the-playbook')) ?>">The Playbook</a>
            <a href="<?= e(url('/performance')) ?>">See the Record</a>
        </div>
        <div>
            <h2>Members</h2>
            <a href="<?= e(url('/picks')) ?>">Daily picks</a>
            <a href="<?= e(url('/faq')) ?>">FAQ</a>
            <a href="<?= e(url('/contact')) ?>">Contact</a>
            <?php if (settings('social_discord')): ?>
                <a href="<?= e(settings('social_discord')) ?>" rel="noopener">Discord</a>
            <?php endif; ?>
        </div>
        <div>
            <h2>Legal</h2>
            <a href="<?= e(url('/privacy')) ?>">Privacy</a>
            <a href="<?= e(url('/terms')) ?>">Terms</a>
            <a href="<?= e(url('/disclaimer')) ?>">Disclaimer</a>
            <a href="<?= e(url('/cookies')) ?>">Cookie Policy</a>
        </div>
    </div>
    <div class="container footer-legal">
        <p><?= e(cms('footer_disclaimer', '21+. Informational use only, not betting advice. 1-800-GAMBLER.')) ?></p>
        <p>&copy; <?= date('Y') ?> <?= e(site_name()) ?></p>
    </div>
</footer>
