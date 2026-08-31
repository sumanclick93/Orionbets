<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\SettingsService;

function app(): Application
{
    return Application::getInstance();
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function env_get(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

function request(): Request
{
    return app()->request;
}

function session(?string $key = null, mixed $default = null): mixed
{
    $session = app()->session;
    return $key === null ? $session : $session->get($key, $default);
}

function auth(): Auth
{
    return app()->auth;
}

function request_is_https(): bool
{
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (str_starts_with($forwarded, 'https')) {
        return true;
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function web_prefix(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/public' || str_starts_with($path, '/public/')) {
        return '/public';
    }

    return '';
}

function web_base_url(): string
{
    $configured = rtrim((string) (Env::get('APP_URL') ?: config('app.url', '')), '/');
    if ($configured !== '') {
        return canonical_public_url($configured);
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $host) ?? '';
    if ($host !== '') {
        return canonical_public_url((request_is_https() ? 'https' : 'http') . '://' . $host . web_prefix());
    }

    return 'https://orionbets.co';
}

function canonical_public_url(string $url): string
{
    $url = rtrim($url, '/');
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host === '' || str_contains($host, 'cpanel.site') || $host === 'orionbets.co' || $host === 'www.orionbets.co') {
        return 'https://orionbets.co';
    }

    return $url;
}

function url(string $path = '/'): string
{
    if (
        str_starts_with($path, 'http://')
        || str_starts_with($path, 'https://')
        || str_starts_with($path, '//')
        || str_starts_with($path, 'mailto:')
        || str_starts_with($path, 'tel:')
        || str_starts_with($path, '#')
    ) {
        return $path;
    }

    $base = rtrim(web_base_url(), '/');
    if ($path === '' || $path === '/') {
        return $base . '/';
    }

    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function old(string $key, mixed $default = ''): mixed
{
    static $old = null;
    if ($old === null) {
        $old = app()->session->getFlash('_old') ?? [];
    }
    return $old[$key] ?? $default;
}

function error(string $key): ?string
{
    $errors = errors();
    return $errors[$key][0] ?? null;
}

function errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = app()->session->getFlash('errors') ?? [];
    }
    return $errors;
}

function flash(string $key, mixed $default = null): mixed
{
    return app()->session->getFlash($key, $default);
}

function has_flash(string $key): bool
{
    return app()->session->hasFlash($key);
}

function component(string $name, array $data = []): string
{
    return View::component($name, $data);
}

function settings(?string $key = null, mixed $default = null): mixed
{
    static $all = null;
    if ($all === null) {
        try {
            $all = (new SettingsService(app()->db))->all();
        } catch (\Throwable) {
            $all = [];
        }
    }

    if ($key === null) {
        return $all;
    }

    return $all[$key] ?? $default;
}

function cms(string $key, mixed $default = ''): string
{
    static $service = null;
    if ($service === null) {
        try {
            $service = new \App\Services\CmsService(app()->db);
        } catch (\Throwable) {
            return (string) $default;
        }
    }

    return $service->get($key, $default);
}

function site_name(): string
{
    $name = (string) (settings('site_name') ?: Env::get('APP_NAME', 'Orion Bets'));
    return strcasecmp($name, 'EDGEPLAY') === 0 ? 'Orion Bets' : $name;
}

function cookie_consent_defaults(): array
{
    return [
        'cookie_kicker' => 'Cookie gate',
        'cookie_title' => 'Allow cookies to enter',
        'cookie_copy' => '{site} does not treat cookies as accepted until you allow them. Essential cookies keep you signed in, protect forms, and remember theme. Decline leaves the desk locked.',
        'cookie_item_1_label' => 'Session',
        'cookie_item_1_text' => 'Sign-in and account',
        'cookie_item_2_label' => 'Security',
        'cookie_item_2_text' => 'CSRF protection',
        'cookie_item_3_label' => 'Preference',
        'cookie_item_3_text' => 'Theme only',
        'cookie_deny' => 'Declined. Access is not granted without cookies. Allow cookies to continue, or leave the site.',
        'cookie_allow' => 'Allow cookies',
        'cookie_decline' => 'Decline',
        'cookie_policy_link' => 'Cookie Policy',
        'cookie_privacy_link' => 'Privacy',
    ];
}

function cookie_consent(?string $key = null, mixed $default = null): mixed
{
    $defaults = cookie_consent_defaults();
    $out = [];
    foreach ($defaults as $name => $fallback) {
        $value = trim((string) settings($name, ''));
        $out[$name] = $value !== '' ? $value : $fallback;
    }
    $out['cookie_copy'] = str_replace('{site}', site_name(), $out['cookie_copy']);
    $out['cookie_deny'] = str_replace('{site}', site_name(), $out['cookie_deny']);
    $out['cookie_items'] = [];
    for ($i = 1; $i <= 3; $i++) {
        $label = trim((string) $out['cookie_item_' . $i . '_label']);
        $text = trim((string) $out['cookie_item_' . $i . '_text']);
        if ($label === '' && $text === '') {
            continue;
        }
        $out['cookie_items'][] = ['label' => $label, 'text' => $text];
    }
    if ($key === null) {
        return $out;
    }
    return $out[$key] ?? $default ?? ($defaults[$key] ?? null);
}

function money(int $cents, string $currency = 'USD'): string
{
    $symbol = $currency === 'USD' ? '$' : $currency . ' ';
    return $symbol . number_format($cents / 100, 2);
}

function format_datetime(?string $value, string $format = 'M j, Y g:i A'): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '—';
}

