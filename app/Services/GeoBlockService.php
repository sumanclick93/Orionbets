<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GeoRestrictionRepository;

final class GeoBlockService
{
    public function __construct(
        private GeoRestrictionRepository $rules,
        private SettingsService $settings
    ) {
    }

    public function enabled(): bool
    {
        $all = $this->settings->all();
        return (string) ($all['geo_block_enabled'] ?? '0') === '1';
    }

    public function setEnabled(bool $on): void
    {
        $this->settings->put('geo_block_enabled', $on ? '1' : '0');
    }

    public function message(): array
    {
        $title = trim((string) settings('geo_block_title', ''));
        $copy = trim((string) settings('geo_block_copy', ''));

        return [
            'kicker' => 'Region lock',
            'title' => $title !== '' ? $title : 'This desk is not available here',
            'copy' => $copy !== '' ? $copy : 'Access from your country, state, or city has been restricted. If you believe this is a mistake, contact support.',
        ];
    }

    public function evaluate(array $location, bool $respectEnabled = true): array
    {
        $empty = [
            'blocked' => false,
            'rule' => null,
            'reason' => null,
            'scope' => null,
        ];

        if ($respectEnabled && !$this->enabled()) {
            return $empty + ['reason' => 'disabled'];
        }

        $countryCode = strtoupper(trim((string) ($location['country_code'] ?? '')));
        if ($countryCode === '') {
            return $empty + ['reason' => 'unknown_location'];
        }

        $stateCode = strtoupper(trim((string) ($location['state_code'] ?? '')));
        $stateName = $this->norm((string) ($location['state'] ?? ''));
        $cityName = $this->norm((string) ($location['city'] ?? ''));

        $rules = $this->rules->all();
        $cityRule = $this->matchCity($rules, $countryCode, $stateCode, $stateName, $cityName);
        if ($cityRule !== null) {
            return $this->fromRule($cityRule);
        }

        $stateRule = $this->matchState($rules, $countryCode, $stateCode, $stateName);
        if ($stateRule !== null) {
            return $this->fromRule($stateRule);
        }

        $countryRule = $this->matchCountry($rules, $countryCode);
        if ($countryRule !== null) {
            return $this->fromRule($countryRule);
        }

        return $empty;
    }

    public function annotate(string $countryCode, string $stateCode = '', string $stateName = '', string $cityName = ''): array
    {
        $rules = $this->rules->all();
        $countryCode = strtoupper($countryCode);
        $stateCode = strtoupper($stateCode);
        $stateName = $this->norm($stateName);
        $cityName = $this->norm($cityName);

        $countryRule = $this->matchCountry($rules, $countryCode);
        $stateRule = $stateCode !== '' || $stateName !== ''
            ? $this->matchState($rules, $countryCode, $stateCode, $stateName)
            : null;
        $cityRule = $cityName !== ''
            ? $this->matchCity($rules, $countryCode, $stateCode, $stateName, $cityName)
            : null;

        $winner = $cityRule ?? $stateRule ?? $countryRule;
        $explicit = $cityName !== '' ? $cityRule : ($stateCode !== '' || $stateName !== '' ? $stateRule : $countryRule);

        return [
            'restricted' => $winner !== null && (int) $winner['restricted'] === 1,
            'inherited' => $winner !== null && $explicit === null,
            'explicit' => $explicit === null ? null : ((int) $explicit['restricted'] === 1),
            'rule' => $explicit,
            'effective_rule' => $winner,
            'scope' => $winner['scope'] ?? null,
        ];
    }

