<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Env;
use App\Repositories\GeoRestrictionRepository;
use App\Services\AuditService;
use App\Services\GeoBlockService;
use App\Services\GeoCatalogService;
use App\Services\GeoIpService;
use App\Services\SettingsService;

final class AdminGeoController extends Controller
{
    public function index(): string
    {
        $geo = new GeoIpService();
        $block = $this->block();
        $location = $geo->locate($this->request, false);
        $decision = $block->evaluate($location, false);
        if (!empty($decision['blocked']) && !$block->enabled()) {
            $block->setEnabled(true);
        }

        return $this->view('admin/geo/index', [
            'title' => 'Geo blocking',
            'enabled' => $block->enabled(),
            'location' => $location,
            'decision' => $decision,
            'rules' => (new GeoRestrictionRepository($this->db))->all(),
            'message' => $block->message(),
            'canPreview' => $geo->canPreview(),
            'debug' => Env::bool('APP_DEBUG'),
            'countryCount' => count((new GeoCatalogService())->countries()),
        ], 'admin');
    }

    public function countries(): never
    {
        $catalog = new GeoCatalogService();
        $block = $this->block();
        $q = mb_strtolower(trim((string) $this->request->query('q', '')));
        $out = [];

        foreach ($catalog->countries() as $country) {
            $iso2 = strtoupper((string) ($country['iso2'] ?? ''));
            $name = (string) ($country['name'] ?? '');
            if ($q !== '' && !str_contains(mb_strtolower($name . ' ' . $iso2), $q)) {
                continue;
            }
            $out[] = [
                'iso2' => $iso2,
                'name' => $name,
                'flag' => (string) ($country['flag'] ?? ''),
                'status' => $block->annotate($iso2),
            ];
        }

        $this->json(['countries' => $out]);
    }

    public function states(): never
    {
        $country = strtoupper(trim((string) $this->request->query('country', '')));
        $catalog = new GeoCatalogService();
        $meta = $catalog->country($country);
        if ($country === '' || $meta === null) {
            $this->json(['error' => 'Unknown country'], 404);
        }

        $block = $this->block();
        $q = mb_strtolower(trim((string) $this->request->query('q', '')));
        $states = [];
        foreach ($catalog->states($country) as $state) {
            $iso2 = (string) ($state['iso2'] ?? '');
            $name = (string) ($state['name'] ?? '');
            if ($q !== '' && !str_contains(mb_strtolower($name . ' ' . $iso2), $q)) {
                continue;
            }
            $states[] = [
                'iso2' => $iso2,
                'name' => $name,
                'status' => $block->annotate($country, $iso2, $name),
            ];
        }

        $this->json([
            'country' => $meta,
            'country_status' => $block->annotate($country),
            'states' => $states,
        ]);
    }

    public function cities(): never
    {
        $country = strtoupper(trim((string) $this->request->query('country', '')));
        $state = trim((string) $this->request->query('state', ''));
        $catalog = new GeoCatalogService();
        $countryMeta = $catalog->country($country);
        $stateMeta = $catalog->state($country, $state);
        if ($countryMeta === null || $stateMeta === null) {
            $this->json(['error' => 'Unknown state'], 404);
        }

        $block = $this->block();
        $q = mb_strtolower(trim((string) $this->request->query('q', '')));
        $limit = max(1, min(80, (int) $this->request->query('limit', '60')));
        $matched = [];
        foreach ($catalog->cities($country, $state) as $name) {
            if ($q !== '' && !str_contains(mb_strtolower((string) $name), $q)) {
                continue;
            }
            $matched[] = (string) $name;
        }

        $total = count($matched);
        $needQuery = $q === '' && $total > $limit;
        $slice = $needQuery ? [] : array_slice($matched, 0, $limit);
        $cities = [];
        foreach ($slice as $name) {
            $cities[] = [
                'name' => $name,
                'status' => $block->annotate($country, (string) $stateMeta['iso2'], (string) $stateMeta['name'], $name),
            ];
        }

        $this->json([
            'country' => $countryMeta,
            'state' => $stateMeta,
            'cities' => $cities,
            'total' => $total,
            'shown' => count($cities),
            'need_query' => $needQuery,
        ]);
    }

    public function rules(): never
    {
        $this->json(['rules' => (new GeoRestrictionRepository($this->db))->all()]);
    }

