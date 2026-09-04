<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private Database $db,
        private UserRepository $users,
        private Mailer $mailer,
        private AuditService $audit
    ) {
    }

    /**
     * Email/password registration, including guest-account claim when the email already paid.
     *
     * @return array{ok:bool,user?:array<string,mixed>,claimed?:bool,error?:string}
     */
    public function register(array $input, Request $request): array
    {
        $email = strtolower(trim((string) $input['email']));
        $existing = $this->users->findByEmail($email);

        if ($existing && (int) ($existing['is_guest'] ?? 0) === 1) {
            return [
                'ok' => true,
                'claimed' => true,
                'user' => $this->claimGuest($existing, $input, $request),
            ];
        }

        if ($existing) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Please log in.'];
        }

        $now = date('Y-m-d H:i:s');
        $id = $this->users->create([
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'email' => $email,
            'password_hash' => password_hash((string) $input['password'], PASSWORD_DEFAULT),
            'timezone' => 'UTC',
            'theme_preference' => 'system',
            'is_active' => 1,
            'is_guest' => 0,
            'age_confirmed_at' => $now,
            'terms_accepted_at' => $now,
            'privacy_accepted_at' => $now,
            'last_login_at' => $now,
            'last_login_ip' => $request->ip(),
        ]);

        $this->users->assignRole($id, 'user');
        $this->seedPreferences($id);

        $token = bin2hex(random_bytes(32));
        $this->db->insert('email_verifications', [
            'user_id' => $id,
            'token' => hash('sha256', $token),
        ]);

        $user = $this->users->findById($id);
        $this->mailer->send($user['email'], 'Verify your Orion Bets desk', 'verify', [
            'user' => $user,
            'url' => url('/verify-email?token=' . $token),
        ]);
        $this->mailer->send($user['email'], 'Welcome to Orion Bets', 'welcome', ['user' => $user]);

        try {
            (new BeehiivService())->subscribeAndSendWelcome(
                (string) ($user['email'] ?? ''),
                (string) ($user['first_name'] ?? ''),
                (string) ($user['last_name'] ?? '')
            );
        } catch (\Throwable $e) {
            Logger::error('Beehiiv registration trigger exception', ['error' => $e->getMessage()]);
        }

        $this->audit->log($id, 'registration', 'user', (string) $id, $request);

        return ['ok' => true, 'claimed' => false, 'user' => $user];
    }

    /**
     * Convert a guest checkout row into a password-protected member. Same user_id keeps
     * subscriptions, subscription_transactions, and user_roles (including premium_user).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function claimGuest(array $user, array $input, Request $request): array
    {
        $this->users->claimGuestWithPassword(
            (int) $user['id'],
            trim((string) $input['first_name']),
            trim((string) $input['last_name']),
            password_hash((string) $input['password'], PASSWORD_DEFAULT),
            $request->ip()
        );
        $this->users->assignRole((int) $user['id'], 'user');
        $this->db->delete('password_resets', 'email = :e', ['e' => $user['email']]);
        $this->audit->log((int) $user['id'], 'guest_claimed', 'user', (string) $user['id'], $request, [
            'channel' => 'email',
        ]);

        return $this->users->findById((int) $user['id']) ?? $user;
    }

    public function attempt(string $email, string $password, Request $request): array
    {
        $email = strtolower(trim($email));
        $recentFails = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :e AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            ['e' => $email]
        );

        if ($recentFails >= 8) {
            return ['ok' => false, 'error' => 'Too many sign-in attempts. Try again in 15 minutes.'];
        }

        $user = $this->users->findByEmail($email);
        $ok = $user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash']);

        $this->db->insert('login_attempts', [
            'email' => $email,
            'ip' => $request->ip(),
            'success' => $ok ? 1 : 0,
        ]);

        if (!$ok) {
            if ($user && (int) ($user['is_active'] ?? 0) !== 1 && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
                return ['ok' => false, 'error' => 'This account is suspended.'];
            }
            if ($user && (int) ($user['is_guest'] ?? 0) === 1) {
                return ['ok' => false, 'error' => 'This email paid as a guest. Create a password with the same email to sign in and see your history.'];
            }
            if ($user && !empty($user['discord_id'])) {
                return ['ok' => false, 'error' => 'This account uses Discord. Continue with Discord, or reset a password to sign in with email.'];
            }
            return ['ok' => false, 'error' => 'Those credentials do not match our records.'];
        }

        $this->users->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip(),
        ]);

        $this->audit->log((int) $user['id'], 'login', 'user', (string) $user['id'], $request);

        return ['ok' => true, 'user' => $user];
    }

    public function requestReset(string $email, Request $request): void
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->db->delete('password_resets', 'email = :e', ['e' => $user['email']]);
        $this->db->insert('password_resets', [
            'email' => $user['email'],
            'token' => hash('sha256', $token),
        ]);

        $this->mailer->send($user['email'], 'Reset your Orion Bets password', 'reset', [
            'user' => $user,
            'url' => url('/reset-password?token=' . $token),
        ]);
        $this->audit->log((int) $user['id'], 'password_reset_requested', 'user', (string) $user['id'], $request);
    }

    public function resetPassword(string $token, string $password, Request $request): bool
    {
        $row = $this->db->fetch(
            'SELECT * FROM password_resets WHERE token = :t AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)',
            ['t' => hash('sha256', $token)]
        );
        if (!$row) {
            return false;
        }

        $user = $this->users->findByEmail($row['email']);
        if (!$user) {
            return false;
        }

        if ((int) ($user['is_guest'] ?? 0) === 1) {
            $this->users->claimGuestWithPassword(
                (int) $user['id'],
                (string) ($user['first_name'] ?? ''),
                (string) ($user['last_name'] ?? ''),
                password_hash($password, PASSWORD_DEFAULT)
            );
            $this->users->assignRole((int) $user['id'], 'user');
        } else {
            $this->users->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }
        $this->db->delete('password_resets', 'email = :e', ['e' => $user['email']]);
        $this->audit->log((int) $user['id'], 'password_changed', 'user', (string) $user['id'], $request);
        return true;
    }

    public function verifyEmail(string $token): bool
    {
        $row = $this->db->fetch(
            'SELECT * FROM email_verifications WHERE token = :t',
            ['t' => hash('sha256', $token)]
        );
        if (!$row) {
            return false;
        }

        $this->users->update((int) $row['user_id'], [
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->delete('email_verifications', 'user_id = :id', ['id' => $row['user_id']]);
        return true;
    }

    /**
     * Discord OAuth login, including guest-account claim when the Discord email matches a checkout row.
     *
     * @param array{id?:string,username?:string,handle?:string,email?:string,avatar?:string,verified?:bool} $profile
     * @param array<string, mixed>|null $currentUser
     * @return array{ok:bool,user?:array,claimed?:bool,created?:bool,error?:string}
     */
    public function loginWithDiscord(array $profile, Request $request, ?array $currentUser = null): array
    {
        $discordId = trim((string) ($profile['id'] ?? ''));
        if ($discordId === '') {
            return ['ok' => false, 'error' => 'Discord did not return a user id.'];
        }

        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = '';
        }

        $avatar = $this->discordAvatarUrl($discordId, (string) ($profile['avatar'] ?? ''));
        $user = null;
        $created = false;
        $linked = false;
        $claimed = false;

        if ($currentUser) {
            $currentId = (int) ($currentUser['id'] ?? 0);
            $currentEmail = strtolower(trim((string) ($currentUser['email'] ?? '')));

            // Check if Discord ID is already bound to another user
            $existingDiscord = $this->users->findByDiscordId($discordId);
            if ($existingDiscord && (int) $existingDiscord['id'] !== $currentId) {
                return ['ok' => false, 'error' => 'That Discord account is already linked to another member.'];
            }

            $currentDiscord = trim((string) ($currentUser['discord_id'] ?? ''));
            if ($currentDiscord !== '' && $currentDiscord !== $discordId) {
                return ['ok' => false, 'error' => 'This account is already linked to a different Discord user.'];
            }

            // Strict Email Match Requirement:
            // Compare the Discord account's email with the logged-in user's database email
            if ($email === '' || strcasecmp($email, $currentEmail) !== 0) {
                return [
                    'ok' => false,
                    'error' => 'Connection failed: The email address associated with your Discord account does not match your registered account email.',
                ];
            }

            if ($currentId > 0) {
                if ((int) ($currentUser['is_guest'] ?? 0) === 1) {
                    $this->users->claimWithDiscord($currentId, $discordId, $avatar);
                    $claimed = true;
                } else {
                    $this->users->attachDiscord($currentId, $discordId, $avatar);
                    $linked = true;
                }
            }
            $user = $currentId > 0 ? $this->users->findById($currentId) : null;
        }

        // Case 1: discord_id already exists — sign that member in (and claim if still a guest).
        if (!$user) {
            $user = $this->users->findByDiscordId($discordId);
            if ($user && (int) ($user['is_guest'] ?? 0) === 1) {
                $this->users->claimWithDiscord((int) $user['id'], $discordId, $avatar);
                $claimed = true;
                $user = $this->users->findById((int) $user['id']);
            }
        }

        // Case 2: no discord_id match, but Discord email matches an existing users row (guest or registered).
        if (!$user && $email !== '') {
            $existing = $this->users->findByEmail($email);
            if ($existing) {
                $existingDiscord = trim((string) ($existing['discord_id'] ?? ''));
                if ($existingDiscord !== '' && $existingDiscord !== $discordId) {
                    return ['ok' => false, 'error' => 'That email is already linked to another Discord account. Sign in with email.'];
                }
                if ((int) ($existing['is_guest'] ?? 0) === 1) {
                    $this->users->claimWithDiscord((int) $existing['id'], $discordId, $avatar);
                    $claimed = true;
                } else {
                    $this->users->attachDiscord((int) $existing['id'], $discordId, $avatar);
                    $linked = true;
                }
                $user = $this->users->findById((int) $existing['id']);
            }
        }

        // Case 3: neither discord_id nor email exists — create a free member.
        if (!$user) {
            $display = trim((string) ($profile['username'] ?? $profile['handle'] ?? 'Discord'));
            if ($display === '') {
                $display = 'Discord';
            }
            $parts = split_person_name($display);
            $now = date('Y-m-d H:i:s');
            $row = [
                'first_name' => $parts['first_name'],
                'last_name' => $parts['last_name'],
                'email' => $email !== '' ? $email : ('discord_' . $discordId . '@oauth.orionbets.local'),
                'discord_id' => $discordId,
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'timezone' => 'UTC',
                'theme_preference' => 'system',
                'is_active' => 1,
                'is_guest' => 0,
                'email_verified_at' => $now,
                'age_confirmed_at' => $now,
                'terms_accepted_at' => $now,
                'privacy_accepted_at' => $now,
            ];
            if ($avatar !== null && $this->db->columnExists('users', 'avatar')) {
                $row['avatar'] = $avatar;
            }
            try {
                $id = $this->users->create($row);
            } catch (\Throwable $e) {
                Logger::error('Discord registration failed', ['error' => $e->getMessage()]);
                return ['ok' => false, 'error' => 'Could not create an account from Discord. Sign in with email or try again.'];
            }
            $this->users->assignRole($id, 'user');
            $this->seedPreferences($id);
            $user = $this->users->findById($id);
            $created = true;

            try {
                (new BeehiivService())->subscribeAndSendWelcome(
                    (string) ($user['email'] ?? ''),
                    (string) ($user['first_name'] ?? ''),
                    (string) ($user['last_name'] ?? '')
                );
            } catch (\Throwable $e) {
                Logger::error('Beehiiv Discord OAuth registration trigger exception', ['error' => $e->getMessage()]);
            }
        }

        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'This account is disabled.'];
        }

        $updates = [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip(),
        ];
        if ($avatar !== null && $this->db->columnExists('users', 'avatar') && (string) ($user['avatar'] ?? '') !== $avatar) {
            $updates['avatar'] = $avatar;
        }
        $this->users->update((int) $user['id'], $updates);
        $this->users->assignRole((int) $user['id'], 'user');

        $action = $created
            ? 'discord_registration'
            : ($claimed ? 'guest_claimed' : ($linked ? 'discord_linked' : 'discord_login'));
        $this->audit->log((int) $user['id'], $action, 'user', (string) $user['id'], $request, [
            'discord_id' => $discordId,
            'channel' => 'discord',
            'claimed' => $claimed,
        ]);

        $fresh = $this->users->findById((int) $user['id']);
        return ['ok' => true, 'claimed' => $claimed, 'created' => $created, 'linked' => $linked, 'user' => $fresh ?? $user];
    }

    private function discordAvatarUrl(string $discordId, string $hash): ?string
    {
        $discordId = trim($discordId);
        $hash = trim($hash);
        if ($discordId === '' || $hash === '' || !preg_match('/^[0-9]+$/', $discordId) || !preg_match('/^[a-zA-Z0-9_]+$/', $hash)) {
            return null;
        }

        $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
        return 'https://cdn.discordapp.com/avatars/' . $discordId . '/' . $hash . '.' . $ext;
    }

    private function seedPreferences(int $userId): void
    {
        foreach (['email', 'in_app'] as $channel) {
            foreach (['daily_pick', 'pick_result', 'subscription', 'account'] as $event) {
                $this->db->insert('notification_preferences', [
                    'user_id' => $userId,
                    'channel' => $channel,
                    'event_type' => $event,
                    'enabled' => 1,
                ]);
            }
        }
    }
}
