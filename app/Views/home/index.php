<?php
$heroHeadline = cms('hero_headline', 'Our best bets, sent before kickoff.');
$heroLede = cms('hero_subheadline', 'Daily picks. Public record. No excuses. The Playbook is a daily picks subscription. Every morning you get our best bets — the play, the price, the size — from a system trained on the success of the best bettors in the game. Every result gets posted publicly, win or lose.');
$heroCtaText = cms('hero_cta_text', 'Get the picks');
$heroCtaUrl = cms('hero_cta_url', '/the-playbook');
$heroBannerUrl = cms('hero_banner_url', '');

$story1Eyebrow = cms('story_1_eyebrow', '01 · Who we are');
$story1Title = cms('story_1_title', "Built from one bettor's edge.");
$story1Body = cms('story_1_body', "Before he ever bet against the board, our founder priced it — setting lines in Las Vegas and watching the public tell him where he was wrong.\n\nHe spent years turning that instinct into rules. We built a system to run those rules across every game, every night.");
$story1Scrawl = cms('story_1_scrawl', 'no favorite team. no bad nights.');

$story2Eyebrow = cms('story_2_eyebrow', '02 · Our process');
$story2Title = cms('story_2_title', 'What lands in your inbox.');
$story2Body = cms('story_2_body', "The system runs overnight. In the morning our desk writes it up in plain English — the game, and the odds as we see them.\n\nIt reaches you before kickoff. When the games end, Action Network records the result. They keep the count, so you can trust our system.");

$story3Eyebrow = cms('story_3_eyebrow', '03 · Our value');
$story3Title = cms('story_3_title', 'Six points is the whole game.');
$story3Body = cms('story_3_body', "To come out even you have to win about 52 of every 100. That's the wall, and most people never hear about it.");

$countdownAt = cms('kickoff_countdown_at', $countdownAt ?? '2026-09-09T20:20:00-04:00');
$kickoffKicker = cms('kickoff_kicker', 'ORIONBETS · THE PLAYBOOK · NFL');
$kickoffTitlePre = cms('kickoff_title_pre', 'Season One kicks off in');
$kickoffTitleLive = cms('kickoff_title_live', 'Season One is live.');
$kickoffSubPre = cms('kickoff_sub_pre', 'Lock the founders rate before the first whistle and your price never moves.');
$kickoffSubLive = cms('kickoff_sub_live', "Today's slate is out. Every pick posted before kickoff.");
$kickoffCtaPre = cms('kickoff_cta_pre', 'Claim the Founders Rate');
$kickoffCtaLive = cms('kickoff_cta_live', "Get Today's Picks");
?>
<section class="ob-hero">
    <div class="ob-hero__media" aria-hidden="true" <?= $heroBannerUrl !== '' ? 'style="background-image:url(' . e($heroBannerUrl) . ');background-size:cover;background-position:center;"' : '' ?>></div>
    <div class="container ob-hero__copy">
        <h1><?= e($heroHeadline) ?></h1>
        <p class="ob-hero__lede"><?= nl2br(e($heroLede)) ?></p>
        <a class="ob-btn" href="<?= e(url($heroCtaUrl)) ?>"><?= e($heroCtaText) ?></a>
    </div>
</section>

