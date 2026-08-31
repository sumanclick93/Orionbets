<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT * FROM users WHERE id = :id';
        if (!$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $user = $this->db->fetch($sql, ['id' => $id]);
        return $user ? $this->withRoles($user) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $user = $this->db->fetch('SELECT * FROM users WHERE email = :email AND deleted_at IS NULL', ['email' => $email]);
        return $user ? $this->withRoles($user) : null;
    }

    public function findByDiscordId(string $discordId): ?array
    {
        $discordId = trim($discordId);
        if ($discordId === '' || !$this->db->columnExists('users', 'discord_id')) {
            return null;
        }

        $user = $this->db->fetch(
            'SELECT * FROM users WHERE discord_id = :did AND deleted_at IS NULL',
            ['did' => $discordId]
        );
        return $user ? $this->withRoles($user) : null;
    }

    public function linkDiscordId(int $userId, string $discordId): void
    {
        $this->attachDiscord($userId, $discordId);
    }

    /**
     * Convert a PayPal/Upgrade.Chat guest row into a registered email account.
     * Subscriptions, transactions, and roles stay on this user_id.
     */
    public function claimGuestWithPassword(
        int $userId,
        string $firstName,
        string $lastName,
        string $passwordHash,
        ?string $lastLoginIp = null
    ): void {
        $sql = 'UPDATE users SET
                    password_hash = :password_hash,
                    first_name = :first_name,
                    last_name = :last_name,
                    is_guest = 0,
                    is_active = 1,
                    email_verified_at = IFNULL(email_verified_at, NOW()),
                    age_confirmed_at = IFNULL(age_confirmed_at, NOW()),
                    terms_accepted_at = IFNULL(terms_accepted_at, NOW()),
                    privacy_accepted_at = IFNULL(privacy_accepted_at, NOW()),
                    last_login_at = NOW()';
        $params = [
            'password_hash' => $passwordHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'id' => $userId,
        ];
        if ($lastLoginIp !== null && $lastLoginIp !== '') {
            $sql .= ', last_login_ip = :last_login_ip';
            $params['last_login_ip'] = $lastLoginIp;
        }
        $sql .= ' WHERE id = :id AND deleted_at IS NULL';
        $this->db->query($sql, $params);
    }

    /**
     * Claim or merge an existing row (typically a guest checkout) onto a Discord profile.
     * Foreign keys on subscriptions, subscription_transactions, and user_roles keep this user_id.
     */
    public function claimWithDiscord(int $userId, string $discordId, ?string $avatar = null): void
    {
        $sql = 'UPDATE users SET
                    discord_id = :discord_id,
                    is_guest = 0,
                    is_active = 1,
                    email_verified_at = IFNULL(email_verified_at, NOW())';
        $params = [
            'discord_id' => trim($discordId),
            'id' => $userId,
        ];
        if ($avatar !== null && $avatar !== '' && $this->db->columnExists('users', 'avatar')) {
            $sql .= ', avatar = :avatar';
            $params['avatar'] = $avatar;
        }
        $sql .= ' WHERE id = :id AND deleted_at IS NULL';
        $this->db->query($sql, $params);
    }

    /**
     * Attach Discord to an already-registered (non-guest) account without changing is_active.
     */
    public function attachDiscord(int $userId, string $discordId, ?string $avatar = null): void
    {
        $sql = 'UPDATE users SET
                    discord_id = :discord_id,
                    email_verified_at = IFNULL(email_verified_at, NOW())';
        $params = [
            'discord_id' => trim($discordId),
            'id' => $userId,
        ];
        if ($avatar !== null && $avatar !== '' && $this->db->columnExists('users', 'avatar')) {
            $sql .= ', avatar = :avatar';
            $params['avatar'] = $avatar;
        }
        $sql .= ' WHERE id = :id AND deleted_at IS NULL';
        $this->db->query($sql, $params);
    }

    public function findByCheckoutCookie(string $cookie): ?array
    {
        if ($cookie === '') {
            return null;
        }

        $user = $this->db->fetch(
            'SELECT * FROM users WHERE checkout_cookie = :c AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',
            ['c' => $cookie]
        );
        return $user ? $this->withRoles($user) : null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('users', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('users', $data, 'id = :id', ['id' => $id]);
    }

    public function assignRole(int $userId, string $slug): void
    {
        $role = $this->db->fetch('SELECT id FROM roles WHERE slug = :slug', ['slug' => $slug]);
        if (!$role) {
            return;
        }
        $exists = $this->db->fetch(
            'SELECT user_id FROM user_roles WHERE user_id = :uid AND role_id = :rid',
            ['uid' => $userId, 'rid' => $role['id']]
        );
        if (!$exists) {
            $this->db->insert('user_roles', ['user_id' => $userId, 'role_id' => $role['id']]);
        }
    }

    public function setRoles(int $userId, array $slugs): void
    {
        $this->db->delete('user_roles', 'user_id = :id', ['id' => $userId]);
        foreach ($slugs as $slug) {
            $this->assignRole($userId, $slug);
        }
    }

    public function paginate(
        string|array $searchOrFilters = '',
        int $page = 1,
        int $perPage = 20,
        string $status = '',
        string $role = '',
        string $tier = ''
    ): array {
        $filters = is_array($searchOrFilters) ? $searchOrFilters : [
            'q' => (string) $searchOrFilters,
            'status' => $status,
            'role' => $role,
            'tier' => $tier,
        ];

        $page = max(1, (int) ($filters['page'] ?? $page));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? $perPage)));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilterClause($filters);

        $total = (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT u.id) FROM users u {$where}", $params);
        $rows = $this->db->fetchAll(
            "SELECT u.* FROM users u {$where} GROUP BY u.id ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => array_map(fn ($u) => $this->withTierAndRoles($u), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function exportUsers(array $filters): array
    {
        [$where, $params] = $this->buildFilterClause($filters);
        $rows = $this->db->fetchAll(
            "SELECT u.* FROM users u {$where} GROUP BY u.id ORDER BY u.created_at DESC",
            $params
        );

        return array_map(fn ($u) => $this->withTierAndRoles($u), $rows);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterClause(array $filters): array
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $role = strtolower(trim((string) ($filters['role'] ?? '')));
        $tier = strtolower(trim((string) ($filters['tier'] ?? '')));
        $search = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));

        $clauses = [];
        $params = [];

        if ($status === 'deleted') {
            $clauses[] = 'u.deleted_at IS NOT NULL';
        } elseif ($status === 'suspended') {
            $clauses[] = 'u.deleted_at IS NULL AND u.is_active = 0';
        } elseif ($status === 'guest') {
            $clauses[] = 'u.deleted_at IS NULL AND u.is_guest = 1';
        } elseif ($status === 'active') {
            $clauses[] = 'u.deleted_at IS NULL AND u.is_active = 1 AND u.is_guest = 0';
        } elseif ($status !== 'all' && $status !== '') {
            $clauses[] = 'u.deleted_at IS NULL';
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $clauses[] = '(u.email LIKE :q1 OR u.first_name LIKE :q2 OR u.last_name LIKE :q3 OR u.discord_id LIKE :q4 OR REPLACE(u.email, CONCAT(\'+deleted\', u.id), \'\') LIKE :q5 OR u.id = :q6)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = is_numeric($search) ? (int) $search : 0;
        }

        if ($role !== '' && $role !== 'all') {
            $clauses[] = 'EXISTS (
                SELECT 1 FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.slug = :filter_role
            )';
            $params['filter_role'] = $role;
        }

        if ($tier === 'paid') {
            $clauses[] = '(
                EXISTS (
                    SELECT 1 FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE ur.user_id = u.id AND r.slug IN (\'premium_user\', \'admin\', \'super_admin\')
                ) OR EXISTS (
                    SELECT 1 FROM subscriptions s
                    WHERE s.user_id = u.id AND s.status IN (\'active\', \'trialing\')
                      AND (s.ends_at IS NULL OR s.ends_at > NOW())
                )
            )';
        } elseif ($tier === 'free') {
            $clauses[] = '(
                NOT EXISTS (
                    SELECT 1 FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE ur.user_id = u.id AND r.slug IN (\'premium_user\', \'admin\', \'super_admin\')
                ) AND NOT EXISTS (
                    SELECT 1 FROM subscriptions s
                    WHERE s.user_id = u.id AND s.status IN (\'active\', \'trialing\')
                      AND (s.ends_at IS NULL OR s.ends_at > NOW())
                )
            )';
        }

        $where = $clauses !== [] ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$where, $params];
    }

    public function originalEmail(array $user): string
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $id = (int) ($user['id'] ?? 0);
        if ($id > 0) {
            $tag = '+deleted' . $id . '@';
            if (str_contains($email, $tag)) {
                return str_replace($tag, '@', $email);
            }
        }

        return $email;
    }

    public function suspend(int $id): void
    {
        $this->update($id, [
            'is_active' => 0,
            'remember_token' => null,
        ]);
    }

    public function unsuspend(int $id): void
    {
        $this->update($id, ['is_active' => 1]);
    }

    public function softDelete(int $id): void
    {
        $user = $this->findById($id, true);
        if (!$user) {
            return;
        }

        $original = $this->originalEmail($user);
        $this->update($id, [
            'is_active' => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
            'remember_token' => null,
            'email' => $this->tombstoneEmail($id, $original),
        ]);
    }

    public function restore(int $id): void
    {
        $user = $this->findById($id, true);
        if (!$user) {
            return;
        }

        $email = $this->originalEmail($user);
        $taken = $this->db->fetch(
            'SELECT id FROM users WHERE email = :e AND id != :id AND deleted_at IS NULL',
            ['e' => $email, 'id' => $id]
        );
        if ($taken) {
            throw new \RuntimeException('That email is already in use by another member.');
        }

        $this->update($id, [
            'deleted_at' => null,
            'is_active' => 1,
            'email' => $email,
        ]);
    }

    private function tombstoneEmail(int $id, string $email): string
    {
        $email = strtolower(trim($email));
        if (!str_contains($email, '@')) {
            return 'deleted+' . $id . '@invalid.local';
        }

        [$local, $domain] = explode('@', $email, 2);
        $candidate = $local . '+deleted' . $id . '@' . $domain;
        if (strlen($candidate) <= 190) {
            return $candidate;
        }

        return 'deleted+' . $id . '@invalid.local';
    }

    public function countActive(): int
    {
        return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL');
    }

    public function recent(int $limit = 8): array
    {
        return $this->db->fetchAll(
            'SELECT id, first_name, last_name, email, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
    }

    public function dailyActive(int $days = 1): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL :d DAY)',
            ['d' => $days]
        );
    }

    public function withTierAndRoles(array $user): array
    {
        $user = $this->withRoles($user);
        $userId = (int) $user['id'];
        $roles = $user['roles'] ?? [];

        $hasPaidRole = in_array('premium_user', $roles, true)
            || in_array('admin', $roles, true)
            || in_array('super_admin', $roles, true);

        $hasActiveSubscription = false;
        if (!$hasPaidRole && $this->db->tableExists('subscriptions')) {
            $sub = $this->db->fetch(
                "SELECT id FROM subscriptions
                 WHERE user_id = :uid AND status IN ('active', 'trialing')
                   AND (ends_at IS NULL OR ends_at > NOW())
                 LIMIT 1",
                ['uid' => $userId]
            );
            $hasActiveSubscription = $sub !== null;
        }

        $isPaid = $hasPaidRole || $hasActiveSubscription;
        $user['tier'] = $isPaid ? 'Paid Member' : 'Free Member';
        $user['tier_slug'] = $isPaid ? 'paid' : 'free';
        $user['is_paid'] = $isPaid;
        $user['discord_linked'] = !empty($user['discord_id']);

        return $user;
    }

    private function withRoles(array $user): array
    {
        $roles = $this->db->fetchAll(
            'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :id',
            ['id' => $user['id']]
        );
        $user['roles'] = array_column($roles, 'slug');
        return $user;
    }
}
