<?php
$enabled = !empty($enabled);
$location = $location ?? [];
$decision = $decision ?? [];
$rules = $rules ?? [];
$place = array_filter([
    $location['city'] ?? null,
    $location['state'] ?? null,
    $location['country'] ?? null,
]);
$placeLabel = $place ? implode(', ', $place) : 'Unknown location';
$blockedNow = !empty($decision['blocked']);
?>
<div
    class="geo-admin"
    data-geo-admin
    data-csrf="<?= e(csrf_token()) ?>"
    data-enabled="<?= $enabled ? '1' : '0' ?>"
>
    <section class="panel geo-admin__hero">
        <p class="kicker">Access</p>
        <div class="geo-admin__hero-row">
            <div>
                <h2>Geo blocking</h2>
                <p class="muted admin-hint">Restrict a whole country, a whole state, or a single city. A more specific toggle wins — so you can lock the United States, allow California, then lock Los Angeles.</p>
            </div>
            <label class="geo-switch">
                <input type="checkbox" data-geo-enabled <?= $enabled ? 'checked' : '' ?>>
                <span class="geo-switch__ui" aria-hidden="true"></span>
                <span class="geo-switch__copy">
                    <strong data-geo-enabled-label><?= $enabled ? 'Blocking on' : 'Blocking off' ?></strong>
                    <em>Based on visitor IP</em>
                </span>
            </label>
        </div>
        <p class="geo-admin__catalog muted">Catalog loaded: <?= (int) $countryCount ?> countries, states, and cities from local JSON.</p>
    </section>

    <section class="panel geo-admin__now">
        <p class="kicker">This machine</p>
        <h3><?= e($placeLabel) ?></h3>
        <?php if ($blockedNow && $enabled): ?>
            <div class="alert alert-danger">
                <?= e((string) ($location['country'] ?? 'This country')) ?> is restricted<?= !empty($location['city']) ? ' — including ' . e((string) $location['city']) : '' ?>.
                The public site is locked for this IP. This admin page stays open so you can change the rule. Open the homepage in a new tab to see the block screen.
            </div>
        <?php elseif ($blockedNow && !$enabled): ?>
            <div class="alert alert-danger">A restriction matches this place, but <strong>Blocking off</strong> is still set — turn blocking on for the lock to apply.</div>
        <?php endif; ?>
        <dl class="geo-admin__meta">
            <div><dt>IP</dt><dd data-geo-ip><?= e((string) ($location['ip'] ?? '—')) ?></dd></div>
            <div><dt>Country</dt><dd data-geo-country><?= e((string) (($location['country'] ?? '—') . (!empty($location['country_code']) ? ' (' . $location['country_code'] . ')' : ''))) ?></dd></div>
            <div><dt>State</dt><dd data-geo-state><?= e((string) ($location['state'] ?? '—')) ?></dd></div>
            <div><dt>City</dt><dd data-geo-city><?= e((string) ($location['city'] ?? '—')) ?></dd></div>
            <div>
                <dt>Public site</dt>
                <dd>
                    <span class="badge <?= $blockedNow ? 'badge-lost' : 'badge-won' ?>" data-geo-verdict>
                        <?= $blockedNow ? ($enabled ? 'Blocked' : 'Would be blocked') : 'Allowed' ?>
                    </span>
                    <?php if (!empty($location['is_preview'])): ?>
                        <span class="badge badge-demo">Preview IP</span>
                    <?php elseif (!empty($location['is_egress'])): ?>
                        <span class="badge badge-demo">Public IP</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <p class="muted admin-hint">
            A city inherits its country lock. If India is restricted, Kolkata is restricted too unless you add an Allowed exception on that state or city.
        </p>
        <div class="geo-admin__preview">
            <input data-geo-preview-ip type="text" placeholder="e.g. 8.8.8.8" value="" autocomplete="off">
            <button class="btn btn-ghost" type="button" data-geo-lookup>Lookup</button>
            <a class="btn btn-primary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" data-geo-open-home>Open homepage</a>
            <button class="btn btn-ghost" type="button" data-geo-open-as>Open homepage as this IP</button>
        </div>
        <div class="geo-admin__chips" data-geo-chips>
            <button type="button" data-ip="8.8.8.8">US · 8.8.8.8</button>
            <button type="button" data-ip="49.207.5.1">India · 49.207.5.1</button>
            <button type="button" data-ip="51.140.0.1">UK · 51.140.0.1</button>
            <button type="button" data-ip="1.1.1.1">Cloudflare · 1.1.1.1</button>
        </div>
        <p class="muted admin-hint">Lookup / open-as is only a test. The live site always uses your real public IP (Kolkata on this machine), not a leftover preview cookie.</p>
    </section>

    <section class="panel">
        <p class="kicker">Locations</p>
        <h3>Toggle country, state, or city</h3>
        <input data-geo-search type="search" placeholder="Search countries" autocomplete="off">
        <p class="muted admin-hint">Open a country to search its states, then open a state to search cities. Large states are search-only so the page does not freeze.</p>
        <div class="geo-tree" data-geo-tree>
            <p class="muted">Loading countries…</p>
        </div>
    </section>

    <section class="panel">
        <p class="kicker">Rules</p>
        <h3>Active restrictions and exceptions</h3>
        <p class="muted admin-hint">Restricted blocks that place. Allowed is an exception under a locked parent.</p>
        <div data-geo-rules>
            <?php if (!$rules): ?>
                <p class="muted">No rules yet. Expand a country below and flip a switch.</p>
            <?php endif; ?>
        </div>
    </section>
</div>
