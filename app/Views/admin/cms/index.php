<?php
$val = static fn (string $key, string $default = ''): string => (string) ($cms[$key] ?? $default);
?>
<div class="page-toolbar">
    <div>
        <p class="kicker">Content & Assets</p>
        <h2>Dynamic CMS & Marketing Hub</h2>
    </div>
    <div class="page-toolbar__actions">
        <a class="btn btn-ghost btn-small" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
            View Live Homepage ↗
        </a>
    </div>
</div>

<p class="lede" style="margin-bottom:1.5rem;">Update hero marketing copy, brand assets, and content blocks dynamically across the platform without touching template files.</p>

<div class="range-tabs" id="cms-tabs" style="margin-bottom:1.5rem;">
    <a href="#hero" class="is-active" data-tab-target="tab-hero">Hero & Banner</a>
    <a href="#branding" data-tab-target="tab-branding">Branding & Assets</a>
    <a href="#about" data-tab-target="tab-about">About Us & Stories</a>
    <a href="#footer" data-tab-target="tab-footer">Footer & Disclaimers</a>
</div>

<!-- Section 1: Hero & Countdown Controls -->
<section class="panel cms-section" id="tab-hero" style="margin-bottom:1.5rem;">
    <div style="margin-bottom:1.2rem;">
        <p class="kicker">Homepage Hero</p>
        <h3 style="margin:0;">Hero Section & Kickoff Countdown</h3>
    </div>
    <form method="post" action="<?= e(url('/admin/cms')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="hero">

        <label for="hero_headline">Hero Headline</label>
        <input id="hero_headline" name="hero_headline" value="<?= e($val('hero_headline', 'Our best bets, sent before kickoff.')) ?>" required>

        <label for="hero_subheadline">Hero Lede / Subheadline</label>
        <textarea id="hero_subheadline" name="hero_subheadline" rows="4"><?= e($val('hero_subheadline', 'Daily picks. Public record. No excuses. The Playbook is a daily picks subscription. Every morning you get our best bets — the play, the price, the size — from a system trained on the success of the best bettors in the game. Every result gets posted publicly, win or lose.')) ?></textarea>

        <div class="form-row split">
            <div>
                <label for="hero_cta_text">Primary CTA Button Text</label>
                <input id="hero_cta_text" name="hero_cta_text" value="<?= e($val('hero_cta_text', 'Get the picks')) ?>">
            </div>
            <div>
                <label for="hero_cta_url">Primary CTA URL Link</label>
                <input id="hero_cta_url" name="hero_cta_url" value="<?= e($val('hero_cta_url', '/the-playbook')) ?>">
            </div>
        </div>

        <div style="margin-top:1rem;padding:1rem;background:var(--color-surface-alt);border:1px solid var(--color-border);border-radius:var(--radius);">
            <label for="hero_banner_file" style="margin-bottom:0.35rem;font-weight:600;">Hero Graphic / Background Banner File Upload</label>
            <input id="hero_banner_file" name="hero_banner_file" type="file" accept="image/*" style="padding:0.4rem 0;">
            <p class="field-hint" style="margin:0.25rem 0 0.75rem;">Upload an image file (JPG, PNG, WEBP) to replace the hero background media.</p>
            
            <?php if ($val('hero_banner_url') !== ''): ?>
                <div style="margin:0.75rem 0 0.5rem;padding:0.75rem;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <img src="<?= e(url($val('hero_banner_url'))) ?>" alt="Hero Banner Preview" style="max-height:50px;width:auto;border-radius:4px;border:1px solid var(--color-border);">
                        <span class="fineprint" style="word-break:break-all;"><?= e($val('hero_banner_url')) ?></span>
                    </div>
                    <label class="checkbox" style="font-size:0.85rem;color:var(--color-danger, #ef4444);margin:0;">
                        <input type="checkbox" name="remove_hero_banner" value="1">
                        <span>Remove Banner Image</span>
                    </label>
                </div>
            <?php endif; ?>

            <details style="margin-top:0.5rem;font-size:0.85rem;">
                <summary style="cursor:pointer;color:var(--color-text-muted);">Or enter banner image URL manually</summary>
                <div style="margin-top:0.5rem;">
                    <input id="hero_banner_url" name="hero_banner_url" type="text" placeholder="https://example.com/assets/banner.jpg" value="<?= e($val('hero_banner_url')) ?>">
                </div>
            </details>
        </div>

        <hr style="border:0;border-top:1px solid var(--color-border);margin:1.5rem 0;">

        <div style="margin-bottom:1rem;">
            <p class="kicker">Flap Board Clock</p>
            <h4 style="margin:0;">Kickoff Countdown Settings</h4>
        </div>

        <div class="form-row split">
            <div>
                <label for="kickoff_countdown_at">Countdown Target (ISO 8601)</label>
                <input id="kickoff_countdown_at" name="kickoff_countdown_at" value="<?= e($val('kickoff_countdown_at', '2026-09-09T20:20:00-04:00')) ?>" placeholder="2026-09-09T20:20:00-04:00">
            </div>
            <div>
                <label for="kickoff_kicker">Board Kicker</label>
                <input id="kickoff_kicker" name="kickoff_kicker" value="<?= e($val('kickoff_kicker', 'ORIONBETS · THE PLAYBOOK · NFL')) ?>">
            </div>
        </div>

        <div class="form-row split">
            <div>
                <label for="kickoff_title_pre">Title (Pre-Kickoff)</label>
                <input id="kickoff_title_pre" name="kickoff_title_pre" value="<?= e($val('kickoff_title_pre', 'Season One kicks off in')) ?>">
            </div>
            <div>
                <label for="kickoff_title_live">Title (Live / Post-Kickoff)</label>
                <input id="kickoff_title_live" name="kickoff_title_live" value="<?= e($val('kickoff_title_live', 'Season One is live.')) ?>">
            </div>
        </div>

        <div class="form-row split">
            <div>
                <label for="kickoff_sub_pre">Sub-copy (Pre-Kickoff)</label>
                <input id="kickoff_sub_pre" name="kickoff_sub_pre" value="<?= e($val('kickoff_sub_pre', 'Lock the founders rate before the first whistle and your price never moves.')) ?>">
            </div>
            <div>
                <label for="kickoff_sub_live">Sub-copy (Live)</label>
                <input id="kickoff_sub_live" name="kickoff_sub_live" value="<?= e($val('kickoff_sub_live', "Today's slate is out. Every pick posted before kickoff.")) ?>">
            </div>
        </div>

        <div class="form-row split">
            <div>
                <label for="kickoff_cta_pre">Button Text (Pre-Kickoff)</label>
                <input id="kickoff_cta_pre" name="kickoff_cta_pre" value="<?= e($val('kickoff_cta_pre', 'Claim the Founders Rate')) ?>">
            </div>
            <div>
                <label for="kickoff_cta_live">Button Text (Live)</label>
                <input id="kickoff_cta_live" name="kickoff_cta_live" value="<?= e($val('kickoff_cta_live', "Get Today's Picks")) ?>">
            </div>
        </div>

        <button class="btn btn-primary" type="submit" style="margin-top:1rem;">Save Hero & Banner Settings</button>
    </form>