    public function applyToggle(array $target, bool $restricted): array
    {
        $scope = $target['scope'];
        $countryCode = strtoupper((string) $target['country_code']);
        $stateCode = strtoupper((string) ($target['state_code'] ?? ''));
        $cityName = trim((string) ($target['city_name'] ?? ''));
        $stateName = (string) ($target['state_name'] ?? '');

        $parentBlocked = $this->parentBlocked($scope, $countryCode, $stateCode, $stateName, $cityName);

        if ($restricted) {
            if ($parentBlocked) {
                $this->rules->deleteLevel($scope, $countryCode, $stateCode, $cityName);
                return ['ok' => true, 'action' => 'inherit_restrict'];
            }

            return [
                'ok' => true,
                'action' => 'restrict',
                'rule' => $this->rules->upsert([
                    'scope' => $scope,
                    'country_code' => $countryCode,
                    'country_name' => (string) $target['country_name'],
                    'state_code' => $stateCode,
                    'state_name' => $stateName,
                    'city_name' => $cityName,
                    'restricted' => 1,
                ]),
            ];
        }

        if ($parentBlocked) {
            return [
                'ok' => true,
                'action' => 'allow_exception',
                'rule' => $this->rules->upsert([
                    'scope' => $scope,
                    'country_code' => $countryCode,
                    'country_name' => (string) $target['country_name'],
                    'state_code' => $stateCode,
                    'state_name' => $stateName,
                    'city_name' => $cityName,
                    'restricted' => 0,
                ]),
            ];
        }

        $this->rules->deleteLevel($scope, $countryCode, $stateCode, $cityName);
        return ['ok' => true, 'action' => 'clear'];
    }

    public function parentBlocked(string $scope, string $countryCode, string $stateCode = '', string $stateName = '', string $cityName = ''): bool
    {
        $rules = $this->rules->all();
        $countryRule = $this->matchCountry($rules, $countryCode);
        $stateRule = ($stateCode !== '' || $stateName !== '')
            ? $this->matchState($rules, $countryCode, $stateCode, $stateName)
            : null;

        if ($scope === 'country') {
            return false;
        }

        if ($scope === 'state') {
            return $countryRule !== null && (int) $countryRule['restricted'] === 1;
        }

        if ($stateRule !== null) {
            return (int) $stateRule['restricted'] === 1;
        }

        return $countryRule !== null && (int) $countryRule['restricted'] === 1;
    }

    private function fromRule(array $rule): array
    {
        $blocked = (int) $rule['restricted'] === 1;
        return [
            'blocked' => $blocked,
            'rule' => $rule,
            'scope' => $rule['scope'],
            'reason' => $blocked ? 'restricted' : 'allowed_exception',
        ];
    }

    private function matchCountry(array $rules, string $countryCode): ?array
    {
        foreach ($rules as $rule) {
            if ($rule['scope'] === 'country' && strtoupper((string) $rule['country_code']) === $countryCode) {
                return $rule;
            }
        }
        return null;
    }

    private function matchState(array $rules, string $countryCode, string $stateCode, string $stateName): ?array
    {
        foreach ($rules as $rule) {
            if ($rule['scope'] !== 'state' || strtoupper((string) $rule['country_code']) !== $countryCode) {
                continue;
            }
            $ruleCode = strtoupper((string) $rule['state_code']);
            $ruleName = $this->norm((string) $rule['state_name']);
            if ($stateCode !== '' && $ruleCode !== '' && $ruleCode === $stateCode) {
                return $rule;
            }
            if ($stateName !== '' && $ruleName !== '' && $ruleName === $stateName) {
                return $rule;
            }
        }
        return null;
    }

    private function matchCity(array $rules, string $countryCode, string $stateCode, string $stateName, string $cityName): ?array
    {
        if ($cityName === '') {
            return null;
        }

        foreach ($rules as $rule) {
            if ($rule['scope'] !== 'city' || strtoupper((string) $rule['country_code']) !== $countryCode) {
                continue;
            }
            if ($this->norm((string) $rule['city_name']) !== $cityName) {
                continue;
            }
            $ruleCode = strtoupper((string) $rule['state_code']);
            $ruleName = $this->norm((string) $rule['state_name']);
            if ($ruleCode !== '' && $stateCode !== '' && $ruleCode !== $stateCode) {
                continue;
            }
            if ($ruleName !== '' && $stateName !== '' && $ruleName !== $stateName && $ruleCode === '') {
                continue;
            }
            return $rule;
        }
        return null;
    }

    public function norm(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
    }
}
