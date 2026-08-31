<section class="ob-pb is-in" data-slate>
    <div class="ob-pb__grid">
        <div class="ob-pb__left">
            <h1 class="ob-pb__h1">The<br>Playbook</h1>
            <p class="ob-pb__sub">Every call we make, sent before the games start.</p>
            <p class="ob-pb__scrawl">the game. the price. the stake.</p>
            <div class="ob-pb__cta">
                <a class="ob-btn" href="#plans">Get the Picks</a>
                <a class="ob-btn ob-btn--ghost" href="<?= e(url('/performance')) ?>">See the Record</a>
            </div>
        </div>
        <div class="ob-pb__right">
            <div class="ob-pb__slate" aria-label="Example daily slate">
                <div class="ob-pb__slatehead">
                    <span class="ob-pb__sport" data-sport>NFL</span>
                    <span class="ob-pb__tag">Example</span>
                </div>
                <div class="ob-pb__rows" data-rows>
                    <div class="ob-pb__row is-in">
                        <div><span class="ob-pb__play">HOME −2.5</span><span class="ob-pb__time">8:20 PM ET</span></div>
                        <div class="ob-pb__cell"><b>−110</b><span>Price</span></div>
                        <div class="ob-pb__cell"><b>1 UNIT</b><span>Stake</span></div>
                    </div>
                    <div class="ob-pb__row is-in">
                        <div><span class="ob-pb__play">UNDER 44.5</span><span class="ob-pb__time">1:00 PM ET</span></div>
                        <div class="ob-pb__cell"><b>−105</b><span>Price</span></div>
                        <div class="ob-pb__cell"><b>1 UNIT</b><span>Stake</span></div>
                    </div>
                    <div class="ob-pb__row is-in">
                        <div><span class="ob-pb__play">AWAY MONEYLINE</span><span class="ob-pb__time">4:25 PM ET</span></div>
                        <div class="ob-pb__cell"><b>+120</b><span>Price</span></div>
                        <div class="ob-pb__cell"><b>1 UNIT</b><span>Stake</span></div>
                    </div>
                </div>
                <div class="ob-pb__slatefoot">
                    <span data-cadence>Sent before kickoff</span>
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