</section>

<!-- Section 2: Branding & Assets -->
<section class="panel cms-section" id="tab-branding" style="margin-bottom:1.5rem;display:none;">
    <div style="margin-bottom:1.2rem;">
        <p class="kicker">Brand Identity</p>
        <h3 style="margin:0;">Logos, Favicons & Promo Banners</h3>
    </div>
    <form method="post" action="<?= e(url('/admin/cms')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="branding">

        <!-- Logo Upload -->
        <div style="margin-bottom:1.25rem;padding:1rem;background:var(--color-surface-alt);border:1px solid var(--color-border);border-radius:var(--radius);">
            <label for="site_logo_file" style="margin-bottom:0.35rem;font-weight:600;">Custom Logo File Upload</label>
            <input id="site_logo_file" name="site_logo_file" type="file" accept="image/*,.svg" style="padding:0.4rem 0;">
            <p class="field-hint" style="margin:0.25rem 0 0.75rem;">Upload a transparent PNG, SVG, or WEBP file for the site logo.</p>

            <?php if ($val('site_logo_url') !== ''): ?>
                <div style="margin:0.75rem 0 0.5rem;padding:0.75rem;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <span class="vis-label">Current:</span>
                        <img src="<?= e(url($val('site_logo_url'))) ?>" alt="Site Logo Preview" style="max-height:36px;width:auto;object-fit:contain;">
                        <span class="fineprint" style="word-break:break-all;"><?= e($val('site_logo_url')) ?></span>
                    </div>
                    <label class="checkbox" style="font-size:0.85rem;color:var(--color-danger, #ef4444);margin:0;">
                        <input type="checkbox" name="remove_site_logo" value="1">
                        <span>Revert to Built-in Logo</span>
                    </label>
                </div>
            <?php endif; ?>

            <details style="margin-top:0.5rem;font-size:0.85rem;">
                <summary style="cursor:pointer;color:var(--color-text-muted);">Or enter logo image URL manually</summary>
                <div style="margin-top:0.5rem;">
                    <input id="site_logo_url" name="site_logo_url" type="text" placeholder="https://example.com/assets/logo.png" value="<?= e($val('site_logo_url')) ?>">
                </div>
            </details>
        </div>

        <!-- Favicon Upload -->
        <div style="margin-bottom:1.25rem;padding:1rem;background:var(--color-surface-alt);border:1px solid var(--color-border);border-radius:var(--radius);">
            <label for="site_favicon_file" style="margin-bottom:0.35rem;font-weight:600;">Favicon File Upload (.ico, .svg, .png)</label>
            <input id="site_favicon_file" name="site_favicon_file" type="file" accept="image/*,.ico,.svg" style="padding:0.4rem 0;">
            <p class="field-hint" style="margin:0.25rem 0 0.75rem;">Custom favicon for browser tabs and bookmarks.</p>

            <?php if ($val('site_favicon_url') !== ''): ?>
                <div style="margin:0.75rem 0 0.5rem;padding:0.75rem;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <span class="vis-label">Current:</span>
                        <img src="<?= e(url($val('site_favicon_url'))) ?>" alt="Favicon Preview" style="width:24px;height:24px;object-fit:contain;">
                        <span class="fineprint" style="word-break:break-all;"><?= e($val('site_favicon_url')) ?></span>
                    </div>
                    <label class="checkbox" style="font-size:0.85rem;color:var(--color-danger, #ef4444);margin:0;">
                        <input type="checkbox" name="remove_site_favicon" value="1">
                        <span>Revert to Default Favicon</span>
                    </label>
                </div>
            <?php endif; ?>

            <details style="margin-top:0.5rem;font-size:0.85rem;">
                <summary style="cursor:pointer;color:var(--color-text-muted);">Or enter favicon URL manually</summary>
                <div style="margin-top:0.5rem;">
                    <input id="site_favicon_url" name="site_favicon_url" type="text" placeholder="https://example.com/favicon.svg" value="<?= e($val('site_favicon_url')) ?>">
                </div>
            </details>
        </div>

        <hr style="border:0;border-top:1px solid var(--color-border);margin:1.5rem 0;">

        <div style="margin-bottom:1rem;">
            <p class="kicker">Top Notification</p>
            <h4 style="margin:0;">Promotional Header Banner</h4>
        </div>

        <label class="checkbox" style="margin-bottom:0.75rem;">
            <input type="checkbox" name="promo_banner_enabled" value="1" <?= $val('promo_banner_enabled') === '1' ? 'checked' : '' ?>>
            Enable promotional header banner across public pages
        </label>

        <div class="form-row split">
            <div>
                <label for="promo_banner_text">Promo Message Text</label>
                <input id="promo_banner_text" name="promo_banner_text" placeholder="e.g. NFL Week 1 Pass Special — 50% Off Founders Rate!" value="<?= e($val('promo_banner_text')) ?>">
            </div>
            <div>
                <label for="promo_banner_url">Promo Action URL</label>
                <input id="promo_banner_url" name="promo_banner_url" placeholder="/the-playbook" value="<?= e($val('promo_banner_url')) ?>">
            </div>
        </div>

        <button class="btn btn-primary" type="submit" style="margin-top:1rem;">Save Branding & Assets</button>
    </form>
