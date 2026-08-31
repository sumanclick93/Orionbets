<?php
$place = array_filter([
    $location['city'] ?? null,
    $location['state'] ?? null,
    $location['country'] ?? null,
]);
?>
<p class="kicker"><?= e((string) ($kicker ?? 'Region lock')) ?></p>
<h1><?= e((string) ($title ?? 'This desk is not available here')) ?></h1>
<p><?= e((string) ($copy ?? 'Access from your region has been restricted.')) ?></p>
<?php if ($place): ?>
    <p class="geo-blocked__place"><?= e(implode(' · ', $place)) ?></p>
<?php endif; ?>
<p class="muted">HTTP 451 · Unavailable for legal reasons</p>
