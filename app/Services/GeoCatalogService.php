<?php

declare(strict_types=1);

namespace App\Services;

final class GeoCatalogService
{
    private static ?array $countries = null;
    private static ?array $states = null;
    private static array $cityFiles = [];

    public function countries(): array
    {
        if (self::$countries !== null) {
            return self::$countries;
        }

        $path = CONFIG_PATH . '/geo/countries.json';
        $raw = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        self::$countries = is_array($raw) ? $raw : [];

        return self::$countries;
    }

    public function country(string $iso2): ?array
    {
        $iso2 = strtoupper($iso2);
        foreach ($this->countries() as $country) {
            if (strtoupper((string) ($country['iso2'] ?? '')) === $iso2) {
                return $country;
            }
        }
        return null;
    }

    public function states(string $countryIso2): array
    {
        $countryIso2 = strtoupper($countryIso2);
        $all = $this->allStates();
        return $all[$countryIso2] ?? [];
    }

    public function state(string $countryIso2, string $stateIso2): ?array
    {
        $stateIso2 = strtoupper($stateIso2);
        foreach ($this->states($countryIso2) as $state) {
            if (strtoupper((string) ($state['iso2'] ?? '')) === $stateIso2) {
                return $state;
            }
        }
        return null;
    }

    public function cities(string $countryIso2, string $stateIso2): array
    {
        $countryIso2 = strtoupper($countryIso2);
        $raw = $this->cityFile($countryIso2);

        if (isset($raw[$stateIso2]) && is_array($raw[$stateIso2])) {
            return array_values($raw[$stateIso2]);
        }

        $upper = strtoupper($stateIso2);
        foreach ($raw as $code => $cities) {
            if (strtoupper((string) $code) === $upper && is_array($cities)) {
                return array_values($cities);
            }
        }

        return [];
    }

    private function cityFile(string $countryIso2): array
    {
        if (isset(self::$cityFiles[$countryIso2])) {
            return self::$cityFiles[$countryIso2];
        }

        $path = CONFIG_PATH . '/geo/cities/' . $countryIso2 . '.json';
        if (!is_file($path)) {
            self::$cityFiles[$countryIso2] = [];
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        self::$cityFiles[$countryIso2] = is_array($raw) ? $raw : [];

        return self::$cityFiles[$countryIso2];
    }

    private function allStates(): array
    {
        if (self::$states !== null) {
            return self::$states;
        }

        $path = CONFIG_PATH . '/geo/states.json';
        $raw = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        self::$states = is_array($raw) ? $raw : [];

        return self::$states;
    }
}