function format_date(?string $value): string
{
    return format_datetime($value, 'M j, Y');
}

function is_active_path(string $path, bool $exact = false): bool
{
    $current = request()->path();
    if ($path === '/' || $exact) {
        return $current === $path;
    }
    return $current === $path || str_starts_with($current, rtrim($path, '/') . '/');
}

function nav_class(string $path, bool $exact = false): string
{
    return is_active_path($path, $exact) ? 'is-active' : '';
}

function pick_status_label(string $status): string
{
    return match ($status) {
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'completed' => 'Completed',
        'won' => 'Won',
        'lost' => 'Lost',
        'push' => 'Push',
        'cancelled' => 'Cancelled',
        'canceled' => 'Canceled',
        'pending' => 'Pending',
        'in_progress' => 'Live',
        default => ucfirst($status),
    };
}

function pick_is_historical(array $pick): bool
{
    $status = strtolower((string) ($pick['status'] ?? ''));
    if (in_array($status, ['won', 'lost', 'push', 'cancelled', 'canceled', 'completed'], true)) {
        return true;
    }

    $result = strtolower(trim((string) ($pick['result'] ?? '')));
    if (in_array($result, ['won', 'lost', 'push', 'cancelled', 'canceled'], true)) {
        return true;
    }

    return false;
}

function pick_should_gate(array $pick): bool
{
    if (auth()->isPremium()) {
        return false;
    }

    return !pick_is_historical($pick);
}

function is_premium(): bool
{
    return auth()->isPremium();
}

function is_free_member(): bool
{
    return auth()->isFreeMember();
}

function featured_paid_plan(): ?array
{
    static $plan = false;
    if ($plan !== false) {
        return $plan;
    }

    $plan = null;
    try {
        $rows = (new \App\Repositories\PlanRepository(app()->db))->allActive();
        $ranked = [[], [], []];
        foreach ($rows as $row) {
            if ((int) ($row['price_cents'] ?? 0) <= 0) {
                continue;
            }
            if ((int) ($row['is_featured'] ?? 0) === 1 && plan_has_checkout($row)) {
                $ranked[0][] = $row;
            } elseif (plan_has_checkout($row)) {
                $ranked[1][] = $row;
            } else {
                $ranked[2][] = $row;
            }
        }
        foreach ($ranked as $group) {
            if ($group !== []) {
                $plan = $group[0];
                break;
            }
        }
    } catch (\Throwable) {
        $plan = null;
    }

    return $plan;
}

function intended_path(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
        return null;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) {
        return null;
    }

    $blocked = ['/login', '/register', '/logout', '/auth/discord', '/auth/discord/callback'];
    $only = parse_url($path, PHP_URL_PATH) ?: $path;
    if (in_array($only, $blocked, true)) {
        return null;
    }

    return $path;
}

function discord_oauth_is_popup(): bool
{
    $q = strtolower(trim((string) request()->query('popup', '')));
    if ($q === '1' || $q === 'true' || $q === 'yes') {
        return true;
    }

    return (string) session('discord_oauth_popup', '') === '1'
        || (string) ($_SESSION['discord_oauth_popup'] ?? '') === '1';
}

