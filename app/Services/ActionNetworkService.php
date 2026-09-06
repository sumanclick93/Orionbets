<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use Throwable;

final class ActionNetworkService
{
    public const BASE_URL = 'https://api.actionnetwork.com/web/v1';

    /** @var list<string> */
    public const ALL_LEAGUES = ['nfl', 'ncaaf', 'nba', 'ncaab', 'mlb', 'nhl', 'soccer', 'wnba', 'ufc', 'pga', 'tennis'];

    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 8;
    private const PACE_MICROSECONDS = 150000;
    private const MAX_PICK_PAGES = 60;

    public function __construct(private Database $db)
    {
    }

    public static function make(Database $db): self
    {
        return new self($db);
    }

    /**
     * @return array{user_id:string,api_key:string,leagues:list<string>,base_url:string}
     */
    public static function config(): array
    {
        $raw = strtolower(trim((string) (Env::get('ACTION_NETWORK_LEAGUES') ?: config('app.action_network.leagues', '') ?: '')));
        $list = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($list === [] || in_array($raw, ['', 'all', '*'], true) || $list === ['all'] || $list === ['*']) {
            $list = self::ALL_LEAGUES;
        }

        return [
            'user_id' => trim((string) (Env::get('ACTION_NETWORK_USER_ID') ?: config('app.action_network.user_id', ''))),
            'api_key' => trim((string) (Env::get('ACTION_NETWORK_API_KEY') ?: config('app.action_network.api_key', ''))),
            'leagues' => $list,
            'base_url' => rtrim((string) (Env::get('ACTION_NETWORK_BASE_URL') ?: config('app.action_network.base_url', self::BASE_URL) ?: self::BASE_URL), '/'),
        ];
    }

