<?php
declare(strict_types=1);

$signupUrl = $signupUrl ?? 'https://orionbets.everflowclient.io/affiliate/signup';
$portalUrl = $portalUrl ?? 'https://orionbets.everflowclient.io/';
$supportEmail = $supportEmail ?? 'support@orionbets.co';
$actionNetworkUrl = $actionNetworkUrl ?? 'https://app.actionnetwork.com/4zu6/oharfju5';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Anton&family=Archivo:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&family=Permanent+Marker&display=swap');

html {
  scroll-behavior: smooth;
}

.ob-af-wrapper {
  background: #0B0D10;
  color: #EAE6DC;
  width: 100%;
  overflow-x: hidden;
}

.ob-af {
  --ink:#0B0D10; --ink2:#12151A; --well:#07090C; --hair:#262B33;
  --bone:#EAE6DC; --dim:#A8A499; --graph:#8A9099; --win:#4CC27E;
  --turf:#0F8C44; --sodium:#F26A1B; --blue:#2050E0;
  container-type:inline-size;
  width:100%; max-width:100%; box-sizing:border-box;
  background:var(--ink); color:var(--bone);
  font-family:'Archivo',-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif!important;
  padding:clamp(36px,5.5cqw,80px) clamp(20px,4cqw,64px);
  padding-left: max(clamp(20px, 4cqw, 64px), calc((100% - 1180px) / 2));
  padding-right: max(clamp(20px, 4cqw, 64px), calc((100% - 1180px) / 2));
  border-bottom: 1px solid rgba(38, 43, 51, 0.45);
}
.ob-af *{ box-sizing:border-box; }
.ob-af__kick{ display:block; font-family:'IBM Plex Mono','Courier New',monospace!important;
  font-size:clamp(9.5px,1.15cqw,12px); letter-spacing:.22em; text-transform:uppercase;
  color:var(--graph); margin-bottom:.9em; }
.ob-af h2{ font-family:'Anton',Impact,'Arial Black',sans-serif!important; font-weight:400!important;
  text-transform:uppercase; line-height:.98; margin:0 0 .5em;
  font-size:clamp(28px,5.4cqw,62px); color:var(--bone)!important; }
.ob-af h3{ font-family:'Anton',Impact,sans-serif!important; font-weight:400!important;
  text-transform:uppercase; font-size:clamp(16px,2.1cqw,24px); margin:0 0 .5em; color:var(--bone)!important; }
.ob-af p{ color:var(--dim); font-size:clamp(13.5px,1.5cqw,17px); line-height:1.62; margin:0 0 .9em; max-width:60ch; }
.ob-af p strong{ color:var(--bone); font-weight:500; }
.ob-af__scrawl{ font-family:'Permanent Marker',cursive!important; color:rgba(240,237,228,.95)!important;
  font-size:clamp(14px,2.2cqw,26px); text-shadow:2px 2px 0 rgba(11,13,16,.55); }

/* buttons */
.ob-af__btn{ display:inline-block; background:var(--bone); color:var(--ink)!important;
  font-family:'Anton',Impact,sans-serif!important; text-transform:uppercase; letter-spacing:.05em;
  font-size:clamp(13px,1.7cqw,18px); padding:.85em 1.6em; text-decoration:none; min-height:46px;
  transition:transform .12s ease, background .12s ease; border: none; cursor: pointer; }
