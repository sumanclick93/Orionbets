<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Request;

final class GeoIpService
{
    public const PREVIEW_COOKIE = 'orion_geo_preview_ip';

    public function locate(Request $request, bool $allowPreview = false): array
    {
        $override = $this->overrideIp($request, $allowPreview);
        $preview = $allowPreview ? $this->previewIp($request) : null;
        $queryTest = $this->queryTestIp($request);

        $base = [
            'ip' => $override ?? '',
            'lan_ip' => null,
            'preview_ip' => $preview ?: $queryTest,
            'is_preview' => $override !== null,
            'is_private' => false,
            'is_egress' => false,
            'country' => null,
            'country_code' => null,
            'state' => null,
            'state_code' => null,
            'city' => null,
            'source' => 'none',
        ];

        if ($override !== null) {
            $lookup = $this->lookup($override);
            if ($lookup !== null) {
                return array_merge($base, $lookup, [
                    'ip' => (string) ($lookup['ip'] ?? $override),
                    'is_preview' => true,
                    'source' => $lookup['source'] ?? 'lookup',
                ]);
            }
            $base['ip'] = $override;
            $base['is_private'] = $this->isPrivate($override);
            if ($base['is_private']) {
                $base['country'] = 'Local network';
                $base['source'] = 'private';
            }
            return $base;
        }

        $ip = $this->clientIp($request, false);
        $base['ip'] = $ip;
        $base['is_private'] = $this->isPrivate($ip);

        if ($ip === '' || $this->isPrivate($ip)) {
            if ($this->useEgressForPrivate()) {
                $egress = $this->lookupEgress();
                if ($egress !== null) {
                    return array_merge($base, $egress, [
                        'lan_ip' => $ip,
                        'is_private' => false,
                        'is_egress' => true,
                        'source' => 'egress',
                    ]);
                }
            }

            $base['country'] = 'Local network';
            $base['source'] = 'private';
            return $base;
        }

        $header = $this->fromHostHeaders($request);
        $lookup = $this->lookup($ip);

        if ($lookup !== null) {
            return array_merge($base, $lookup, [
                'ip' => (string) ($lookup['ip'] ?? $ip),
                'source' => $lookup['source'] ?? 'lookup',
            ]);
        }

        if ($header !== null) {
            return array_merge($base, $header, ['source' => 'header']);
        }

        return $base;
    }

    private function overrideIp(Request $request, bool $allowPreview): ?string
    {
        $query = $this->queryTestIp($request);
        if ($query !== null) {
            return $query;
        }

        if ($allowPreview) {
            return $this->previewIp($request);
        }

        return null;
    }

    public function queryTestIp(Request $request): ?string
    {
        if (!$this->useEgressForPrivate()) {
            return null;
        }

        $ip = trim((string) $request->query('geo_test_ip', ''));
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !$this->isPrivate($ip)) {
            return $ip;
        }

