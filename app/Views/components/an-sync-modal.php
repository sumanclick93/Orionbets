<?php
$tickUrl = url('/admin/sync/action-network/tick');
$pauseUrl = url('/admin/sync/action-network/pause');
$statusUrl = url('/admin/sync/action-network/status');
?>
<div
    class="an-sync-modal"
    id="an-sync-modal"
    hidden
    data-csrf="<?= e(csrf_token()) ?>"
    data-tick-url="<?= e($tickUrl) ?>"
    data-pause-url="<?= e($pauseUrl) ?>"
    data-status-url="<?= e($statusUrl) ?>"
>
    <div class="an-sync-modal__scrim" data-an-sync-scrim></div>
    <div class="an-sync-modal__panel" role="dialog" aria-modal="true" aria-labelledby="an-sync-title" aria-describedby="an-sync-copy">
        <p class="an-sync-modal__kicker" data-an-sync-kicker>Action Network</p>
        <h2 class="an-sync-modal__title" id="an-sync-title">Checking for new data</h2>
        <p class="an-sync-modal__copy" id="an-sync-copy" data-an-sync-copy>Comparing Action Network with the local cache…</p>
        <div class="an-sync-modal__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-an-sync-bar>
            <span class="an-sync-modal__fill" data-an-sync-fill></span>
        </div>
        <p class="an-sync-modal__meta" data-an-sync-meta>0 done · 0 left</p>
        <div class="an-sync-modal__actions">
            <button type="button" class="btn btn-ghost" data-an-sync-pause>Pause</button>
            <button type="button" class="btn btn-primary" data-an-sync-close hidden>Close</button>
        </div>
    </div>
</div>
