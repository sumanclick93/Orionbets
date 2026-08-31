<?php
$aboutHeroHeadline = cms('about_hero_headline', 'Victory is found in the Margins');

$story1Eyebrow = cms('story_1_eyebrow', '01 · Who we are');
$story1Title = cms('story_1_title', "Built from a bettor's edge.");
$story1Body = cms('story_1_body', "Before we ever bet against the board, our founder priced it. We set lines in Las Vegas deciding what a game was worth, and watching the public tell us where we were wrong.\n\nWe spent years turning this instinct into rules. So we built a system to run those rules across every game, every night, at a scale no person can match.\n\nOur system works while the rest of the world sleeps. It has no favorite teams, no bad beats to avenge, and no bad nights.");
$story1Scrawl = cms('story_1_scrawl', 'no favorite team. no bad nights.');

$story2Eyebrow = cms('story_2_eyebrow', '02 · Our process');
$story2Title = cms('story_2_title', 'What lands in your inbox.');
$story2Body = cms('story_2_body', "The system runs overnight across every sport we cover.\n\nIn the morning, someone on our desk goes through what came back and writes it up in plain English: the game, and the odds as we see them. If a call can't be explained in a sentence, it doesn't go out.\n\nEverything is sent before kickoff, with a timestamp. Once the games end, Action Network records the result. They keep the count, so we can't touch it.");

$story3Eyebrow = cms('story_3_eyebrow', '03 · Our value');
$story3Title = cms('story_3_title', 'Six points is the whole game.');
$story3Body = cms('story_3_body', "Every bet comes with a price built in. To come out even, you have to win about 52 out of every 100. That's the wall, and most people never hear about it.\n\nOur record sits at 59.\n\nThat gap looks small written down. Across a season it decides everything, and it only holds up if the stakes stay boring and the decisions stay the same size.\n\nAccess an edge that'll help you stay ahead of the competition. The bets we'd make right in front of you before you decide. Built on a record you can trust.");

$valpropTitle = cms('valprop_title', 'The only number the house needs.');
$valpropSubtitle = cms('valprop_subtitle', 'Certainty is the oldest con in this business.');
?>
<section class="ob-about-hero">
    <div class="container">
        <h1><?= e($aboutHeroHeadline) ?></h1>
    </div>
</section>

<section class="section ob-story">
    <div class="container ob-story__grid">
        <article>
            <span class="ob-tri__eyebrow"><?= e($story1Eyebrow) ?></span>
            <h2><?= e($story1Title) ?></h2>
            <?php foreach (array_filter(explode("\n\n", $story1Body)) as $p): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
            <?php endforeach; ?>
            <?php if ($story1Scrawl !== ''): ?>
                <p class="ob-tri__scrawl"><?= e($story1Scrawl) ?></p>
            <?php endif; ?>
        </article>
        <article>
            <span class="ob-tri__eyebrow"><?= e($story2Eyebrow) ?></span>
            <h2><?= e($story2Title) ?></h2>
            <?php foreach (array_filter(explode("\n\n", $story2Body)) as $p): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
            <?php endforeach; ?>
        </article>
        <article>
            <span class="ob-tri__eyebrow"><?= e($story3Eyebrow) ?></span>
            <h2><?= e($story3Title) ?></h2>
            <?php foreach (array_filter(explode("\n\n", $story3Body)) as $p): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
            <?php endforeach; ?>
        </article>
    </div>
</section>

<section class="ob-mg">
    <div class="container">
        <header class="ob-mg__head">
            <h2 class="ob-mg__title"><?= nl2br(e($valpropTitle)) ?></h2>
            <p class="ob-mg__kick"><?= e($valpropSubtitle) ?></p>
        </header>
        <div class="ob-mg__scale">
            <span class="ob-mg__lab">Every 100 bets</span>
            <div class="ob-mg__labels">
                <div class="ob-mg__lg ob-mg__lg--wall" style="left:52.4%"><b>52.4</b><span>The wall</span></div>
                <div class="ob-mg__lg ob-mg__lg--orion" style="left:59%"><b>59</b><span>Orion · aggregate</span></div>
            </div>
            <div class="ob-mg__track">
                <div class="ob-mg__house" style="width:52.4%"></div>
                <div class="ob-mg__gain" style="left:52.4%;width:6.6%"></div>
                <div class="ob-mg__wall" style="left:52.4%"></div>
            </div>
            <div class="ob-mg__axis"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
            <p class="ob-tri__scrawl">six points. that's the whole game.</p>
        </div>
        <div class="ob-mg__units">
            <span class="ob-mg__lab">Same 100 bets · same stake every time</span>
            <div class="ob-mg__row">
                <div class="ob-mg__who"><b>The wall</b><span>52.4% of bets won</span></div>
                <div class="ob-mg__uval">±0%<small>you end where you started</small></div>
            </div>
            <div class="ob-mg__row">
                <div class="ob-mg__who"><b>Orion</b><span>59.0% of bets won</span></div>
                <div class="ob-mg__uval ob-mg__uval--win">+12.6%<small>you end ahead</small></div>
            </div>
            <p class="ob-mg__foot-note">Arithmetic at standard pricing — not a projection.</p>
        </div>
        <div class="ob-mg__goat">
            <h3>Make better decisions. Win more.</h3>
            <div class="ob-mg__panel">
                <div class="ob-mg__stat"><b>104 pts</b><span>win probability lost to bad fourth-down calls, one weekend</span></div>
                <div class="ob-mg__arrow">→</div>
                <div class="ob-mg__stat"><b>¾ win</b><span>what it costs a team across a season</span></div>
                <p class="ob-tri__scrawl">the edge is in the decision.</p>
                <p class="ob-mg__credit">EdjSports · 75 sub-optimal fourth-down decisions across one NFL weekend · ≈1.4 points of win probability each</p>
            </div>
        </div>
        <div class="ob-mg__band">Every pick published before the game.<br>Every result published after.</div>
    </div>
</section>

<section class="ob-edge">
    <div class="container">
        <h2>Take back the edge</h2>
        <p>Send us your details, and let's revolutionize your game portfolio today.</p>
        <div class="ob-tri__cta">
            <a class="ob-btn" href="<?= e(url('/the-playbook')) ?>">Get the Picks</a>
            <a class="ob-btn ob-btn--ghost" href="<?= e(url('/contact')) ?>">Write the desk</a>
        </div>
    </div>
</section>