.ob-af__btn:hover{ background:#F4F1E8; transform:translateY(-1px); }
.ob-af__btn--ghost{ background:transparent; color:var(--bone)!important; border:1px solid rgba(234,230,220,.5); }
.ob-af__btn--ghost:hover{ background:rgba(234,230,220,.09); border-color:var(--bone); }
.ob-af__btn:focus-visible{ outline:2px solid var(--bone); outline-offset:3px; }
.ob-af__ctas{ display:flex; gap:10px; flex-wrap:wrap; margin-top:clamp(14px,2.4cqw,26px); }
.ob-af__login{ font-family:'IBM Plex Mono',monospace!important; font-size:clamp(10px,1.25cqw,13px);
  color:var(--dim); margin-top:1.1em; }
.ob-af__login a{ color:var(--bone); text-decoration: underline; text-underline-offset: 3px; }

/* reveal on scroll */
.ob-af [data-rise]{ opacity:0; transform:translateY(14px);
  transition:opacity .55s ease, transform .55s cubic-bezier(.2,.7,.3,1); }
.ob-af.is-in [data-rise]{ opacity:1; transform:none; }
.ob-af.is-in [data-rise="2"]{ transition-delay:.12s; }
.ob-af.is-in [data-rise="3"]{ transition-delay:.24s; }
.ob-af.is-in [data-rise="4"]{ transition-delay:.36s; }

/* grids */
.ob-af__two{ display:grid; grid-template-columns:1.05fr .95fr; gap:clamp(22px,4cqw,56px); align-items:center; }
.ob-af__cards{ display:grid; grid-template-columns:repeat(3,1fr); gap:clamp(14px,2.4cqw,22px); }
.ob-af__pair{ display:grid; grid-template-columns:1fr 1fr; gap:clamp(14px,2.4cqw,22px); }
.ob-af__stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:clamp(12px,2cqw,20px); }
@container (max-width:820px){ .ob-af__two,.ob-af__cards,.ob-af__pair{ grid-template-columns:1fr; } }
@container (max-width:820px){ .ob-af__stats{ grid-template-columns:repeat(3,1fr); } }
@container (max-width:520px){ .ob-af__stats{ grid-template-columns:1fr; } }

.ob-af__card{ border:1px solid var(--hair); background:var(--ink2); padding:clamp(16px,2.4cqw,26px); }
.ob-af__card p{ font-size:clamp(12.5px,1.35cqw,15px); margin:0; }
.ob-af__card svg{ width:clamp(30px,3.6cqw,44px); height:auto; display:block; margin-bottom:.9em; }

/* inline campaign chart — renders with no image upload */
.ob-af__adcard{ position:relative; overflow:hidden;
  background-color:var(--blue);
  background-image:radial-gradient(rgba(0,0,0,.15) 2.1px, transparent 2.2px);
  background-size:15px 15px; background-repeat:repeat;
  border:1px solid var(--hair); padding:clamp(16px,2.4cqw,26px); }
.ob-af__adhead{ font-family:'Anton',Impact,'Arial Black',sans-serif!important; font-weight:400;
  text-transform:uppercase; line-height:.95; color:#EDE8DE;
  font-size:clamp(22px,4.2cqw,46px); margin:0 0 clamp(12px,1.8cqw,20px); position:relative; }
.ob-af__chart{ position:relative; background:rgba(7,9,12,.94); border:1px solid rgba(38,43,51,.9);
  padding:clamp(12px,1.6cqw,18px) clamp(12px,1.6cqw,18px) clamp(20px,2.4cqw,26px); }
.ob-af__chart svg{ width:100%; height:auto; display:block; }

/* the duel — three bars race to their number */
.ob-af__duel{ position:relative; background:rgba(7,9,12,.94); border:1px solid rgba(38,43,51,.9);
  padding:clamp(16px,2.2cqw,22px) clamp(14px,2cqw,20px) clamp(12px,1.8cqw,16px); }
.ob-af__duel .row{ margin-bottom:clamp(12px,1.7cqw,17px); }
.ob-af__duel .lab{ display:flex; justify-content:space-between; align-items:baseline; margin-bottom:6px; gap:10px; }
.ob-af__duel .lab span{ font-family:'IBM Plex Mono',monospace!important; font-size:clamp(8.5px,1.05cqw,10px);
  letter-spacing:.16em; text-transform:uppercase; color:var(--graph); }
.ob-af__duel .lab b{ font-family:'Anton',Impact,sans-serif!important; font-weight:400;
  font-size:clamp(20px,2.9cqw,28px); line-height:1; font-variant-numeric:tabular-nums; color:var(--bone); }
.ob-af__duel .lab b.muted{ color:var(--graph); }
.ob-af__duel .lab b a{ color:var(--bone); text-decoration:underline; text-decoration-color:rgba(234,230,220,.3);
  text-underline-offset:4px; }
.ob-af__duel .lab b a:hover{ text-decoration-color:var(--bone); }
.ob-af__duel .track{ position:relative; height:clamp(20px,2.6cqw,26px); background:#12161C;
  border:1px solid rgba(38,43,51,.9); }