</section>

<!-- Section 3: About Us & Marketing Blocks -->
<section class="panel cms-section" id="tab-about" style="margin-bottom:1.5rem;display:none;">
    <div style="margin-bottom:1.2rem;">
        <p class="kicker">Storytelling & Pillars</p>
        <h3 style="margin:0;">About Us & Three Pillars Copy</h3>
    </div>
    <form method="post" action="<?= e(url('/admin/cms')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="about">

        <label for="about_hero_headline">About Page Hero Headline</label>
        <input id="about_hero_headline" name="about_hero_headline" value="<?= e($val('about_hero_headline', 'Victory is found in the Margins')) ?>">

        <hr style="border:0;border-top:1px solid var(--color-border);margin:1.5rem 0;">

        <div style="margin-bottom:0.75rem;">
            <p class="kicker">Pillar 01</p>
            <h4 style="margin:0;">Who We Are (Edge & Origin)</h4>
        </div>
        <div class="form-row split">
            <div>
                <label for="story_1_eyebrow">Eyebrow</label>
                <input id="story_1_eyebrow" name="story_1_eyebrow" value="<?= e($val('story_1_eyebrow', '01 · Who we are')) ?>">
            </div>
            <div>
                <label for="story_1_title">Title</label>
                <input id="story_1_title" name="story_1_title" value="<?= e($val('story_1_title', "Built from a bettor's edge.")) ?>">
            </div>
        </div>
        <label for="story_1_body">Body Copy</label>
        <textarea id="story_1_body" name="story_1_body" rows="4"><?= e($val('story_1_body')) ?></textarea>
        <label for="story_1_scrawl">Handwritten Scrawl Accent</label>
        <input id="story_1_scrawl" name="story_1_scrawl" value="<?= e($val('story_1_scrawl', 'no favorite team. no bad nights.')) ?>">

        <hr style="border:0;border-top:1px solid var(--color-border);margin:1.5rem 0;">

        <div style="margin-bottom:0.75rem;">
            <p class="kicker">Pillar 02</p>
            <h4 style="margin:0;">Our Process (Overnight & Kickoff Delivery)</h4>
        </div>
        <div class="form-row split">
            <div>
                <label for="story_2_eyebrow">Eyebrow</label>
                <input id="story_2_eyebrow" name="story_2_eyebrow" value="<?= e($val('story_2_eyebrow', '02 · Our process')) ?>">
            </div>
            <div>
                <label for="story_2_title">Title</label>
                <input id="story_2_title" name="story_2_title" value="<?= e($val('story_2_title', 'What lands in your inbox.')) ?>">
            </div>
        </div>
        <label for="story_2_body">Body Copy</label>
        <textarea id="story_2_body" name="story_2_body" rows="4"><?= e($val('story_2_body')) ?></textarea>

        <hr style="border:0;border-top:1px solid var(--color-border);margin:1.5rem 0;">

        <div style="margin-bottom:0.75rem;">
            <p class="kicker">Pillar 03</p>
            <h4 style="margin:0;">Our Value (The 52 vs 59 Record)</h4>
        </div>
        <div class="form-row split">
            <div>
                <label for="story_3_eyebrow">Eyebrow</label>
                <input id="story_3_eyebrow" name="story_3_eyebrow" value="<?= e($val('story_3_eyebrow', '03 · Our value')) ?>">
            </div>
            <div>
                <label for="story_3_title">Title</label>
                <input id="story_3_title" name="story_3_title" value="<?= e($val('story_3_title', 'Six points is the whole game.')) ?>">
            </div>
        </div>
        <label for="story_3_body">Body Copy</label>
        <textarea id="story_3_body" name="story_3_body" rows="4"><?= e($val('story_3_body')) ?></textarea>

        <hr style="border:0;border-top:1px solid var(--color-border);margin:1.5rem 0;">

        <div style="margin-bottom:0.75rem;">
            <p class="kicker">Value Proposition Block</p>
            <h4 style="margin:0;">The House Numbers & Wall</h4>
        </div>
        <div class="form-row split">
            <div>
                <label for="valprop_title">Headline</label>
                <input id="valprop_title" name="valprop_title" value="<?= e($val('valprop_title', 'The only number the house needs.')) ?>">
            </div>
            <div>
                <label for="valprop_subtitle">Kicker Subtitle</label>
                <input id="valprop_subtitle" name="valprop_subtitle" value="<?= e($val('valprop_subtitle', 'Certainty is the oldest con in this business.')) ?>">
            </div>
        </div>
        <label for="valprop_body">Explanation</label>
        <textarea id="valprop_body" name="valprop_body" rows="2"><?= e($val('valprop_body', 'To come out even you have to win about 52 of every 100. That is the wall, and most people never hear about it.')) ?></textarea>

        <button class="btn btn-primary" type="submit" style="margin-top:1rem;">Save About Us & Marketing Copy</button>
    </form>