function json_decode_array(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function excerpt(string $text, int $limit = 160): string
{
    $text = trim(strip_tags($text));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function current_url(): string
{
    return url(ltrim(request()->path(), '/'));
}

function canonical_url(): string
{
    return current_url();
}

function demo_badge(): string
{
    return '<span class="badge badge-demo">Demo data</span>';
}

function plan_payment_url(?array $plan): string
{
    return trim((string) ($plan['payment_url'] ?? ''));
}

function plan_has_checkout(?array $plan): bool
{
    if (!is_array($plan)) {
        return false;
    }

    if (is_upgrade_chat_url(plan_payment_url($plan))) {
        return true;
    }

    return paypal_configured() && (int) ($plan['price_cents'] ?? 0) > 0;
}

function paypal_configured(): bool
{
    return paypal_client_id() !== '';
}

function paypal_client_id(): string
{
    return trim((string) (Env::get('PAYPAL_CLIENT_ID') ?: config('app.paypal.client_id', '') ?: ''));
}

function paypal_env(): string
{
    $env = strtolower(trim((string) (Env::get('PAYPAL_ENV') ?: config('app.paypal.env', 'sandbox') ?: 'sandbox')));
    return $env === 'live' ? 'live' : 'sandbox';
}

function is_upgrade_chat_url(string $url): bool
{
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    return $scheme === 'https' && in_array($host, ['upgrade.chat', 'www.upgrade.chat'], true);
}

function upgrade_chat_product_id(string $url): string
{
    if (preg_match('#/p/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})#', $url, $match)) {
        return strtolower($match[1]);
    }

    return '';
}

function split_person_name(string $name): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return ['first_name' => 'Guest', 'last_name' => 'Member'];
    }

    $parts = explode(' ', $name, 2);
    return [
        'first_name' => $parts[0],
        'last_name' => $parts[1] ?? 'Member',
    ];
}

function action_network_config(): array
{
    return \App\Services\ActionNetworkService::config();
}

function pick_selection_label(array $pick): string
{
    $line = trim((string) ($pick['selection_line'] ?? ''));
    if ($line !== '') {
        return $line;
    }
    return trim((string) ($pick['title'] ?? $pick['matchup'] ?? ''));
}

function pick_matchup_label(array $pick): string
{
    $matchup = trim((string) ($pick['matchup'] ?? $pick['matchup_label'] ?? $pick['event_name'] ?? ''));
    if ($matchup !== '') {
        return $matchup;
    }
    $away = trim((string) ($pick['away_team'] ?? ''));
    $home = trim((string) ($pick['home_team'] ?? ''));
    if ($away !== '' && $home !== '') {
        return $away . ' @ ' . $home;
    }
    return (string) ($pick['title'] ?? 'Playbook pick');
}

function everflow_config(): array
{
    $rawDomain = trim((string) (settings('everflow_tracking_domain') ?: Env::get('EVERFLOW_TRACKING_DOMAIN', '')));
    $domain = strtolower(preg_replace('#^https?://#i', '', $rawDomain) ?? '');
    $domain = rtrim($domain, '/');
    if (str_starts_with($domain, 'www.')) {
        $host = $domain;
    } elseif ($domain !== '') {
        $host = 'www.' . $domain;
    } else {
        $host = '';
    }

    return [
        'domain' => $domain,
        'host' => $host,
        'nid' => trim((string) (settings('everflow_nid') ?: Env::get('EVERFLOW_NID', ''))),
        'offer_id' => trim((string) (settings('everflow_offer_id') ?: Env::get('EVERFLOW_OFFER_ID', ''))),
        'advertiser_id' => trim((string) (settings('everflow_advertiser_id') ?: Env::get('EVERFLOW_ADVERTISER_ID', ''))),
        'affiliate_id' => trim((string) (settings('everflow_affiliate_id') ?: Env::get('EVERFLOW_AFFILIATE_ID', Env::get('EVERFLOW_ADVERTISER_ID', '1')))),
        'rebill_event_id' => trim((string) (settings('everflow_rebill_event_id') ?: Env::get('EVERFLOW_REBILL_EVENT_ID', ''))),
        'lead_event_id' => trim((string) (settings('everflow_lead_event_id') ?: Env::get('EVERFLOW_LEAD_EVENT_ID', ''))),
        'checkout_event_id' => trim((string) (settings('everflow_checkout_event_id') ?: Env::get('EVERFLOW_CHECKOUT_EVENT_ID', ''))),
        'sdk_url' => $host !== '' ? 'https://' . $host . '/scripts/main.js' : '',
        'enabled' => $host !== '' || trim((string) Env::get('EVERFLOW_POSTBACK_URL', '')) !== '',
    ];
}

function everflow_sdk_url(): string
{
    return (string) (everflow_config()['sdk_url'] ?? '');
}

function everflow_csp_hosts(): string
{
    $host = (string) (everflow_config()['host'] ?? '');
    if ($host === '') {
        return '';
    }

    $www = 'https://' . $host;
    $bare = 'https://' . preg_replace('/^www\./', '', $host);
    return $www === $bare ? $www : $www . ' ' . $bare;
}

function is_everflow_transaction_id(string $value): bool
{
    return (bool) preg_match('/^[A-Fa-f0-9]{32}$/', $value);
}

function plan_price_label(array $plan): string
{
    if ((int) ($plan['price_cents'] ?? 0) === 0) {
        return 'Free';
    }

    return money((int) $plan['price_cents'], $plan['currency'] ?? 'USD') . '/' . ($plan['billing_interval'] ?? 'month');
}

function redirect_to(string $path): never
{
    app()->response->redirect($path);
}