.ob-af__duel .fill{ position:absolute; left:0; top:0; bottom:0; width:0;
  transition:width 1.15s cubic-bezier(.2,.7,.3,1); }
.ob-af__duel .fill.house{ background:#2A313C; }
.ob-af__duel .fill.orion{ background:var(--turf); }
.ob-af__duel .fill.orion.agg{ background:rgba(15,140,68,.55); }
.ob-af__duel .wallmark{ position:absolute; top:-7px; bottom:-7px; width:0;
  border-left:3px dashed var(--bone); opacity:0; transition:opacity .4s .6s; }
.ob-af.is-in .ob-af__duel .wallmark{ opacity:.85; }
.ob-af__duel .gap{ position:relative; height:30px; margin-top:2px; }
.ob-af__duel .gap i{ position:absolute; top:0; height:11px; border:2px solid var(--win); border-top:none;
  opacity:0; transition:opacity .35s 1.35s; }
.ob-af.is-in .ob-af__duel .gap i{ opacity:1; }
.ob-af__duel .gap b{ position:absolute; top:12px; font-family:'Anton',Impact,sans-serif!important;
  font-weight:400; font-size:clamp(18px,2.5cqw,24px); color:var(--win); opacity:0;
  transition:opacity .35s 1.5s; white-space:nowrap; }
.ob-af.is-in .ob-af__duel .gap b{ opacity:1; }
.ob-af__duel .scrawl{ font-family:'Permanent Marker',cursive!important; font-size:clamp(13px,1.7cqw,17px);
  color:rgba(240,237,228,.92); margin-top:8px; opacity:0; transition:opacity .4s 1.7s;
  text-shadow:2px 2px 0 rgba(11,13,16,.55); }
.ob-af.is-in .ob-af__duel .scrawl{ opacity:1; }
@media (prefers-reduced-motion:reduce){
  .ob-af__duel .fill,.ob-af__duel .wallmark,.ob-af__duel .gap i,
  .ob-af__duel .gap b,.ob-af__duel .scrawl{ transition:none!important; opacity:1!important; }
}
.ob-af__note{ position:absolute; right:clamp(10px,1.4cqw,16px); bottom:clamp(5px,.8cqw,8px);
  font-family:'IBM Plex Mono',monospace!important; font-size:clamp(7px,.85cqw,9.5px);
  letter-spacing:.1em; text-transform:uppercase; color:#5C6069; }
.ob-af__adfoot{ position:relative; margin-top:clamp(10px,1.6cqw,16px); background:var(--ink);
  padding:clamp(9px,1.3cqw,13px) clamp(12px,1.6cqw,17px);
  font-family:'IBM Plex Mono',monospace!important; font-size:clamp(8px,1cqw,11px);
  letter-spacing:.12em; text-transform:uppercase; color:var(--bone);
  display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.ob-af__adfoot .pct{ color:var(--win); }

/* stat tiles */
.ob-af__stat{ border:1px solid var(--hair); background:var(--well); padding:clamp(14px,2.2cqw,24px); text-align:center; }
.ob-af__stat b{ display:block; font-family:'Anton',Impact,sans-serif!important; font-weight:400;
  font-size:clamp(30px,5cqw,60px); line-height:1.05; color:var(--bone); }
.ob-af__stat span{ display:block; margin-top:1em; font-family:'IBM Plex Mono',monospace!important;
  font-size:clamp(8px,1.05cqw,11px); letter-spacing:.14em; text-transform:uppercase; color:var(--graph); }
.ob-af__stat a, .ob-af__wall a{
  color:var(--bone); border-bottom:none;
  text-decoration:underline; text-decoration-style:dashed;
  text-decoration-color:var(--graph); text-decoration-thickness:2px;
  text-underline-offset:.12em;
}
.ob-af__stat a:hover, .ob-af__wall a:hover{ text-decoration-color:var(--bone); }

/* the wall block */
.ob-af__wall{ display:grid; grid-template-columns:1fr auto 1fr; align-items:center;
  border:1px solid var(--hair); background:var(--well); padding:clamp(16px,2.4cqw,26px); }
.ob-af__wall .n{ font-family:'Anton',Impact,sans-serif!important; font-weight:400;
  font-size:clamp(34px,6cqw,72px); line-height:1.05; text-align:center; display:block; color:var(--bone); }
.ob-af__wall .l{ font-family:'IBM Plex Mono',monospace!important; font-size:clamp(8px,1.05cqw,11px);
  letter-spacing:.14em; text-transform:uppercase; color:var(--graph); text-align:center; display:block; margin-top:1em; }
.ob-af__wall .house .n{ color:var(--graph); }
.ob-af__wall .sep{ font-family:'Permanent Marker',cursive!important; padding:0 .6em; color:var(--bone); }

/* numbered lists */
.ob-af__steps{ counter-reset:obaf; list-style:none; margin:0; padding:0; }
.ob-af__steps li{ counter-increment:obaf; position:relative; padding-left:2.4em;
  margin-bottom:.85em; font-size:clamp(13px,1.4cqw,16px); color:var(--dim); line-height:1.55; }
.ob-af__steps li::before{ content:counter(obaf); position:absolute; left:0; top:.05em;
  font-family:'Anton',Impact,sans-serif; font-size:clamp(13px,1.5cqw,17px); color:var(--bone); }
.ob-af__steps li strong{ color:var(--bone); font-weight:500; }

/* commission callout */
.ob-af__pct{ text-align:center; }
.ob-af__pct p{ margin-left:auto; margin-right:auto; text-align:center; }
.ob-af__pct{ border:1px solid var(--hair); background:var(--well);
  padding:clamp(26px,4.5cqw,54px) clamp(18px,3cqw,36px); }
.ob-af__pct b{ display:block; font-family:'Anton',Impact,sans-serif!important; font-weight:400;
  font-size:clamp(60px,13cqw,150px); line-height:.9; color:var(--bone); }
.ob-af__pct .sub{ font-family:'IBM Plex Mono',monospace!important; font-size:clamp(9px,1.3cqw,14px);
  letter-spacing:.16em; text-transform:uppercase; color:var(--dim); margin-top:.8em; }

/* commission rate rows */
.ob-af__rates{ display:grid; grid-template-columns:repeat(3,1fr); gap:clamp(10px,1.8cqw,18px);
  margin-top:clamp(18px,2.8cqw,32px); }
@container (max-width:620px){ .ob-af__rates{ grid-template-columns:1fr; } }
.ob-af__rate{ border:1px solid var(--hair); background:var(--ink2); padding:clamp(14px,2cqw,22px); }
.ob-af__rate b{ display:block; font-family:'Anton',Impact,sans-serif!important; font-weight:400;
  font-size:clamp(26px,3.6cqw,44px); line-height:1.05; color:var(--bone); }
.ob-af__rate span{ display:block; margin-top:.8em; font-family:'IBM Plex Mono',monospace!important;
  font-size:clamp(8px,1.05cqw,11px); letter-spacing:.12em; text-transform:uppercase;
  color:var(--graph); line-height:1.8; }

/* faq */
.ob-af__faq details{ border:1px solid var(--hair); border-bottom:none; background:var(--ink2); }
.ob-af__faq details:last-of-type{ border-bottom:1px solid var(--hair); }
.ob-af__faq summary{ cursor:pointer; padding:clamp(13px,1.8cqw,20px); list-style:none;
  font-family:'IBM Plex Mono',monospace!important; font-size:clamp(11.5px,1.35cqw,14px);
  color:var(--bone); display:flex; justify-content:space-between; gap:14px; align-items:center; min-height:46px; }
.ob-af__faq summary::-webkit-details-marker{ display:none; }
.ob-af__faq summary::after{ content:"+"; color:var(--graph); font-size:1.3em; line-height:1; }
.ob-af__faq details[open] summary::after{ content:"–"; }
.ob-af__faq .a ul{ list-style:disc; }
.ob-af__faq .a{ padding:0 clamp(13px,1.8cqw,20px) clamp(15px,2cqw,22px);
  font-size:clamp(12.5px,1.4cqw,15.5px); color:var(--dim); line-height:1.62; max-width:72ch; }

/* band + house rules */
.ob-af--band{
  background-color:var(--turf);
  background-image:radial-gradient(rgba(0,0,0,.16) 2.1px, transparent 2.2px);
  background-size:15px 15px;
  background-repeat:repeat;
  background-position:0 0;
  text-align:center; }
.ob-af--band h2{ color:var(--ink)!important; }
.ob-af--band p{ color:rgba(11,13,16,.85); font-family:'IBM Plex Mono',monospace!important;
  font-size:clamp(10px,1.3cqw,14px); letter-spacing:.06em; text-transform:uppercase; margin:0 auto 1.4em; }
.ob-af--band .ob-af__btn{ background:var(--ink); color:var(--bone)!important; }
.ob-af__house{ font-family:'IBM Plex Mono',monospace!important; font-size:clamp(10px,1.15cqw,12px);
  line-height:1.9; color:var(--dim); border-top:1px solid var(--hair); padding-top:1.2em; margin-top:1.6em; }
.ob-af__house b{ display:block; font-size:.82em; letter-spacing:.14em; text-transform:uppercase; }
.ob-af__house a{ color:var(--dim); }

@media (prefers-reduced-motion: reduce){
  .ob-af [data-rise]{ opacity:1!important; transform:none!important; transition:none!important; }
  .ob-af__btn{ transition:none; }
}
</style>

<div class="ob-af-wrapper">

  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 1 · HERO
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af>
    <div class="ob-af__two">
      <div>
        <span class="ob-af__kick" data-rise>OrionBets Affiliate Program</span>
        <h2 data-rise>Monetize your sports betting traffic.</h2>
        <p data-rise="2">Promote a picks product with a <strong>59% verified win rate across every sport</strong> &mdash; and <strong>68% in the NFL</strong>. Both publicly tracked on Action Network, where your audience can check them without taking your word for it. <strong>Competitive commissions with no earnings cap</strong> — 20% of every monthly subscription for the first four months.</p>
        <div class="ob-af__ctas" data-rise="3">
          <a class="ob-af__btn" href="<?= e(url($signupUrl)) ?>" target="_blank" rel="noopener">Become an Affiliate</a>
          <a class="ob-af__btn ob-af__btn--ghost" href="#af-faq">Learn More</a>
        </div>
        <p class="ob-af__login" data-rise="4">Already a partner? <a href="<?= e(url($portalUrl)) ?>" target="_blank" rel="noopener">Log in to your Everflow dashboard →</a></p>
      </div>
      <div data-rise="2">
        <div class="ob-af__adcard">
          <div class="ob-af__adhead">We don&rsquo;t beat luck.<br>We beat math.</div>

          <div class="ob-af__duel">
            <div class="row">
              <div class="lab"><span>The wall &middot; break-even</span><b class="muted">52.4</b></div>
              <div class="track"><div class="fill house" data-w="52.4"></div></div>
            </div>

            <div class="row">
              <div class="lab"><span>Orion &middot; NFL</span>
                <b><a href="<?= e($actionNetworkUrl) ?>" target="_blank" rel="noopener">68%</a></b></div>
              <div class="track"><div class="fill orion" data-w="68"></div>
                <div class="wallmark" style="left:52.4%"></div></div>
            </div>

            <div class="gap"><i style="left:52.4%;width:15.6%"></i><b style="left:69%">+15.6</b></div>

            <div class="row" style="margin-top:14px;">
              <div class="lab"><span>Orion &middot; every sport</span>
                <b><a href="<?= e($actionNetworkUrl) ?>" target="_blank" rel="noopener">59%</a></b></div>
              <div class="track"><div class="fill orion agg" data-w="59"></div>
                <div class="wallmark" style="left:52.4%"></div></div>
            </div>

            <div class="scrawl">fifteen points. that&rsquo;s the whole game.</div>
          </div>

          <div class="ob-af__adfoot">
            <span>Counted on Action Network</span>
            <span class="pct">No cap on earnings</span>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 2 · WHY IT CONVERTS
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af>
    <span class="ob-af__kick" data-rise>Why it converts</span>
    <h2 data-rise>68% against a wall of 52.4.</h2>
    <p data-rise="2">To break even against a standard bet you have to win 52 out of every 100 — that's the wall, and it's what the house counts on. Our record clears it, in public, all year &mdash; 68% in the NFL and 59% across every sport we cover.</p>

    <div style="max-width:560px;margin-top:clamp(14px,2.4cqw,26px);" data-rise="2">
      <div class="ob-af__wall">
        <div><span class="n"><a href="<?= e($actionNetworkUrl) ?>" target="_blank" rel="noopener">68%</a></span><span class="l">NFL &middot; verified</span></div>
        <div class="sep">vs.</div>
        <div class="house"><span class="n">52.4</span><span class="l">The wall &middot; break-even</span></div>
      </div>
      <p style="margin-top:.9em;font-family:'IBM Plex Mono',monospace;font-size:clamp(10px,1.2cqw,12.5px);letter-spacing:.08em;text-transform:uppercase;color:var(--graph);">
        <a href="<?= e($actionNetworkUrl) ?>" target="_blank" rel="noopener" style="color:var(--dim);">59% across every sport</a> &middot; +6.6 past the wall
      </p>
    </div>

    <div class="ob-af__stats" style="margin-top:clamp(16px,2.6cqw,28px);" data-rise="3">
      <div class="ob-af__stat"><b><a href="<?= e($actionNetworkUrl) ?>" target="_blank" rel="noopener">68%</a></b><span>NFL win rate &middot; tap to check</span></div>
      <div class="ob-af__stat"><b>+15.6</b><span>Points clear of the wall</span></div>
      <div class="ob-af__stat"><b>20%</b><span>Recurring, first four months</span></div>
    </div>

    <p style="margin-top:clamp(14px,2cqw,22px);font-family:'IBM Plex Mono',monospace;font-size:clamp(9.5px,1.15cqw,12px);letter-spacing:.1em;text-transform:uppercase;color:var(--graph);" data-rise="4">
      Action Network tracks our record, so your audience gets picks and results they can trust.
    </p>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 3 · TWO WAYS IN
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af>
    <span class="ob-af__kick" data-rise>How it works</span>
    <h2 data-rise>Two ways in.</h2>
    <div class="ob-af__pair" style="margin-top:clamp(16px,2.6cqw,28px);">
      <div class="ob-af__card" data-rise="2">
        <h3>New to affiliate marketing</h3>
        <ol class="ob-af__steps">
          <li><strong>Sign up through our Everflow signup page</strong> — takes a few minutes.</li>
          <li>We review every application, then send your unique tracking link.</li>
          <li>Share it with your audience: posts, streams, newsletter, group chat.</li>
          <li>Anyone who subscribes through your link is tracked to you automatically.</li>
          <li>Earn 20% of every monthly subscription for the first four months. No cap.</li>
        </ol>
      </div>
      <div class="ob-af__card" data-rise="3">
        <h3>Experienced affiliate</h3>
        <ol class="ob-af__steps">
          <li><strong>Sign up through our Everflow signup page</strong> — every applicant is screened, so this is the only way in.</li>
          <li>Once approved, pull creative from the partner asset library: logos, Card templates, banner sets.</li>
          <li>Deploy across your channels with your tracking links.</li>
          <li>Monitor clicks, conversions, and payouts in the Everflow dashboard.</li>
          <li>Scale what works — no cap on what you can earn.</li>
        </ol>
      </div>
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 4 · WHY PARTNER
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
      <filter id="obAfRough"><feTurbulence type="fractalNoise" baseFrequency="0.045" numOctaves="3" seed="7" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="2.2"/></filter>
      <marker id="obAfArr" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10" fill="none" stroke="#EAE6DC" stroke-width="1.8" stroke-linecap="round"/></marker>
    </defs></svg>

    <h2 data-rise>Why partner with OrionBets</h2>
    <div class="ob-af__cards" style="margin-top:clamp(16px,2.6cqw,28px);">
      <div class="ob-af__card" data-rise="2">
        <svg viewBox="0 0 54 54"><g stroke="#EAE6DC" stroke-width="2.6" stroke-linecap="round" filter="url(#obAfRough)"><line x1="14" y1="12" x2="14" y2="40"/><line x1="22" y1="12" x2="22" y2="40"/><line x1="30" y1="12" x2="30" y2="40"/><line x1="38" y1="12" x2="38" y2="40"/><line x1="8" y1="38" x2="46" y2="16"/></g></svg>
        <h3>A record you can stand behind</h3>
        <p>Every pick is tracked publicly by a third party. Your audience verifies it themselves — you never have to defend a hot streak.</p>
      </div>
      <div class="ob-af__card" data-rise="3">
        <svg viewBox="0 0 54 54"><path d="M 10 42 C 22 38, 30 30, 42 14" fill="none" stroke="#EAE6DC" stroke-width="2.6" stroke-linecap="round" stroke-dasharray="9 7" marker-end="url(#obAfArr)" filter="url(#obAfRough)"/></svg>
        <h3>Paid on every plan</h3>
        <p>20% of every monthly subscription for the first four months. No cap on what you can earn.</p>
      </div>
      <div class="ob-af__card" data-rise="4">
        <svg viewBox="0 0 54 54"><circle cx="27" cy="27" r="18" fill="none" stroke="#EAE6DC" stroke-width="2.4" stroke-dasharray="100 12" filter="url(#obAfRough)"/><path d="M 22 19 L 36 27 L 22 35 Z" fill="none" stroke="#EAE6DC" stroke-width="2.2" stroke-linejoin="round" filter="url(#obAfRough)"/></svg>
        <h3>Creative that's ready to run</h3>
        <p>Full brand kit in the partner library: lockups in every sport colorway, Card ad templates, and daily Playbook content to repost.</p>
      </div>
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 5 · COMMISSION CALLOUT
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af>
    <div class="ob-af__pct" data-rise>
      <span class="ob-af__kick" style="text-align:center;">Commission</span>
      <b data-count="20">20</b>
      <div class="sub">Recurring on monthly plans · up to 4 months</div>

      <div class="ob-af__rates" data-rise="2">
        <div class="ob-af__rate"><b>20%</b><span>Of every monthly subscription<br>first four months</span></div>
        <div class="ob-af__rate"><b>$49.99</b><span>What a subscription costs<br>the only product</span></div>
        <div class="ob-af__rate"><b>No cap</b><span>On what you can earn<br>however many you refer</span></div>
      </div>

      <p style="margin:1.3em auto 0;max-width:48ch;text-align:center;">On every qualified new subscriber who signs up through your link. <strong>No cap on your earnings.</strong></p>
      <div class="ob-af__ctas" style="justify-content:center;" data-rise="3">
        <a class="ob-af__btn" href="<?= e(url($signupUrl)) ?>" target="_blank" rel="noopener">Become an Affiliate</a>
      </div>
      <p style="margin:1.1em auto 0;max-width:46ch;text-align:center;font-family:'IBM Plex Mono',monospace;font-size:clamp(9.5px,1.15cqw,12px);letter-spacing:.1em;text-transform:uppercase;color:var(--graph);">
        Signup and approval handled in Everflow · paid monthly
      </p>
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 6 · FAQ
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af id="af-faq">
    <span class="ob-af__kick" data-rise>FAQ</span>
    <h2 data-rise>Questions before you apply</h2>
    <div class="ob-af__faq" style="margin-top:clamp(16px,2.6cqw,26px);" data-rise="2">

      <details open><summary>How much can I earn?</summary>
        <div class="a">
          There is no cap on your earnings. You earn competitive commissions for every qualified new subscriber who signs up using your unique link:
          <ul style="margin:.9em 0 0 1.2em;padding:0;">
            <li style="padding:.22em 0;"><strong style="color:var(--bone);">20% of every monthly subscription</strong> &mdash; for the first four months, on every qualified new subscriber</li>
            <li style="padding:.22em 0;"><strong style="color:var(--bone);">No cap</strong> &mdash; however many you refer</li>
          </ul>
          Payouts are processed monthly for referrals earned during the previous calendar month. Payments are sent directly to your preferred payout method, configured in your Everflow affiliate portal account settings.</div></details>

      <details><summary>Is there a minimum audience size?</summary>
        <div class="a">No — we welcome partners of all sizes. Whether you run a major sports publication, a niche blog, or a growing social media account, you are eligible to apply. We value audience engagement and content quality far more than raw follower count.</div></details>

      <details><summary>How long does the tracking cookie last?</summary>
        <div class="a">Our tracking cookies use a 30-day, last-click attribution window. If a user clicks your link and subscribes within 30 days, you earn the commission — as long as your link was the final touchpoint before sign-up.</div></details>

      <details><summary>What creative assets do you provide?</summary>
        <div class="a">Once approved, you gain instant access to our affiliate portal. Resources include ready-to-use text links, promotional copy, and display banners optimized for web and mobile (728×90, 300×250, 160×600, and 320×50).</div></details>

      <details><summary>Do I need to be a betting expert?</summary>
        <div class="a">Not at all. Familiarity with sports or sports analytics helps, but you don't need to be a professional handicapper. Our platform handles the heavy data crunching for users — your main role is showing your audience how our tools help them make smarter, data-driven decisions. We also provide onboarding cheat sheets and walkthroughs to make sharing easy.</div></details>

      <details><summary>What is OrionBets and what am I promoting?</summary>
        <div class="a">The Playbook: daily calls from a system trained on the success of one of the best bettors in the game, published before every game, with every result posted publicly on Action Network. Coverage spans NFL, CFL, US college football, NBA, WNBA, college basketball and more.</div></details>

      <details><summary>Who do I contact with questions?</summary>
        <div class="a">Email <a href="mailto:<?= e($supportEmail) ?>" style="color:var(--bone);"><?= e($supportEmail) ?></a> — our affiliate desk will reply promptly.</div></details>

    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 7 · ALREADY A PARTNER
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af" data-af>
    <h2 data-rise>Already a partner?</h2>
    <p data-rise="2">Your tracking links, creative assets, and performance reporting all live in Everflow. Head there to grab a link or check your numbers.</p>
    <div class="ob-af__ctas" data-rise="3">
      <a class="ob-af__btn" href="<?= e(url($portalUrl)) ?>" target="_blank" rel="noopener">Open Everflow Dashboard</a>
      <a class="ob-af__btn ob-af__btn--ghost" href="mailto:<?= e($supportEmail) ?>">Email the affiliate team</a>
    </div>
    <div class="ob-af__house">
      <b>House Rules</b>
      21+. Informational use only — not betting advice. Partners must not present picks as guaranteed outcomes or cite subscriber earnings in promotion.
      <a href="tel:18004262537">1-800-GAMBLER</a>.
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════════════
       BLOCK 8 · CLOSING BAND
       ═══════════════════════════════════════════════════════════════ -->
  <div class="ob-af ob-af--band" data-af>
    <h2 data-rise>Get in the game.</h2>
    <p data-rise="2">No earnings cap &middot; 68% NFL, 59% across every sport &middot; signup in minutes</p>
    <a class="ob-af__btn" href="<?= e(url($signupUrl)) ?>" data-rise="3" target="_blank" rel="noopener">Become an Affiliate</a>
  </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════
     BLOCK 9 · SHARED SCRIPT
     ═══════════════════════════════════════════════════════════════ -->
<script>
(function(){
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function countUp(el){
    var to = parseInt(el.dataset.count, 10), sfx = (el.dataset.suffix !== undefined ? el.dataset.suffix : '%');
    if (reduce) { el.textContent = to + sfx; return; }
    var t0 = null, dur = 900;
    (function step(t){
      if (!t0) t0 = t;
      var k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
      el.textContent = Math.round(to * e) + sfx;
      if (k < 1) requestAnimationFrame(step); else el.textContent = to + sfx;
    })(performance.now());
  }

  function reveal(sec){
    sec.classList.add('is-in');
    sec.querySelectorAll('[data-count]').forEach(function(el){ setTimeout(function(){ countUp(el); }, 300); });
    sec.querySelectorAll('.ob-af__duel .fill[data-w]').forEach(function(el, i){
      var w = el.dataset.w + '%';
      if (reduce) { el.style.width = w; return; }
      setTimeout(function(){ el.style.width = w; }, 120 + i * 140);
    });
  }

  var secs = document.querySelectorAll('.ob-af[data-af]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(es){
      es.forEach(function(e){ if (e.isIntersecting) { reveal(e.target); io.unobserve(e.target); } });
    }, { threshold: 0.15 });
    secs.forEach(function(s){ io.observe(s); });
  } else {
    secs.forEach(reveal);
  }
})();
</script>