</section>

<!-- Section 4: Footer & Disclaimers -->
<section class="panel cms-section" id="tab-footer" style="margin-bottom:1.5rem;display:none;">
    <div style="margin-bottom:1.2rem;">
        <p class="kicker">Legal & Footers</p>
        <h3 style="margin:0;">Footer Copy & Compliance Disclaimers</h3>
    </div>
    <form method="post" action="<?= e(url('/admin/cms')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="footer">

        <label for="footer_text">Footer Mission / Tagline Blurb</label>
        <input id="footer_text" name="footer_text" value="<?= e($val('footer_text', 'Daily picks. Public record. No excuses.')) ?>">

        <label for="footer_disclaimer">Footer Compliance Disclaimer (Responsible Gaming)</label>
        <textarea id="footer_disclaimer" name="footer_disclaimer" rows="3"><?= e($val('footer_disclaimer', '21+. Informational use only, not betting advice. 1-800-GAMBLER.')) ?></textarea>

        <button class="btn btn-primary" type="submit" style="margin-top:1rem;">Save Footer & Disclaimer</button>
    </form>
</section>

<script>
(() => {
    const tabs = document.querySelectorAll('#cms-tabs a');
    const sections = document.querySelectorAll('.cms-section');

    const switchTab = (targetId) => {
        tabs.forEach(t => {
            const isMatch = t.getAttribute('data-tab-target') === targetId || t.getAttribute('href') === '#' + targetId.replace('tab-', '');
            t.classList.toggle('is-active', isMatch);
        });

        sections.forEach(s => {
            s.style.display = s.id === targetId ? 'block' : 'none';
        });
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = tab.getAttribute('data-tab-target');
            if (targetId) {
                switchTab(targetId);
                history.replaceState(null, '', tab.getAttribute('href'));
            }
        });
    });

    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById('tab-' + hash)) {
        switchTab('tab-' + hash);
    }
})();
</script>