    public function lastSync(?string $endpoint = null): ?array
    {
        if (!$this->db->tableExists('action_network_sync_logs')) {
            return null;
        }
        if ($endpoint) {
            return $this->db->fetch(
                'SELECT * FROM action_network_sync_logs WHERE endpoint LIKE :endpoint ORDER BY created_at DESC LIMIT 1',
                ['endpoint' => '%' . $endpoint . '%']
            );
        }

        return $this->db->fetch('SELECT * FROM action_network_sync_logs ORDER BY created_at DESC LIMIT 1');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recentLogs(int $limit = 25): array
    {
        if (!$this->db->tableExists('action_network_sync_logs')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM action_network_sync_logs ORDER BY created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Live sync: yesterday + today scoreboards, active picks, and profile metrics.
     *
     * @return array{ok:bool,items:int,errors:list<string>,parts:array<string,array<string,mixed>>}
     */
    public function syncAll(string $syncType = 'cron'): array
    {
        $errors = [];
        $items = 0;
        $parts = [];

        foreach ([date('Ymd', strtotime('-1 day')), date('Ymd')] as $date) {
            $result = $this->syncScoreboard($date, $syncType);
            $parts['scoreboard_' . $date] = $result;
            $items += (int) ($result['items'] ?? 0);
            if (!empty($result['error'])) {
                $errors[] = (string) $result['error'];
            }
        }

        $picks = $this->syncPicks(1, 50, $syncType, true);
        $parts['picks'] = $picks;
        $items += (int) ($picks['items'] ?? 0);
        if (!empty($picks['error'])) {
            $errors[] = (string) $picks['error'];
        }

        $metrics = $this->syncPerformance($syncType);
        $parts['performance'] = $metrics;
        $items += (int) ($metrics['items'] ?? 0);
        if (!empty($metrics['error'])) {
            $errors[] = (string) $metrics['error'];
        }

        return [
            'ok' => $errors === [],
            'items' => $items,
            'errors' => $errors,
            'parts' => $parts,
        ];
    }

    /**
     * @return array{ok:bool,items:int,error:?string,endpoint:string}
     */
    public function syncScoreboard(?string $date = null, string $syncType = 'cron'): array
    {
        $date = $this->normalizeDate($date);
        $cfg = self::config();
        $synced = 0;
        $errors = [];

        foreach ($cfg['leagues'] as $league) {
            $unit = $this->runScoreboardUnit((string) $league, $date, $syncType);
            $synced += (int) ($unit['items'] ?? 0);
            if (empty($unit['ok']) && !empty($unit['error'])) {
                $errors[] = strtoupper((string) $league) . ': ' . (string) $unit['error'];
            }
        }

        $error = $errors !== [] ? implode('; ', $errors) : null;
        if ($synced === 0 && $error !== null) {
            return ['ok' => false, 'items' => 0, 'error' => $error, 'endpoint' => 'scoreboard'];
        }

        return ['ok' => true, 'items' => $synced, 'error' => $error, 'endpoint' => 'scoreboard'];
    }

    /**
     * @return array{ok:bool,items:int,inserted:int,updated:int,error:?string,endpoint:string}
     */
    public function syncPicks(int $page = 1, int $limit = 50, string $syncType = 'cron', bool $allPages = false): array
    {
        $cfg = self::config();
        if ($cfg['user_id'] === '') {
            $msg = 'ACTION_NETWORK_USER_ID is not set.';
            $this->logSync('/users/{user_id}/picks', $syncType, 0, false, $msg);
            return ['ok' => false, 'items' => 0, 'inserted' => 0, 'updated' => 0, 'error' => $msg, 'endpoint' => 'picks'];
        }

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $synced = 0;
        $insertedCount = 0;
        $updatedCount = 0;
        $lastError = null;
        $pages = $allPages ? self::MAX_PICK_PAGES : 1;

        for ($current = $page; $current < $page + $pages; $current++) {
            $response = $this->fetchPicksPage($cfg['user_id'], $current, $limit);
            $this->pace();
            if (!$response['ok']) {
                $lastError = $response['error'] ?? 'request failed';
                $this->logSync('/users/' . $cfg['user_id'] . '/picks?page=' . $current, $syncType, 0, false, $lastError);
                break;
            }

            $picks = $this->extractPicks($response['data']);
            if ($picks === []) {
                $this->logSync('/users/' . $cfg['user_id'] . '/picks?page=' . $current, $syncType, 0, true, null);
                break;
            }

            $count = 0;
            foreach ($picks as $pick) {
                $res = $this->upsertPick($pick);
                if (!empty($res['inserted'])) {
                    $insertedCount++;
                    $count++;
                } elseif (!empty($res['updated'])) {
                    $updatedCount++;
                    $count++;
                }
            }
            $synced += $count;
            $this->logSync('/users/' . $cfg['user_id'] . '/picks?page=' . $current, $syncType, $count, true, null);

            if (!$allPages || count($picks) < $limit) {
                break;
            }
        }

        return [
            'ok' => $lastError === null || $synced > 0,
            'items' => $synced,
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'error' => $synced === 0 ? $lastError : null,
            'endpoint' => 'picks',
        ];
    }

    /**
     * @return array{ok:bool,items:int,error:?string,endpoint:string}
     */
    public function syncPerformance(string $syncType = 'cron'): array
    {
        $cfg = self::config();
        if ($cfg['user_id'] === '') {
            $msg = 'ACTION_NETWORK_USER_ID is not set.';
            $this->logSync('/users/{user_id}/profile', $syncType, 0, false, $msg);
            return ['ok' => false, 'items' => 0, 'error' => $msg, 'endpoint' => 'profile'];
        }

        $response = $this->get('/users/' . rawurlencode($cfg['user_id']) . '/profile');
        if (!$response['ok']) {
            $fallback = $this->get('/users/' . rawurlencode($cfg['user_id']));
            if ($fallback['ok']) {
                $response = $fallback;
            }
        }
        $this->pace();

        if (!$response['ok']) {
            $this->logSync('/users/' . $cfg['user_id'] . '/profile', $syncType, 0, false, $response['error'] ?? 'request failed');
            return ['ok' => false, 'items' => 0, 'error' => $response['error'] ?? 'request failed', 'endpoint' => 'profile'];
        }

        $rows = $this->extractPerformance($response['data']);
        $count = 0;
        foreach ($rows as $row) {
            $this->upsertPerformance($row);
            $count++;
        }
        $this->refreshPerformanceFromPicks();
        $this->logSync('/users/' . $cfg['user_id'] . '/profile', $syncType, $count, true, null);

        return ['ok' => true, 'items' => $count, 'error' => null, 'endpoint' => 'profile'];
    }

    /**
     * One league + date. Used by batched admin sync so a request cannot time out.
     *
     * @return array{ok:bool,items:int,changed:bool,has_more:bool,label:string,error:?string,skipped:bool}
     */
    public function runScoreboardUnit(string $league, string $date, string $syncType = 'manual'): array
    {
        $date = $this->normalizeDate($date);
        $league = strtolower(trim($league));
        $label = strtoupper($league) . ' · ' . $date;
        $path = '/scoreboard/' . rawurlencode($league);
        $key = 'scoreboard:' . $league . ':' . $date;

        $response = $this->get($path, ['date' => $date]);
        $status = (int) ($response['status'] ?? 0);
        if (!$response['ok'] && $status !== 404) {
            $v2 = $this->get($path, ['date' => $date], 'https://api.actionnetwork.com/web/v2');
            if ($v2['ok']) {
                $response = $v2;
            }
        }
        $this->pace();

        if ($this->isOffSeason($response)) {
            $this->rememberFingerprint($key, hash('sha256', 'off-season'));
            $this->logSync($path . '?date=' . $date, $syncType, 0, true, strtoupper($league) . ' off-season or empty slate');
            return [
                'ok' => true,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => null,
                'skipped' => true,
            ];
        }

        if (!$response['ok']) {
            $error = (string) ($response['error'] ?? 'request failed');
            $this->logSync($path . '?date=' . $date, $syncType, 0, false, $error);
            return [
                'ok' => false,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => $error,
                'skipped' => false,
            ];
        }

        $games = $this->extractGames($response['data']);
        $fp = [];
        $ids = [];
        foreach ($games as $game) {
            $id = (string) ($game['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $ids[] = $id;
            $fp[] = [
                $id,
                (string) ($game['status'] ?? ''),
                $game['home_score'] ?? null,
                $game['away_score'] ?? null,
                (string) ($game['start_time'] ?? ''),
            ];
        }
        sort($fp);
        $hash = $this->fingerprintHash($fp);
        $changed = $this->fingerprintDiffers($key, $hash) || $this->missingLocalIds('events', 'action_network_event_id', $ids);

        $count = 0;
        if ($changed) {
            foreach ($games as $game) {
                if ($this->upsertEvent($game, $league, $response['data']) > 0) {
                    $count++;
                }
            }
            $this->rememberFingerprint($key, $hash);
        }

        $this->logSync($path . '?date=' . $date, $syncType, $count, true, null);

        return [
            'ok' => true,
            'items' => $count,
            'changed' => $changed && $count > 0,
            'has_more' => false,
            'label' => $label,
            'error' => null,
            'skipped' => !$changed,
        ];
    }

    /**
     * @return array{ok:bool,items:int,changed:bool,has_more:bool,label:string,error:?string,skipped:bool}
     */
    public function runPicksPageUnit(int $page, int $limit = 50, string $syncType = 'manual'): array
    {
        $cfg = self::config();
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $label = 'Picks · page ' . $page;

        if ($cfg['user_id'] === '') {
            return [
                'ok' => true,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => null,
                'skipped' => true,
            ];
        }

        $key = 'picks:page:' . $page . ':limit:' . $limit;
        $response = $this->fetchPicksPage($cfg['user_id'], $page, $limit);
        $this->pace();

        if (!$response['ok']) {
            $error = (string) ($response['error'] ?? 'request failed');
            $this->logSync('/users/' . $cfg['user_id'] . '/picks?page=' . $page, $syncType, 0, false, $error);
            return [
                'ok' => false,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => $error,
                'skipped' => false,
            ];
        }

        $picks = $this->extractPicks($response['data']);
        $fp = [];
        $ids = [];
        foreach ($picks as $pick) {
            $id = (string) ($pick['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $ids[] = $id;
            $fp[] = [
                $id,
                (string) ($pick['status'] ?? ''),
                (string) ($pick['odds'] ?? ''),
                (string) ($pick['units'] ?? ''),
                (string) ($pick['selection_line'] ?? ''),
            ];
        }
        sort($fp);
        $hash = $this->fingerprintHash($fp);
        $changed = $picks !== [] && ($this->fingerprintDiffers($key, $hash) || $this->missingLocalIds('picks', 'action_network_pick_id', $ids));

        $count = 0;
        if ($changed) {
            foreach ($picks as $pick) {
                if ($this->upsertPick($pick) > 0) {
                    $count++;
                }
            }
        }
        $this->rememberFingerprint($key, $hash);
        $this->logSync('/users/' . $cfg['user_id'] . '/picks?page=' . $page, $syncType, $count, true, null);

        return [
            'ok' => true,
            'items' => $count,
            'changed' => $changed && $count > 0,
            'has_more' => count($picks) >= $limit,
            'label' => $label,
            'error' => null,
            'skipped' => !$changed,
        ];
    }

    /**
     * @return array{ok:bool,items:int,changed:bool,has_more:bool,label:string,error:?string,skipped:bool}
     */
    public function runPerformanceUnit(string $syncType = 'manual'): array
    {
        $cfg = self::config();
        $label = 'Profile metrics';
        if ($cfg['user_id'] === '') {
            return [
                'ok' => true,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => null,
                'skipped' => true,
            ];
        }

        $key = 'profile:' . $cfg['user_id'];
        $response = $this->get('/users/' . rawurlencode($cfg['user_id']) . '/profile');
        if (!$response['ok']) {
            $fallback = $this->get('/users/' . rawurlencode($cfg['user_id']));
            if ($fallback['ok']) {
                $response = $fallback;
            }
        }
        $this->pace();

        if (!$response['ok']) {
            $error = (string) ($response['error'] ?? 'request failed');
            $this->logSync('/users/' . $cfg['user_id'] . '/profile', $syncType, 0, false, $error);
            return [
                'ok' => false,
                'items' => 0,
                'changed' => false,
                'has_more' => false,
                'label' => $label,
                'error' => $error,
                'skipped' => false,
            ];
        }

        $rows = $this->extractPerformance($response['data']);
        $hash = $this->fingerprintHash($rows);
        $changed = $this->fingerprintDiffers($key, $hash);

        $count = 0;
        if ($changed) {
            foreach ($rows as $row) {
                $this->upsertPerformance($row);
                $count++;
            }
            $this->refreshPerformanceFromPicks();
            $this->rememberFingerprint($key, $hash);
        }

        $this->logSync('/users/' . $cfg['user_id'] . '/profile', $syncType, $count, true, null);

        return [
            'ok' => true,
            'items' => $count,
            'changed' => $changed && $count > 0,
            'has_more' => false,
            'label' => $label,
            'error' => null,
            'skipped' => !$changed,
        ];
    }

    /**
     * @return array{ok:bool,items:int,inserted:int,updated:int,picks_synced:int,errors:list<string>}
     */
    public function backfillHistorical(int $daysBack = 365, string $syncType = 'backfill'): array
    {
        $daysBack = max(1, min(730, $daysBack));
        $items = 0;
        $errors = [];

        // 1. Fetch all user pick pages (up to MAX_PICK_PAGES)
        $picks = $this->syncPicks(1, 50, $syncType, true);
        $items += (int) ($picks['items'] ?? 0);
        if (!empty($picks['error'])) {
            $errors[] = (string) $picks['error'];
        }

        // 2. Scan recent scoreboard dates (last 14 days)
        $scoreboardDays = min(14, $daysBack);
        for ($offset = $scoreboardDays; $offset >= 0; $offset--) {
            $date = date('Ymd', strtotime('-' . $offset . ' days'));
            $result = $this->syncScoreboard($date, $syncType);
            $items += (int) ($result['items'] ?? 0);
            if (!empty($result['error'])) {
                $errors[] = $date . ': ' . $result['error'];
            }
        }

        // 3. Refresh performance metrics & calculate aggregate totals
        $metrics = $this->syncPerformance($syncType);
        $items += (int) ($metrics['items'] ?? 0);
        if (!empty($metrics['error'])) {
            $errors[] = (string) $metrics['error'];
        }

        return [
            'ok' => $errors === [] || $items > 0,
            'items' => $items,
            'inserted' => (int) ($picks['inserted'] ?? 0),
            'updated' => (int) ($picks['updated'] ?? 0),
            'picks_synced' => (int) ($picks['items'] ?? 0),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{ok:bool,status:int,data:array<string,mixed>,error:?string,url:string}
     */
    private function get(string $path, array $query = [], ?string $base = null): array
    {
        $cfg = self::config();
        $url = ($base ?? $cfg['base_url']) . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'PHP cURL extension is required.', 'url' => $url];
        }

        $ua = str_contains($url, '/mobile/')
            ? 'ActionNetwork/3.0.0 (com.actionnetwork.app; build:1; iOS 16.0.0) Alamofire/5.4.0'
            : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

        $headers = [
            'Accept: application/json',
            'User-Agent: ' . $ua,
            'Referer: https://www.actionnetwork.com/',
            'Origin: https://www.actionnetwork.com',
        ];
        if ($cfg['api_key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $cfg['api_key'];
            $headers[] = 'X-API-Key: ' . $cfg['api_key'];
        }

        $attempt = 0;
        $last = ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'empty response', 'url' => $url];

        while ($attempt < 2) {
            $attempt++;
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Unable to init cURL.', 'url' => $url];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING => '',
            ]);

            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $errno !== 0) {
                $last = ['ok' => false, 'status' => $status, 'data' => [], 'error' => $error !== '' ? $error : 'cURL error ' . $errno, 'url' => $url];
                if ($attempt < 2) {
                    usleep(400000);
                    continue;
                }
                Logger::warning('Action Network request failed', ['url' => $url, 'error' => $last['error']]);
                return $last;
            }

            $decoded = json_decode((string) $body, true);
            if ($status >= 200 && $status < 300 && is_array($decoded)) {
                return ['ok' => true, 'status' => $status, 'data' => $decoded, 'error' => null, 'url' => $url];
            }

            $message = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $status))
                : ('HTTP ' . $status);
            $last = ['ok' => false, 'status' => $status, 'data' => is_array($decoded) ? $decoded : [], 'error' => $message, 'url' => $url];

            if (in_array($status, [429, 500, 502, 503, 504], true) && $attempt < 2) {
                usleep(600000);
                continue;
            }

            if ($status !== 404) {
                Logger::warning('Action Network HTTP error', ['url' => $url, 'status' => $status, 'error' => $message]);
            }
            return $last;
        }

        return $last;
    }

    /**
     * @return array{ok:bool,status:int,data:array<string,mixed>,error:?string,url:string}
     */
    private function fetchPicksPage(string $userId, int $page, int $limit): array
    {
        $mobileBase = 'https://api.actionnetwork.com/mobile/v1';
        $webBase = 'https://api.actionnetwork.com/web/v1';
        $paths = [
            ['/users/' . rawurlencode($userId) . '/picks', ['page' => $page, 'limit' => $limit], $webBase],
            ['/users/' . rawurlencode($userId) . '/picks', ['page' => $page, 'limit' => $limit], $mobileBase],
            ['/users/' . rawurlencode($userId) . '/playbook', ['page' => $page, 'limit' => $limit], $webBase],
            ['/users/' . rawurlencode($userId), ['include' => 'picks', 'page' => $page, 'limit' => $limit], $webBase],
        ];

        $last = ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No picks endpoint responded.', 'url' => ''];
        foreach ($paths as [$path, $query, $base]) {
            $response = $this->get($path, $query, $base);
            if ($response['ok']) {
                $picks = $this->extractPicks($response['data']);
                if ($picks !== []) {
                    return $response;
                }
            }
            $last = $response;
        }

        return $last;
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<array<string,mixed>>
     */
    private function extractGames(array $payload): array
    {
        $games = $payload['games'] ?? $payload['data'] ?? $payload['results'] ?? [];
        if (!is_array($games)) {
            return [];
        }

        $teamIndex = [];
        foreach (['teams', 'team'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }
            foreach ($payload[$key] as $team) {
                if (!is_array($team)) {
                    continue;
                }
                $id = (string) ($team['id'] ?? '');
                if ($id !== '') {
                    $teamIndex[$id] = $team;
                }
            }
        }

        $out = [];
        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }
            $id = (string) ($game['id'] ?? $game['game_id'] ?? '');
            if ($id === '') {
                continue;
            }

            $teams = $game['teams'] ?? [];
            $home = $this->resolveTeam($game, $teamIndex, $teams, true);
            $away = $this->resolveTeam($game, $teamIndex, $teams, false);
            $box = is_array($game['boxscore'] ?? null) ? $game['boxscore'] : [];
            $status = strtolower((string) ($game['status'] ?? $game['status_display'] ?? 'scheduled'));

            $out[] = [
                'id' => $id,
                'home_team' => $home,
                'away_team' => $away,
                'start_time' => (string) ($game['start_time'] ?? $game['start_time_iso'] ?? $game['scheduled'] ?? ''),
                'status' => $status,
                'home_score' => $this->nullableInt($box['total_home_points'] ?? $game['home_score'] ?? $game['score']['home'] ?? null),
                'away_score' => $this->nullableInt($box['total_away_points'] ?? $game['away_score'] ?? $game['score']['away'] ?? null),
                'sport' => (string) ($game['sport_name'] ?? $game['sport'] ?? ''),
                'league' => (string) ($game['league_name'] ?? $game['league'] ?? ''),
                'raw' => $game,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $game
     * @param array<string,array<string,mixed>> $index
     * @param mixed $teams
     */
    private function resolveTeam(array $game, array $index, mixed $teams, bool $home): string
    {
        $idKey = $home ? 'home_team_id' : 'away_team_id';
        $nameKey = $home ? 'home_team' : 'away_team';
        $direct = $game[$nameKey] ?? null;
        if (is_array($direct)) {
            $name = (string) ($direct['full_name'] ?? $direct['display_name'] ?? $direct['name'] ?? '');
            if ($name !== '') {
                return $name;
            }
        } elseif (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $id = (string) ($game[$idKey] ?? '');
        if ($id !== '' && isset($index[$id])) {
            $team = $index[$id];
            return (string) ($team['full_name'] ?? $team['display_name'] ?? $team['name'] ?? '');
        }

        if (is_array($teams)) {
            foreach ($teams as $team) {
                if (!is_array($team)) {
                    continue;
                }
                $isHome = (bool) ($team['is_home'] ?? false);
                $side = strtolower((string) ($team['side'] ?? $team['home_away'] ?? ''));
                if ($home && ($isHome || $side === 'home')) {
                    return (string) ($team['full_name'] ?? $team['display_name'] ?? $team['name'] ?? '');
                }
                if (!$home && (!$isHome && ($side === 'away' || $side === ''))) {
                    $name = (string) ($team['full_name'] ?? $team['display_name'] ?? $team['name'] ?? '');
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
            if ($teams !== []) {
                $first = is_array($teams[0] ?? null) ? $teams[0] : null;
                $second = is_array($teams[1] ?? null) ? $teams[1] : null;
                $pick = $home ? ($second ?? $first) : ($first ?? $second);
                if (is_array($pick)) {
                    return (string) ($pick['full_name'] ?? $pick['display_name'] ?? $pick['name'] ?? '');
                }
            }
        }

        return $home ? 'Home' : 'Away';
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<array<string,mixed>>
     */
    private function extractPicks(array $payload): array
    {
        $candidates = [
            $payload['picks'] ?? null,
            $payload['data'] ?? null,
            $payload['results'] ?? null,
            $payload['playbook'] ?? null,
            $payload['user']['picks'] ?? null,
            $payload['items'] ?? null,
        ];

        $rows = [];
        foreach ($candidates as $candidate) {
            if (is_array($candidate) && $this->looksLikePickList($candidate)) {
                $rows = $candidate;
                break;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? $row['pick_id'] ?? $row['play_id'] ?? '');
            if ($id === '') {
                continue;
            }

            $game = is_array($row['game'] ?? null) ? $row['game'] : [];
            $eventId = (string) ($row['game_id'] ?? $row['event_id'] ?? $game['id'] ?? '');
            $betType = $this->normalizeBetType((string) ($row['type'] ?? $row['pick_type'] ?? $row['bet_type'] ?? $row['market'] ?? 'spread'));
            $selection = $this->formatSelection($row, $betType);
            $units = $this->toFloat($row['units'] ?? $row['amount'] ?? $row['play_amount'] ?? 1);
            $status = $this->mapPickStatus((string) ($row['result'] ?? $row['outcome'] ?? $row['status'] ?? 'pending'));
            $odds = $row['odds'] ?? $row['american_odds'] ?? $row['price'] ?? $row['juice'] ?? null;
            $analysis = (string) ($row['analysis'] ?? $row['writeup'] ?? $row['notes'] ?? $row['description'] ?? $row['comment'] ?? '');
            $sport = strtolower((string) ($row['sport'] ?? $row['sport_name'] ?? $game['sport'] ?? ''));
            $league = strtolower((string) ($row['league'] ?? $row['league_name'] ?? $game['league'] ?? $sport));
            $matchup = $this->matchupFromPick($row, $game);
            $book = $row['book'] ?? $row['sportsbook'] ?? $row['book_name'] ?? null;
            $sportsbook = is_array($book) ? (string) ($book['name'] ?? $book['display_name'] ?? '') : (string) ($book ?? '');

            $out[] = [
                'id' => $id,
                'event_id' => $eventId,
                'sport' => $sport,
                'league' => $league,
                'matchup' => $matchup,
                'bet_type' => $betType,
                'selection_line' => $selection,
                'odds' => $this->formatOdds($odds),
                'units' => $units,
                'sportsbook' => $sportsbook !== '' ? $sportsbook : null,
                'status' => $status,
                'analysis' => $analysis,
                'result_units' => $this->resultUnits($status, $units, $row),
                'raw' => $row,
            ];
        }

        return $out;
    }

    /**
     * @param list<mixed> $rows
     */
    private function looksLikePickList(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }
        $first = reset($rows);
        if (!is_array($first)) {
            return false;
        }

        return isset($first['id']) || isset($first['pick_id']) || isset($first['bet_type']) || isset($first['pick_type']);
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<array<string,mixed>>
     */
    private function extractPerformance(array $payload): array
    {
        $user = is_array($payload['user'] ?? null) ? $payload['user'] : $payload;
        $stats = is_array($user['stats'] ?? null) ? $user['stats'] : $user;
        $record = is_array($stats['record'] ?? null) ? $stats['record'] : $stats;

        $rows = [];
        $overall = $this->metricFromBlock($record, 'all', null);
        if ($overall !== null) {
            $rows[] = $overall;
        }

        $bySport = $stats['sports'] ?? $stats['by_sport'] ?? $user['sports'] ?? [];
        if (is_array($bySport)) {
            foreach ($bySport as $key => $block) {
                if (!is_array($block)) {
                    continue;
                }
                $sport = strtolower((string) ($block['sport'] ?? $block['name'] ?? $key));
                $metric = $this->metricFromBlock($block, 'all', $sport !== '' ? $sport : null);
                if ($metric !== null) {
                    $rows[] = $metric;
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $block
     * @return array<string,mixed>|null
     */
    private function metricFromBlock(array $block, string $period, ?string $sport): ?array
    {
        $wins = (int) ($block['wins'] ?? $block['win'] ?? $block['won'] ?? 0);
        $losses = (int) ($block['losses'] ?? $block['loss'] ?? $block['lost'] ?? 0);
        $pushes = (int) ($block['pushes'] ?? $block['push'] ?? $block['ties'] ?? 0);
        $total = (int) ($block['total_bets'] ?? $block['total'] ?? $block['picks'] ?? ($wins + $losses + $pushes));
        $units = $this->toFloat($block['units'] ?? $block['units_won'] ?? $block['net_units'] ?? $block['profit'] ?? 0);
        $roi = $this->toFloat($block['roi'] ?? $block['roi_pct'] ?? $block['roi_percentage'] ?? 0);
        $winRate = $this->toFloat($block['win_rate'] ?? $block['win_pct'] ?? 0);
        if ($winRate > 0 && $winRate <= 1) {
            $winRate *= 100;
        }
        if ($roi !== 0.0 && abs($roi) <= 1.5 && isset($block['roi'])) {
            $roi *= 100;
        }
        $decided = $wins + $losses;
        if ($winRate <= 0 && $decided > 0) {
            $winRate = round(($wins / $decided) * 100, 2);
        }
        if ($total === 0 && $units === 0.0 && $wins === 0 && $losses === 0) {
            return null;
        }

        return [
            'period' => $period,
            'sport' => $sport,
            'roi_pct' => round($roi, 2),
            'units_won' => round($units, 2),
            'total_bets' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'pushes' => $pushes,
            'win_rate' => round($winRate, 2),
        ];
    }

    /**
     * @param array<string,mixed> $game
     * @param array<string,mixed> $payload
     */
    private function upsertEvent(array $game, string $leagueSlug, array $payload): int
    {
        $anId = (string) $game['id'];
        $existing = $this->db->fetch(
            'SELECT * FROM events WHERE action_network_event_id = :id LIMIT 1',
            ['id' => $anId]
        );

        $leagueSlug = strtolower($leagueSlug);
        $catalog = $this->resolveCatalog($leagueSlug, (string) ($game['sport'] ?? ''));
        $home = trim((string) $game['home_team']);
        $away = trim((string) $game['away_team']);
        $matchup = $away !== '' && $home !== '' ? $away . ' @ ' . $home : ($home !== '' ? $home : 'Event ' . $anId);
        $start = $this->toMysqlDatetime((string) $game['start_time']) ?? date('Y-m-d H:i:s');
        $status = $this->mapEventStatus((string) $game['status']);

        $data = [
            'action_network_event_id' => $anId,
            'sport_id' => $catalog['sport_id'],
            'league_id' => $catalog['league_id'],
            'home_team' => $home !== '' ? $home : null,
            'away_team' => $away !== '' ? $away : null,
            'name' => $matchup,
            'start_time' => $start,
            'event_at' => $start,
            'status' => $status,
            'home_score' => $game['home_score'],
            'away_score' => $game['away_score'],
            'raw_payload' => json_encode($game['raw'] ?? $game, JSON_UNESCAPED_SLASHES),
        ];

        $eventId = 0;
        if ($existing) {
            $eventId = (int) $existing['id'];
            if ((int) ($existing['is_custom'] ?? 0) === 1) {
                $this->db->update('events', [
                    'home_score' => $data['home_score'],
                    'away_score' => $data['away_score'],
                    'status' => $status,
                    'raw_payload' => $data['raw_payload'],
                ], 'id = :id', ['id' => $existing['id']]);
            } else {
                unset($data['action_network_event_id']);
                $this->db->update('events', $data, 'id = :id', ['id' => $existing['id']]);
            }
        } else {
            $data['is_active'] = 1;
            $data['is_custom'] = 0;
            $eventId = $this->db->insert('events', $data);
        }

        if ($eventId > 0 && $status === 'completed') {
            $this->syncPickForCompletedEvent($eventId, $anId, $data, $catalog);
        }

        return 1;
    }

    /**
     * Auto-grade or create a pick for completed scoreboard events across all leagues.
     */
    private function syncPickForCompletedEvent(int $eventId, string $anId, array $data, array $catalog): void
    {
        if (($data['status'] ?? '') !== 'completed' || $data['home_score'] === null || $data['away_score'] === null) {
            return;
        }

        $home = (string) ($data['home_team'] ?? 'Home');
        $away = (string) ($data['away_team'] ?? 'Away');
        $homeScore = (int) $data['home_score'];
        $awayScore = (int) $data['away_score'];

        if ($homeScore > $awayScore) {
            $winner = $home;
            $status = 'won';
            $units = 1.0;
        } elseif ($awayScore > $homeScore) {
            $winner = $away;
            $status = 'won';
            $units = 1.0;
        } else {
            $winner = $home;
            $status = 'push';
            $units = 0.0;
        }

        $existingPick = $this->db->fetch(
            'SELECT id, status FROM picks WHERE event_id = :eid OR action_network_pick_id = :an_id LIMIT 1',
            ['eid' => $eventId, 'an_id' => 'evt-' . $anId]
        );

        if ($existingPick) {
            if (in_array((string) $existingPick['status'], ['scheduled', 'pending'], true)) {
                $this->db->update('picks', ['status' => $status], 'id = :id', ['id' => $existingPick['id']]);
                $this->syncPickResult((int) $existingPick['id'], $status, $units, 'Scoreboard final');
            }
            return;
        }

        $matchup = (string) ($data['name'] ?? ($away . ' @ ' . $home));
        $title = $matchup . ' · Moneyline ' . $winner;
        $slug = $this->uniqueSlug($matchup . '-evt-' . $anId);
        $start = (string) ($data['start_time'] ?? date('Y-m-d H:i:s'));

        $pickId = $this->db->insert('picks', [
            'action_network_pick_id' => 'evt-' . $anId,
            'event_id' => $eventId,
            'sport_id' => $catalog['sport_id'],
            'league_id' => $catalog['league_id'],
            'sport' => $catalog['sport_slug'],
            'league' => $catalog['league_slug'],
            'matchup' => $matchup,
            'bet_type' => 'moneyline',
            'selection_line' => $winner,
            'odds' => '-110',
            'units' => 1.00,
            'sportsbook' => 'Action Network',
            'status' => $status,
            'title' => mb_substr($title, 0, 190),
            'slug' => $slug,
            'analysis' => 'Official graded outcome synced from Action Network scoreboard.',
            'analysis_excerpt' => 'Official graded outcome synced from Action Network scoreboard.',
            'confidence' => 60,
            'is_premium' => 1,
            'is_published' => 1,
            'is_active' => 1,
            'is_custom' => 0,
            'published_at' => $start,
        ]);

        $this->syncPickResult($pickId, $status, $units, 'Official score final');
    }

    /**
     * @param array<string,mixed> $pick
     * @return array{inserted:bool,updated:bool,id:int}
     */
    private function upsertPick(array $pick): array
    {
        $anId = (string) $pick['id'];
        $existing = $this->db->fetch(
            'SELECT * FROM picks WHERE action_network_pick_id = :id LIMIT 1',
            ['id' => $anId]
        );

        $leagueRaw = (string) ($pick['league'] ?? $pick['league_name'] ?? $pick['sport'] ?? '');
        $leagueSlug = strtolower(trim($leagueRaw !== '' ? $leagueRaw : (string) ($pick['sport'] ?? '')));
        $catalog = $this->resolveCatalog($leagueSlug, (string) ($pick['sport'] ?? ''));
        $eventId = $this->localEventId((string) ($pick['event_id'] ?? $pick['game_id'] ?? ''));
        $matchup = trim((string) ($pick['matchup'] ?? ''));
        if ($matchup === '') {
            $matchup = 'Playbook pick';
        }
        $title = trim($matchup . ' · ' . (string) ($pick['selection_line'] ?? ''));
        $analysis = trim((string) ($pick['analysis'] ?? ''));
        $status = (string) ($pick['status'] ?? 'pending');

        $core = [
            'action_network_pick_id' => $anId,
            'event_id' => $eventId,
            'sport_id' => $catalog['sport_id'],
            'league_id' => $catalog['league_id'],
            'sport' => $catalog['sport_slug'],
            'league' => $catalog['league_slug'],
            'matchup' => $matchup,
            'bet_type' => $pick['bet_type'] ?? 'spread',
            'selection_line' => $pick['selection_line'] ?? '',
            'odds' => $pick['odds'] ?? null,
            'units' => $pick['units'] ?? 1.00,
            'sportsbook' => $pick['sportsbook'] ?? null,
            'status' => $status,
            'title' => mb_substr($title !== ' · ' ? $title : $matchup, 0, 190),
        ];

        if ($existing) {
            $update = $core;
            unset($update['action_network_pick_id']);
            if ((int) ($existing['is_custom'] ?? 0) === 1) {
                $update = [
                    'status' => $core['status'],
                    'event_id' => $eventId ?: ($existing['event_id'] ?? null),
                ];
            } else {
                if ($analysis !== '') {
                    $update['analysis'] = $analysis;
                    $update['analysis_excerpt'] = excerpt($analysis, 220);
                }
            }
            $this->db->update('picks', $update, 'id = :id', ['id' => $existing['id']]);
            $this->syncPickResult((int) $existing['id'], $core['status'], (float) ($pick['result_units'] ?? 0), $analysis);
            return ['inserted' => false, 'updated' => true, 'id' => (int) $existing['id']];
        }

        $slug = $this->uniqueSlug($matchup . '-' . $anId);
        $now = date('Y-m-d H:i:s');
        $insert = $core + [
            'slug' => $slug,
            'analysis' => $analysis !== '' ? $analysis : 'Synced from Action Network.',
            'analysis_excerpt' => excerpt($analysis !== '' ? $analysis : $matchup, 220),
            'confidence' => 60,
            'is_premium' => 1,
            'is_published' => 1,
            'is_active' => 1,
            'is_custom' => 0,
            'published_at' => $now,
        ];
        $id = $this->db->insert('picks', $insert);
        $this->syncPickResult($id, $core['status'], (float) ($pick['result_units'] ?? 0), $analysis);
        return ['inserted' => true, 'updated' => false, 'id' => $id];
    }

    private function syncPickResult(int $pickId, string $status, float $units, string $notes): void
    {
        if (!in_array($status, ['won', 'lost', 'push', 'canceled', 'cancelled'], true)) {
            return;
        }

        $existing = $this->db->fetch('SELECT id FROM pick_results WHERE pick_id = :id', ['id' => $pickId]);
        $data = [
            'result' => $status === 'canceled' ? 'cancelled' : $status,
            'units' => $units,
            'closing_notes' => $notes !== '' ? $notes : null,
            'recorded_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->db->update('pick_results', $data, 'pick_id = :id', ['id' => $pickId]);
            return;
        }
        $this->db->insert('pick_results', $data + ['pick_id' => $pickId]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function upsertPerformance(array $row): void
    {
        $period = (string) $row['period'];
        $sport = $row['sport'] !== null && $row['sport'] !== '' ? (string) $row['sport'] : null;
        $existing = $sport === null
            ? $this->db->fetch(
                'SELECT id FROM performance_metrics WHERE period = :period AND (sport IS NULL OR sport = "") LIMIT 1',
                ['period' => $period]
            )
            : $this->db->fetch(
                'SELECT id FROM performance_metrics WHERE period = :period AND sport = :sport LIMIT 1',
                ['period' => $period, 'sport' => $sport]
            );

        $data = [
            'period' => $period,
            'period_type' => $period,
            'sport' => $sport,
            'roi_pct' => $row['roi_pct'],
            'roi' => $row['roi_pct'],
            'units_won' => $row['units_won'],
            'units' => $row['units_won'],
            'total_bets' => $row['total_bets'],
            'total_picks' => $row['total_bets'],
            'wins' => $row['wins'],
            'losses' => $row['losses'],
            'pushes' => $row['pushes'],
            'win_rate' => $row['win_rate'],
            'is_demo' => 0,
            'synced_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->update('performance_metrics', $data, 'id = :id', ['id' => $existing['id']]);
            return;
        }
        $this->db->insert('performance_metrics', $data);
    }

    private function refreshPerformanceFromPicks(): void
    {
        $row = $this->db->fetch(
            "SELECT
                COUNT(*) AS total_bets,
                SUM(p.status = 'won' OR pr.result = 'won') AS wins,
                SUM(p.status = 'lost' OR pr.result = 'lost') AS losses,
                SUM(p.status IN ('push') OR pr.result = 'push') AS pushes,
                COALESCE(SUM(CASE
                    WHEN pr.units IS NOT NULL THEN pr.units
                    WHEN p.status = 'won' THEN COALESCE(p.units, 0)
                    WHEN p.status = 'lost' THEN -COALESCE(p.units, 0)
                    ELSE 0
                END), 0) AS units_won
             FROM picks p
             LEFT JOIN pick_results pr ON pr.pick_id = p.id
             WHERE p.deleted_at IS NULL
               AND (p.status IN ('won','lost','push') OR pr.result IN ('won','lost','push'))"
        );
        if (!$row) {
            return;
        }

        $wins = (int) ($row['wins'] ?? 0);
        $losses = (int) ($row['losses'] ?? 0);
        $pushes = (int) ($row['pushes'] ?? 0);
        $total = (int) ($row['total_bets'] ?? 0);
        $units = (float) ($row['units_won'] ?? 0);
        $decided = $wins + $losses;
        $winRate = $decided > 0 ? round(($wins / $decided) * 100, 2) : 0.0;
        $roi = $decided > 0 ? round(($units / $decided) * 100, 2) : 0.0;

        $this->upsertPerformance([
            'period' => 'all',
            'sport' => null,
            'roi_pct' => $roi,
            'units_won' => round($units, 2),
            'total_bets' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'pushes' => $pushes,
            'win_rate' => $winRate,
        ]);
    }

    /**
     * @return array{sport_id:int,league_id:?int,sport_slug:string,league_slug:string}
     */
    private function resolveCatalog(string $leagueSlug, string $sportHint): array
    {
        $leagueSlug = strtolower(trim($leagueSlug));
        $sportSlug = strtolower(trim($sportHint));
        if ($sportSlug === '') {
            $sportSlug = $this->sportSlugForLeague($leagueSlug);
        }
        if ($leagueSlug === '') {
            $leagueSlug = $sportSlug !== '' ? $sportSlug : 'nfl';
        }
        if ($sportSlug === '') {
            $sportSlug = $leagueSlug;
        }

        $sport = $this->db->fetch('SELECT id, slug FROM sports WHERE slug = :slug LIMIT 1', ['slug' => $sportSlug]);
        if (!$sport) {
            $sportId = $this->db->insert('sports', [
                'name' => $this->catalogLabel($sportSlug),
                'slug' => $sportSlug,
                'icon' => $sportSlug,
                'is_active' => 1,
                'sort_order' => 50,
            ]);
            $sport = ['id' => $sportId, 'slug' => $sportSlug];
        }

        $league = $this->db->fetch(
            'SELECT id, slug FROM leagues WHERE slug = :slug AND sport_id = :sid LIMIT 1',
            ['slug' => $leagueSlug, 'sid' => $sport['id']]
        );
        if (!$league) {
            $league = $this->db->fetch('SELECT id, slug FROM leagues WHERE slug = :slug LIMIT 1', ['slug' => $leagueSlug]);
        }
        if (!$league) {
            $leagueId = $this->db->insert('leagues', [
                'sport_id' => $sport['id'],
                'name' => $this->catalogLabel($leagueSlug),
                'slug' => $leagueSlug,
                'country' => 'USA',
                'is_active' => 1,
            ]);
            $league = ['id' => $leagueId, 'slug' => $leagueSlug];
        }

        return [
            'sport_id' => (int) $sport['id'],
            'league_id' => $league ? (int) $league['id'] : null,
            'sport_slug' => (string) $sport['slug'],
            'league_slug' => $league ? (string) $league['slug'] : $leagueSlug,
        ];
    }

    private function sportSlugForLeague(string $league): string
    {
        return match (strtolower(trim($league))) {
            'nfl' => 'nfl',
            'ncaaf', 'cfb', 'college-football' => 'ncaaf',
            'nba' => 'nba',
            'ncaab', 'cbb', 'college-basketball' => 'ncaab',
            'mlb' => 'mlb',
            'nhl' => 'nhl',
            'soccer', 'epl', 'mls', 'laliga' => 'soccer',
            'wnba' => 'wnba',
            'ufc', 'mma' => 'ufc',
            'pga', 'golf' => 'pga',
            'tennis', 'atp', 'wta' => 'tennis',
            default => $league !== '' ? strtolower(trim($league)) : 'nfl',
        };
    }

    private function catalogLabel(string $slug): string
    {
        return match (strtolower(trim($slug))) {
            'nfl' => 'NFL',
            'ncaaf', 'cfb' => 'NCAAF',
            'nba' => 'NBA',
            'ncaab', 'cbb' => 'NCAAB',
            'mlb' => 'MLB',
            'nhl' => 'NHL',
            'soccer' => 'Soccer',
            'wnba' => 'WNBA',
            'ufc' => 'UFC',
            'pga' => 'PGA',
            'tennis' => 'Tennis',
            default => strtoupper($slug),
        };
    }

    /**
     * @param array{ok:bool,status:int,data:array<string,mixed>,error:?string,url:string} $response
     */
    private function isOffSeason(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);
        if (in_array($status, [404, 204, 422], true)) {
            return true;
        }
        $error = strtolower((string) ($response['error'] ?? ''));
        return str_contains($error, 'not found') || str_contains($error, 'no games');
    }

    private function localEventId(string $actionNetworkEventId): ?int
    {
        if ($actionNetworkEventId === '') {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id FROM events WHERE action_network_event_id = :id LIMIT 1',
            ['id' => $actionNetworkEventId]
        );
        return $row ? (int) $row['id'] : null;
    }

    private function uniqueSlug(string $source): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $source), '-'));
        if ($slug === '') {
            $slug = 'pick-' . bin2hex(random_bytes(4));
        }
        $base = mb_substr($slug, 0, 160);
        $slug = $base;
        $i = 1;
        while ($this->db->fetch('SELECT id FROM picks WHERE slug = :slug LIMIT 1', ['slug' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function mapEventStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match (true) {
            in_array($status, ['inprogress', 'in_progress', 'live', 'in-progress'], true) => 'in_progress',
            in_array($status, ['complete', 'completed', 'final', 'closed'], true) => 'completed',
            in_array($status, ['canceled', 'cancelled', 'postponed', 'suspended'], true) => 'canceled',
            default => 'scheduled',
        };
    }

    private function mapPickStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match (true) {
            in_array($status, ['win', 'won', 'winner', 'correct'], true) => 'won',
            in_array($status, ['loss', 'lost', 'loser', 'incorrect'], true) => 'lost',
            in_array($status, ['push', 'tie', 'pushed'], true) => 'push',
            in_array($status, ['cancel', 'canceled', 'cancelled', 'void', 'no_action'], true) => 'canceled',
            default => 'pending',
        };
    }

    private function normalizeBetType(string $type): string
    {
        $type = strtolower(trim($type));
        return match (true) {
            str_contains($type, 'money') || $type === 'ml' => 'moneyline',
            str_contains($type, 'over') || str_contains($type, 'under') || str_contains($type, 'total') => 'over_under',
            str_contains($type, 'prop') => 'prop',
            default => 'spread',
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private function formatSelection(array $row, string $betType): string
    {
        $side = (string) ($row['side'] ?? $row['selection'] ?? $row['pick'] ?? $row['team'] ?? $row['outcome'] ?? '');
        if (is_array($row['selection'] ?? null)) {
            $sel = $row['selection'];
            $side = (string) ($sel['name'] ?? $sel['side'] ?? $sel['team'] ?? $side);
        }
        $line = $row['line'] ?? $row['value'] ?? $row['spread'] ?? $row['total'] ?? null;
        $side = trim($side);
        if ($line === null || $line === '') {
            return $side !== '' ? $side : ucfirst($betType);
        }
        $lineNum = is_numeric($line) ? (float) $line : $line;
        $lineText = is_float($lineNum) ? ((string) ((int) $lineNum == $lineNum ? (int) $lineNum : $lineNum)) : (string) $line;
        if (is_float($lineNum) && $lineNum > 0 && !str_starts_with($lineText, '+')) {
            $lineText = '+' . $lineText;
        }
        return trim($side . ' ' . $lineText);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $game
     */
    private function matchupFromPick(array $row, array $game): string
    {
        $direct = (string) ($row['matchup'] ?? $row['event'] ?? $row['event_name'] ?? $game['name'] ?? '');
        if ($direct !== '') {
            return $direct;
        }
        $away = (string) ($game['away_team']['full_name'] ?? $game['away_team']['name'] ?? $row['away_team'] ?? '');
        $home = (string) ($game['home_team']['full_name'] ?? $game['home_team']['name'] ?? $row['home_team'] ?? '');
        if (is_string($game['away_team'] ?? null)) {
            $away = (string) $game['away_team'];
        }
        if (is_string($game['home_team'] ?? null)) {
            $home = (string) $game['home_team'];
        }
        if ($away !== '' && $home !== '') {
            return $away . ' @ ' . $home;
        }
        return $home !== '' ? $home : $away;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resultUnits(string $status, float $stake, array $row): float
    {
        if (isset($row['profit']) || isset($row['net_units']) || isset($row['result_units'])) {
            return $this->toFloat($row['profit'] ?? $row['net_units'] ?? $row['result_units']);
        }
        return match ($status) {
            'won' => $stake,
            'lost' => -$stake,
            default => 0.0,
        };
    }

    private function formatOdds(mixed $odds): ?string
    {
        if ($odds === null || $odds === '') {
            return null;
        }
        if (is_array($odds)) {
            $odds = $odds['american'] ?? $odds['price'] ?? $odds['money'] ?? reset($odds);
        }
        if (!is_numeric($odds)) {
            return substr(trim((string) $odds), 0, 20);
        }
        $n = (int) round((float) $odds);
        return $n > 0 ? '+' . $n : (string) $n;
    }

    private function normalizeDate(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return date('Ymd');
        }
        $date = preg_replace('/[^0-9]/', '', $date) ?? '';
        if (strlen($date) === 8) {
            return $date;
        }
        $ts = strtotime($date);
        return $ts ? date('Ymd', $ts) : date('Ymd');
    }

    private function toMysqlDatetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function toFloat(mixed $value): float
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        return round((float) $value, 2);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function pace(): void
    {
        usleep(self::PACE_MICROSECONDS);
    }

    /**
     * @param mixed $payload
     */
    private function fingerprintHash(mixed $payload): string
    {
        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function fingerprintDiffers(string $key, string $hash): bool
    {
        if (!$this->db->tableExists('action_network_fingerprints')) {
            return true;
        }
        $row = $this->db->fetch(
            'SELECT hash FROM action_network_fingerprints WHERE fingerprint_key = :k LIMIT 1',
            ['k' => $key]
        );
        if (!$row) {
            return true;
        }

        return (string) ($row['hash'] ?? '') !== $hash;
    }

    private function rememberFingerprint(string $key, string $hash): void
    {
        if (!$this->db->tableExists('action_network_fingerprints')) {
            return;
        }
        try {
            $this->db->query(
                'INSERT INTO action_network_fingerprints (fingerprint_key, hash) VALUES (:k, :h)
                 ON DUPLICATE KEY UPDATE hash = VALUES(hash)',
                ['k' => $key, 'h' => $hash]
            );
        } catch (Throwable $e) {
            Logger::warning('Could not store Action Network fingerprint', ['error' => $e->getMessage(), 'key' => $key]);
        }
    }

    /**
     * @param list<string> $ids
     */
    private function missingLocalIds(string $table, string $column, array $ids): bool
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id !== '')));
        if ($ids === [] || !$this->db->tableExists($table)) {
            return false;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $placeholders[] = ':id' . $i;
            $params['id' . $i] = $id;
        }

        $count = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        return $count < count($ids);
    }

    private function logSync(string $endpoint, string $syncType, int $items, bool $ok, ?string $error): void
    {
        $type = in_array($syncType, ['cron', 'manual', 'backfill'], true) ? $syncType : 'cron';
        try {
            if (!$this->db->tableExists('action_network_sync_logs')) {
                return;
            }
            $this->db->insert('action_network_sync_logs', [
                'endpoint' => mb_substr($endpoint, 0, 255),
                'sync_type' => $type,
                'items_synced' => $items,
                'status' => $ok ? 'success' : 'failed',
                'error_message' => $error,
            ]);
        } catch (Throwable $e) {
            Logger::warning('Could not write Action Network sync log', ['error' => $e->getMessage()]);
        }
    }
}
