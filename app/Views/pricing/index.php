<section class="ob-pb is-in" data-slate>
    <div class="ob-pb__grid">
        <div class="ob-pb__left">
            <h1 class="ob-pb__h1"><?= nl2br(e(cms('playbook_hero_title', "The\nPlaybook"))) ?></h1>
            <p class="ob-pb__sub"><?= e(cms('playbook_hero_sub', 'Every call we make, sent before the games start.')) ?></p>
            <p class="ob-pb__scrawl"><?= e(cms('playbook_hero_scrawl', 'the game. the price. the stake.')) ?></p>
            <div class="ob-pb__cta">
                <a class="ob-btn" href="<?= e(url(cms('playbook_cta1_url', '#plans'))) ?>"><?= e(cms('playbook_cta1_text', 'Get the Picks')) ?></a>
                <a class="ob-btn ob-btn--ghost" href="<?= e(url(cms('playbook_cta2_url', '/performance'))) ?>"><?= e(cms('playbook_cta2_text', 'See the Record')) ?></a>
            </div>
        </div>
        <div class="ob-pb__right">
            <div class="ob-pb__slate" aria-label="Example daily slate">
                <div class="ob-pb__slatehead">
                    <span class="ob-pb__sport" data-sport><?= e(cms('playbook_slip_league_badge', 'NFL')) ?></span>
                    <span class="ob-pb__tag"><?= e(cms('playbook_slip_top_tag', 'EXAMPLE')) ?></span>
                </div>
                <div class="ob-pb__rows" data-rows>
                    <div class="ob-pb__row is-in">
                        <div><span class="ob-pb__play"><?= e(cms('playbook_slip_row1_title', 'HOME -2.5')) ?></span><span class="ob-pb__time"><?= e(cms('playbook_slip_row1_time', '8:20 PM ET')) ?></span></div>
                        <div class="ob-pb__cell"><b><?= e(cms('playbook_slip_row1_price', '-110')) ?></b><span>Price</span></div>
                        <div class="ob-pb__cell"><b><?= e(cms('playbook_slip_row1_stake', '1 UNIT')) ?></b><span>Stake</span></div>
                    </div>
                    <div class="ob-pb__row is-in">
                        <div><span class="ob-pb__play"><?= e(cms('playbook_slip_row2_title', 'UNDER 44.5')) ?></span><span class="ob-pb__time"><?= e(cms('playbook_slip_row2_time', '1:00 PM ET')) ?></span></div>
                        <div class="ob-pb__cell"><b><?= e(cms('playbook_slip_row2_price', '-105')) ?></b><span>Price</span></div>
                        <div class="ob-pb__cell"><b><?= e(cms('playbook_slip_row2_stake', '1 UNIT')) ?></b><span>Stake</span></div>
                    </div>
                    <div class="ob-pb__row is-in">
                        <div><span class="ob-pb__play"><?= e(cms('playbook_slip_row3_title', 'AWAY MONEYLINE')) ?></span><span class="ob-pb__time"><?= e(cms('playbook_slip_row3_time', '4:25 PM ET')) ?></span></div>
                        <div class="ob-pb__cell"><b><?= e(cms('playbook_slip_row3_price', '+120')) ?></b><span>Price</span></div>
                        <div class="ob-pb__cell"><b><?= e(cms('playbook_slip_row3_stake', '1 UNIT')) ?></b><span>Stake</span></div>
                    </div>
                </div>
                <div class="ob-pb__slatefoot">
                    <span data-cadence><?= e(cms('playbook_slip_footer_note', 'SENT BEFORE KICKOFF')) ?></span>
                    <span class="ob-pb__dots" data-dots><i class="is-on"></i><i></i><i></i><i></i></span>
                </div>
            </div>
            <p class="ob-pb__annot">the play, the price, the stake — every day</p>
        </div>
    </div>
    <div class="ob-pb__strip">
        <b>Published before kickoff · results counted by Action Network</b>
        <span>Illustrative example · 21+ · informational use only, not betting advice</span>
    </div>
</section>

<section class="section" id="plans">
    <div class="container">
        <p class="kicker">Lock the founders rate</p>
        <h2 class="ob-plans-title">Your price never moves.</h2>
        <p class="lede">The Playbook is a daily picks subscription. Every morning you get the play, the price, and the size — from a system with a public record. Informational use only. Not betting advice.</p>
        <div class="pricing-grid">
            <?php foreach ($plans as $plan): ?>
                <?php if ((int) ($plan['price_cents'] ?? 0) === 0) continue; ?>
                <?= component('pricing-card', ['plan' => $plan]) ?>
            <?php endforeach; ?>
        </div>
        <p class="fineprint" style="margin-top:1.5rem;">21+. Informational use only, not betting advice. <a href="tel:18004262537">1-800-GAMBLER</a>.</p>
    </div>
</section>
