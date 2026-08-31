<?php $pick = $pick ?? []; ?>
<form method="post" class="panel" action="<?= e($pick ? url('/admin/picks/' . $pick['id'] . '/update') : url('/admin/picks')) ?>">
    <?= csrf_field() ?>
    <h2><?= $pick ? 'Override pick' : 'New pick' ?></h2>
    <?php if (!empty($pick['action_network_pick_id'])): ?>
        <p class="muted admin-hint">Synced ID <?= e((string) $pick['action_network_pick_id']) ?> stays linked. Saving marks this record as a desk override so later syncs will not wipe your line, units, or notes.</p>
    <?php endif; ?>
    <label>Title</label>
    <input name="title" required value="<?= e($pick['title'] ?? (string) old('title')) ?>">
    <label>Slug (optional)</label>
    <input name="slug" value="<?= e($pick['slug'] ?? '') ?>">
    <div class="form-row split">
        <div>
            <label>Sport</label>
            <select name="sport_id" required>
                <?php foreach ($sports as $sport): ?>
                    <option value="<?= (int) $sport['id'] ?>" <?= (int) ($pick['sport_id'] ?? 0) === (int) $sport['id'] ? 'selected' : '' ?>><?= e($sport['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>League</label>
            <select name="league_id">
                <option value="">—</option>
                <?php foreach ($leagues as $league): ?>
                    <option value="<?= (int) $league['id'] ?>" <?= (int) ($pick['league_id'] ?? 0) === (int) $league['id'] ? 'selected' : '' ?>><?= e($league['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <label>Event</label>
    <select name="event_id">
        <option value="">—</option>
        <?php foreach ($events as $event): ?>
            <option value="<?= (int) $event['id'] ?>" <?= (int) ($pick['event_id'] ?? 0) === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['name'] . ' · ' . ($event['start_time'] ?? $event['event_at'])) ?></option>
        <?php endforeach; ?>
    </select>
    <label>Matchup</label>
    <input name="matchup" value="<?= e($pick['matchup'] ?? '') ?>" placeholder="Away @ Home">
    <div class="form-row split">
        <div>
            <label>Bet type</label>
            <select name="bet_type">
                <?php foreach (['spread' => 'Spread', 'moneyline' => 'Moneyline', 'over_under' => 'Over/Under', 'prop' => 'Prop'] as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= ($pick['bet_type'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Selection / line</label>
            <input name="selection_line" value="<?= e($pick['selection_line'] ?? '') ?>" placeholder="Chiefs -3.5">
        </div>
    </div>
    <div class="form-row split">
        <div><label>Odds</label><input name="odds" value="<?= e((string) ($pick['odds'] ?? '')) ?>" placeholder="-110"></div>
        <div><label>Units</label><input name="units" value="<?= e((string) ($pick['units'] ?? $pick['result_units'] ?? '')) ?>"></div>
        <div><label>Sportsbook</label><input name="sportsbook" value="<?= e((string) ($pick['sportsbook'] ?? '')) ?>"></div>
    </div>
    <label>Analysis / notes</label>
    <textarea name="analysis"><?= e($pick['analysis'] ?? (string) old('analysis')) ?></textarea>
    <label>Key factors (one per line)</label>
    <textarea name="key_factors"><?= e(implode("\n", json_decode_array($pick['key_factors'] ?? '[]'))) ?></textarea>
    <label>Supporting stats (Label: value)</label>
    <textarea name="supporting_stats"><?php
        $lines = [];
        foreach (json_decode_array($pick['supporting_stats'] ?? '[]') as $row) {
            $lines[] = ($row['label'] ?? '') . ': ' . ($row['value'] ?? '');
        }
        echo e(implode("\n", $lines));
    ?></textarea>
    <label>Historical context</label>
    <textarea name="historical_context"><?= e($pick['historical_context'] ?? '') ?></textarea>
    <div class="form-row split">
        <div><label>Confidence</label><input type="number" min="1" max="100" name="confidence" value="<?= e((string) ($pick['confidence'] ?? 60)) ?>"></div>
        <div>
            <label>Status / grade</label>
            <select name="status">
                <?php foreach (['pending','scheduled','published','won','lost','push','canceled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($pick['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(pick_status_label($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <input type="hidden" name="is_premium" value="0">
    <input type="hidden" name="is_published" value="0">
    <input type="hidden" name="is_active" value="0">
    <label class="checkbox"><input type="checkbox" name="is_premium" value="1" <?= !empty($pick['is_premium']) || !$pick ? 'checked' : '' ?>> Premium (gated for free/guest)</label>
    <label class="checkbox"><input type="checkbox" name="is_published" value="1" <?= !isset($pick['is_published']) || !empty($pick['is_published']) ? 'checked' : '' ?>> Published</label>
    <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= !isset($pick['is_active']) || !empty($pick['is_active']) ? 'checked' : '' ?>> Visible on site</label>
    <div class="form-row split">
        <div><label>Publication date</label><input type="datetime-local" name="published_at" value="<?= e(!empty($pick['published_at']) ? date('Y-m-d\TH:i', strtotime($pick['published_at'])) : '') ?>"></div>
        <div><label>Scheduled for</label><input type="datetime-local" name="scheduled_at" value="<?= e(!empty($pick['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($pick['scheduled_at'])) : '') ?>"></div>
    </div>
    <h3>Result</h3>
    <div class="form-row split">
        <div>
            <label>Mark result</label>
            <select name="result">
                <option value="">—</option>
                <?php foreach (['won','lost','push','cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($pick['result'] ?? '') === $st ? 'selected' : '' ?>><?= e(pick_status_label($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Result units</label><input name="result_units_note" value="<?= e((string) ($pick['result_units'] ?? '')) ?>" disabled></div>
    </div>
    <label>Closing notes</label>
    <textarea name="closing_notes"><?= e($pick['closing_notes'] ?? '') ?></textarea>
    <button class="btn btn-primary" type="submit">Save override</button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/picks')) ?>">Back</a>
</form>