        return null;
    }

    public function lookup(string $ip): ?array
    {
        $ip = trim($ip);
        if ($ip === '' || $this->isPrivate($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $cached = $this->readCache($ip);
        if ($cached !== null) {
            $cached['source'] = 'cache';
            return $cached;
        }

        $result = $this->fetchIpApi($ip) ?? $this->fetchGeoJs($ip);
        if ($result === null) {
            return null;
        }

        $this->writeCache($ip, $result);
        return $result;
    }

    public function clientIp(Request $request, bool $allowPreview = false): string
    {
        if ($allowPreview) {
            $preview = $this->previewIp($request);
            if ($preview !== null) {
                return $preview;
            }
        }

        $candidates = [];
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $raw = (string) ($_SERVER[$key] ?? '');
            if ($raw === '') {
                continue;
            }
            foreach (explode(',', $raw) as $part) {
                $ip = trim($part);
                if ($ip !== '') {
                    $candidates[] = $ip;
                }
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && !$this->isPrivate($ip)) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $request->ip();
    }

    public function previewIp(Request $request): ?string
    {
        $cookie = trim((string) $request->cookie(self::PREVIEW_COOKIE, ''));
        if ($cookie !== '' && filter_var($cookie, FILTER_VALIDATE_IP)) {
            return $cookie;
        }
        return null;
    }

    public function canPreview(): bool
    {
        return Env::bool('APP_DEBUG') || app()->auth->isEditor();
    }

    public function isPrivate(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function useEgressForPrivate(): bool
    {
        $env = strtolower((string) Env::get('APP_ENV', 'production'));

        return Env::bool('APP_DEBUG') || in_array($env, ['local', 'development', 'dev'], true);
    }

    public function lookupEgress(): ?array
    {
        $cached = $this->readCache('egress-self', 3600);
        if ($cached !== null) {
            $cached['source'] = 'cache';
            return $cached;
        }

        $result = $this->fetchIpApi('') ?? $this->fetchGeoJs('');
        if ($result === null) {
            return null;
        }

        $this->writeCache('egress-self', $result);
        return $result;
    }

    private function fromHostHeaders(Request $request): ?array
    {
        $candidates = [
            $request->header('CF-IPCountry', ''),
            $request->header('X-Country-Code', ''),
            $request->header('X-Geo-Country', ''),
            (string) ($_SERVER['GEOIP_COUNTRY_CODE'] ?? ''),
            (string) ($_SERVER['HTTP_GEOIP_COUNTRY_CODE'] ?? ''),
            (string) ($_SERVER['COUNTRY_CODE'] ?? ''),
        ];

        $code = '';
        foreach ($candidates as $raw) {
            $try = strtoupper(trim((string) $raw));
            if ($try !== '' && $try !== 'XX' && $try !== 'T1' && preg_match('/^[A-Z]{2}$/', $try)) {
                $code = $try;
                break;
            }
        }

        if ($code === '') {
            return null;
        }

        return [
            'country_code' => $code,
            'country' => $code,
            'state' => $request->header('CF-Region') ?: ((string) ($_SERVER['GEOIP_REGION_NAME'] ?? '') ?: null),
            'state_code' => $request->header('CF-Region-Code') ?: ((string) ($_SERVER['GEOIP_REGION'] ?? '') ?: null),
            'city' => $request->header('CF-IPCity') ?: ((string) ($_SERVER['GEOIP_CITY'] ?? '') ?: null),
        ];
    }

    private function fetchIpApi(string $ip): ?array
    {
        $path = $ip === '' ? '' : rawurlencode($ip);
        $url = 'http://ip-api.com/json/' . $path . '?fields=status,country,countryCode,region,regionName,city,query';
        $json = $this->httpGet($url);
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return [
            'ip' => (string) ($data['query'] ?? $ip),
            'country' => $data['country'] ?? null,
            'country_code' => isset($data['countryCode']) ? strtoupper((string) $data['countryCode']) : null,
            'state' => $data['regionName'] ?? null,
            'state_code' => isset($data['region']) ? strtoupper((string) $data['region']) : null,
            'city' => $data['city'] ?? null,
            'source' => 'ip-api',
        ];
    }

    private function fetchGeoJs(string $ip): ?array
    {
        $url = $ip === ''
            ? 'https://get.geojs.io/v1/ip/geo.json'
            : 'https://get.geojs.io/v1/ip/geo/' . rawurlencode($ip) . '.json';
        $json = $this->httpGet($url);
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['country_code'])) {
            return null;
        }

        return [
            'ip' => (string) ($data['ip'] ?? $ip),
            'country' => $data['country'] ?? null,
            'country_code' => strtoupper((string) $data['country_code']),
            'state' => $data['region'] ?? null,
            'state_code' => null,
            'city' => $data['city'] ?? null,
            'source' => 'geojs',
        ];
    }

    private function httpGet(string $url): ?string
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: OrionBets-Geo/1.0',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($body) && $body !== '' && $code < 400) {
                return $body;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers) . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        return $body === false ? null : $body;
    }

    private function cachePath(string $ip): string
    {
        $dir = STORAGE_PATH . '/cache/geo-ip';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . md5($ip) . '.json';
    }

    private function readCache(string $ip, int $ttl = 86400): ?array
    {
        $path = $this->cachePath($ip);
        if (!is_file($path)) {
            return null;
        }
        if (filemtime($path) < time() - $ttl) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function writeCache(string $ip, array $data): void
    {
        @file_put_contents($this->cachePath($ip), json_encode($data));
    }
}