<section class="ob-tri is-in" id="our-system">
    <div class="ob-tri__route" aria-hidden="true">
        <svg viewBox="0 0 1200 60" preserveAspectRatio="none">
            <path class="ob-tri__routeline" d="M0,44 C220,44 260,20 400,20 C560,20 600,44 760,44 C900,44 960,14 1200,14" fill="none" stroke="#EAE6DC" stroke-width="3"></path>
        </svg>
    </div>
    <div class="ob-tri__grid">
        <article class="ob-tri__col">
            <svg class="ob-tri__icon" viewBox="0 0 54 54" aria-hidden="true">
                <circle cx="27" cy="27" r="14" fill="none" stroke="#EAE6DC" stroke-width="2.4" stroke-dasharray="70 10"></circle>
                <g stroke="#EAE6DC" stroke-width="2.4" stroke-linecap="round">
                    <line x1="27" y1="5" x2="27" y2="13"></line><line x1="27" y1="41" x2="27" y2="49"></line>
                    <line x1="5" y1="27" x2="13" y2="27"></line><line x1="41" y1="27" x2="49" y2="27"></line>
                </g>
                <circle cx="27" cy="27" r="2.8" fill="#EAE6DC"></circle>
            </svg>
            <span class="ob-tri__eyebrow"><?= e($story1Eyebrow) ?></span>
            <h2 class="ob-tri__h"><?= e($story1Title) ?></h2>
            <?php foreach (array_filter(explode("\n\n", $story1Body)) as $p): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
            <?php endforeach; ?>
            <?php if ($story1Scrawl !== ''): ?>
                <p class="ob-tri__scrawl"><?= e($story1Scrawl) ?></p>
            <?php endif; ?>
        </article>
        <article class="ob-tri__col">
            <svg class="ob-tri__icon" viewBox="0 0 54 54" aria-hidden="true">
                <path d="M 8 44 C 22 40, 30 30, 44 12" fill="none" stroke="#EAE6DC" stroke-width="2.6" stroke-linecap="round" stroke-dasharray="9 7"></path>
                <path d="M 36 10 L 46 11 L 45 21" fill="none" stroke="#EAE6DC" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span class="ob-tri__eyebrow"><?= e($story2Eyebrow) ?></span>
            <h2 class="ob-tri__h"><?= e($story2Title) ?></h2>
            <?php foreach (array_filter(explode("\n\n", $story2Body)) as $p): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
            <?php endforeach; ?>
            <div class="ob-tri__chips">
                <span>Overnight</span><span>Morning</span><span>Before kickoff</span>
            </div>
        </article>
        <article class="ob-tri__col">
            <svg class="ob-tri__icon" viewBox="0 0 54 54" aria-hidden="true">
                <g stroke="#EAE6DC" stroke-width="2.6" stroke-linecap="round">
                    <line x1="14" y1="12" x2="14" y2="40"></line><line x1="22" y1="12" x2="22" y2="40"></line>
                    <line x1="30" y1="12" x2="30" y2="40"></line><line x1="38" y1="12" x2="38" y2="40"></line>
                    <line x1="8" y1="38" x2="46" y2="16"></line>
                </g>
            </svg>
            <span class="ob-tri__eyebrow"><?= e($story3Eyebrow) ?></span>
            <h2 class="ob-tri__h"><?= e($story3Title) ?></h2>
            <?php foreach (array_filter(explode("\n\n", $story3Body)) as $p): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
            <?php endforeach; ?>
            <div class="ob-tri__stats">
                <div class="ob-tri__stat"><b data-count="52">52</b><span>to break even</span></div>
                <div class="ob-tri__vs">vs</div>
                <div class="ob-tri__stat ob-tri__stat--win"><b data-count="59">59</b><span>our record</span></div>
            </div>
            <p>That gap looks small written down. Across a season it decides everything.</p>
        </article>
    </div>
    <div class="ob-tri__cta">
        <a class="ob-btn" href="<?= e(url('/the-playbook')) ?>">Get the Picks</a>
        <a class="ob-btn ob-btn--ghost" href="<?= e(url('/performance')) ?>">See the Record</a>
    </div>
</section>

<section class="ob-board" data-kickoff="<?= e($countdownAt) ?>">
    <div class="ob-board__inner">
        <span class="ob-board__kicker"><?= e($kickoffKicker) ?></span>
        <h2 class="ob-board__title" data-pre="<?= e($kickoffTitlePre) ?>" data-live="<?= e($kickoffTitleLive) ?>"><?= e($kickoffTitlePre) ?></h2>
        <div class="ob-board__clock" role="timer" aria-live="off" aria-label="Countdown to NFL kickoff">
            <div class="ob-board__group"><div class="ob-board__flaps" data-unit="d"></div><span class="ob-board__label">Days</span></div>
            <div class="ob-board__colon">:</div>
            <div class="ob-board__group"><div class="ob-board__flaps" data-unit="h"></div><span class="ob-board__label">Hrs</span></div>
            <div class="ob-board__colon">:</div>
            <div class="ob-board__group"><div class="ob-board__flaps" data-unit="m"></div><span class="ob-board__label">Min</span></div>
            <div class="ob-board__colon">:</div>
            <div class="ob-board__group"><div class="ob-board__flaps" data-unit="s"></div><span class="ob-board__label">Sec</span></div>
        </div>
        <p class="ob-board__sub" data-pre="<?= e($kickoffSubPre) ?>" data-live="<?= e($kickoffSubLive) ?>"><?= e($kickoffSubPre) ?></p>
        <a class="ob-board__cta" href="<?= e(url('/the-playbook')) ?>" data-pre="<?= e($kickoffCtaPre) ?>" data-live="<?= e($kickoffCtaLive) ?>"><?= e($kickoffCtaPre) ?></a>
        <p class="ob-board__fine"><?= e(cms('footer_disclaimer', '21+. Informational use only, not betting advice. 1-800-GAMBLER.')) ?></p>
    </div>
</section>