    public function saveRule(): never
    {
        $body = $this->request->json();
        $scope = (string) ($body['scope'] ?? $this->request->post('scope', ''));
        if (!in_array($scope, ['country', 'state', 'city'], true)) {
            $this->json(['error' => 'Invalid scope'], 422);
        }

        $restricted = $body['restricted'] ?? $this->request->post('restricted');
        $restricted = $restricted === true || $restricted === 1 || $restricted === '1';

        $countryCode = strtoupper(trim((string) ($body['country_code'] ?? $this->request->post('country_code', ''))));
        $catalog = new GeoCatalogService();
        $country = $catalog->country($countryCode);
        if ($country === null) {
            $this->json(['error' => 'Unknown country'], 422);
        }

        $stateCode = strtoupper(trim((string) ($body['state_code'] ?? $this->request->post('state_code', ''))));
        $stateName = trim((string) ($body['state_name'] ?? $this->request->post('state_name', '')));
        $cityName = trim((string) ($body['city_name'] ?? $this->request->post('city_name', '')));

        if ($scope !== 'country') {
            $state = $catalog->state($countryCode, $stateCode);
            if ($state === null) {
                $this->json(['error' => 'Unknown state'], 422);
            }
            $stateCode = (string) $state['iso2'];
            $stateName = (string) $state['name'];
        } else {
            $stateCode = '';
            $stateName = '';
            $cityName = '';
        }

        if ($scope === 'city') {
            if ($cityName === '') {
                $this->json(['error' => 'City is required'], 422);
            }
        } else {
            $cityName = '';
        }

        $result = $this->block()->applyToggle([
            'scope' => $scope,
            'country_code' => $countryCode,
            'country_name' => (string) $country['name'],
            'state_code' => $stateCode,
            'state_name' => $stateName,
            'city_name' => $cityName,
        ], $restricted);

        (new AuditService($this->db))->log(
            $this->auth->id(),
            'geo.rule',
            'geo_restriction',
            $scope,
            $this->request,
            [
                'country' => $countryCode,
                'state' => $stateCode,
                'city' => $cityName,
                'restricted' => $restricted,
                'action' => $result['action'] ?? null,
            ]
        );

        $status = $this->block()->annotate($countryCode, $stateCode, $stateName, $cityName);
        $this->json([
            'ok' => true,
            'result' => $result,
            'status' => $status,
            'rules' => (new GeoRestrictionRepository($this->db))->all(),
        ]);
    }

    public function deleteRule(string $id): never
    {
        $repo = new GeoRestrictionRepository($this->db);
        $repo->delete((int) $id);
        $this->json(['ok' => true, 'rules' => $repo->all()]);
    }

    public function updateSettings(): never
    {
        $body = $this->request->json();
        $settings = new SettingsService($this->db);
        if (array_key_exists('enabled', $body) || $this->request->post('enabled') !== null) {
            $on = $body['enabled'] ?? $this->request->post('enabled');
            $settings->put('geo_block_enabled', ($on === true || $on === 1 || $on === '1') ? '1' : '0');
        }
        if (isset($body['title']) || $this->request->post('title') !== null) {
            $settings->put('geo_block_title', (string) ($body['title'] ?? $this->request->post('title', '')));
        }
        if (isset($body['copy']) || $this->request->post('copy') !== null) {
            $settings->put('geo_block_copy', (string) ($body['copy'] ?? $this->request->post('copy', '')));
        }

        $this->json([
            'ok' => true,
            'enabled' => (string) ($settings->all()['geo_block_enabled'] ?? '0') === '1',
        ]);
    }

    public function lookup(): never
    {
        $ip = trim((string) ($this->request->query('ip') ?: $this->request->post('ip', '')));
        $geo = new GeoIpService();
        if ($ip === '') {
            $location = $geo->locate($this->request, false);
        } else {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $this->json(['error' => 'Enter a valid IP address'], 422);
            }
            $found = $geo->lookup($ip);
            $location = $found ?? [
                'ip' => $ip,
                'is_private' => $geo->isPrivate($ip),
                'country' => $geo->isPrivate($ip) ? 'Local network' : null,
                'country_code' => null,
                'state' => null,
                'state_code' => null,
                'city' => null,
                'source' => $geo->isPrivate($ip) ? 'private' : 'none',
            ];
        }

        $decision = $this->block()->evaluate($location, false);
        $this->json(['location' => $location, 'decision' => $decision]);
    }

    public function preview(): never
    {
        $geo = new GeoIpService();
        if (!$geo->canPreview()) {
            $this->json(['error' => 'Preview is only available in debug mode or for editors'], 403);
        }

        $body = $this->request->json();
        $ip = trim((string) ($body['ip'] ?? $this->request->post('ip', '')));
        $clear = !empty($body['clear']);

        $secure = Env::bool('SESSION_SECURE');
        if ($clear || $ip === '') {
            setcookie(GeoIpService::PREVIEW_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $secure,
            ]);
            $this->json(['ok' => true, 'preview_ip' => null]);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->json(['error' => 'Enter a valid IP address'], 422);
        }

        setcookie(GeoIpService::PREVIEW_COOKIE, $ip, [
            'expires' => time() + 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);

        $found = $geo->lookup($ip) ?? ['ip' => $ip];
        $this->json(['ok' => true, 'preview_ip' => $ip, 'location' => $found, 'decision' => $this->block()->evaluate($found, false)]);
    }

    private function block(): GeoBlockService
    {
        return new GeoBlockService(
            new GeoRestrictionRepository($this->db),
            new SettingsService($this->db)
        );
    }
}
