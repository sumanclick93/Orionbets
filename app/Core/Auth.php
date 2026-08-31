<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    private Session $session;
    private Database $db;
    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $id = $this->session->get('user_id');
        return $id ? (int) $id : null;
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->id();
        if (!$id) {
            return null;
        }

        $repo = new UserRepository($this->db);
        $this->user = $repo->findById($id);

        if (!$this->user || (int) $this->user['is_active'] !== 1) {
            $this->logout();
            $this->user = null;
        }

        return $this->user;
    }

    public function login(array $user, bool $remember = false): void
    {
        $this->session->regenerate(true);
        $this->session->set('user_id', (int) $user['id']);
        $this->user = $user;
        $this->resolved = true;

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $this->db->update('users', [
                'remember_token' => hash('sha256', $token),
            ], 'id = :id', ['id' => $user['id']]);

            setcookie('edgeplay_remember', $token, [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => Env::bool('SESSION_SECURE'),
            ]);
        }
    }

    public function logout(): void
    {
        if ($this->id()) {
            $this->db->update('users', ['remember_token' => null], 'id = :id', ['id' => $this->id()]);
        }

        $this->session->forget('user_id');
        $this->session->regenerate(true);
        $this->user = null;
        $this->resolved = true;

        setcookie('edgeplay_remember', '', time() - 3600, '/');
    }

    public function roles(): array
    {
        $user = $this->user();
        if (!$user) {
            return [];
        }

        return $user['roles'] ?? [];
    }

    public function hasRole(string ...$roles): bool
    {
        $current = $this->roles();
        foreach ($roles as $role) {
            if (in_array($role, $current, true)) {
                return true;
            }
        }
        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin', 'super_admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isEditor(): bool
    {
        return $this->hasRole('editor', 'admin', 'super_admin');
    }

    public function isPremium(): bool
    {
        if (!$this->check()) {
            return false;
        }

        if ($this->hasRole('premium_user', 'admin', 'super_admin')) {
            return true;
        }

        $user = $this->user();
        if (!$user) {
            return false;
        }

        $row = $this->db->fetch(
            "SELECT id FROM subscriptions
             WHERE user_id = :uid AND status IN ('active', 'trialing')
               AND (ends_at IS NULL OR ends_at > NOW())
             LIMIT 1",
            ['uid' => $user['id']]
        );

        return $row !== null;
    }

    public function isFreeMember(): bool
    {
        return $this->check() && !$this->isPremium();
    }
}
