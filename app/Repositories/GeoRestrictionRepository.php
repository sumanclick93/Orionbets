<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class GeoRestrictionRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        if (!$this->db->tableExists('geo_restrictions')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM geo_restrictions ORDER BY country_name, state_name, city_name, scope'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM geo_restrictions WHERE id = :id', ['id' => $id]);
    }

    public function upsert(array $rule): array
    {
        $existing = $this->db->fetch(
            'SELECT id FROM geo_restrictions
             WHERE scope = :scope AND country_code = :country_code
               AND state_code = :state_code AND city_name = :city_name',
            [
                'scope' => $rule['scope'],
                'country_code' => $rule['country_code'],
                'state_code' => $rule['state_code'],
                'city_name' => $rule['city_name'],
            ]
        );

        if ($existing) {
            $this->db->update('geo_restrictions', [
                'country_name' => $rule['country_name'],
                'state_name' => $rule['state_name'],
                'restricted' => $rule['restricted'],
            ], 'id = :id', ['id' => $existing['id']]);

            return $this->find((int) $existing['id']) ?? [];
        }

        $id = $this->db->insert('geo_restrictions', $rule);
        return $this->find($id) ?? [];
    }

    public function delete(int $id): void
    {
        $this->db->delete('geo_restrictions', 'id = :id', ['id' => $id]);
    }

    public function deleteLevel(string $scope, string $countryCode, string $stateCode = '', string $cityName = ''): void
    {
        $this->db->delete(
            'geo_restrictions',
            'scope = :scope AND country_code = :cc AND state_code = :sc AND city_name = :city',
            [
                'scope' => $scope,
                'cc' => $countryCode,
                'sc' => $stateCode,
                'city' => $cityName,
            ]
        );
    }
}
