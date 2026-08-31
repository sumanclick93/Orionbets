<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;

final class DiscordService
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';
    private const PROFILE_URL = 'https://discord.com/api/users/@me';
    private const SCOPES = 'identify email';

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'prompt' => 'consent',
        ]);
    }

    public function getAccessToken(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || !$this->configured()) {
            return null;
        }

        $decoded = $this->request('POST', self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($decoded === null || empty($decoded['access_token'])) {
            return null;
        }

        return $decoded;
    }

    public function getUserProfile(string $accessToken): ?array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return null;
        }

        $decoded = $this->request('GET', self::PROFILE_URL, null, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if ($decoded === null || empty($decoded['id'])) {
            return null;
        }

        return [
            'id' => (string) $decoded['id'],
            'username' => (string) ($decoded['global_name'] ?? $decoded['username'] ?? ''),
            'handle' => (string) ($decoded['username'] ?? ''),
            'email' => strtolower(trim((string) ($decoded['email'] ?? ''))),
            'avatar' => (string) ($decoded['avatar'] ?? ''),
            'verified' => !empty($decoded['verified']),
        ];
    }

    private function clientId(): string
    {
        return trim((string) (Env::get('DISCORD_CLIENT_ID') ?: config('app.discord.client_id', '') ?: ''));
    }

    private function clientSecret(): string
    {
        return trim((string) (Env::get('DISCORD_CLIENT_SECRET') ?: config('app.discord.client_secret', '') ?: ''));
    }

    private function redirectUri(): string
    {
        $configured = trim((string) (Env::get('DISCORD_REDIRECT_URI') ?: config('app.discord.redirect_uri', '') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        return url('/auth/discord/callback');
    }

    /**
     * @param array<string, string>|null $form
     * @param list<string> $headers
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $url, ?array $form = null, array $headers = []): ?array
    {
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: OrionBets (https://orionbets.co, 1.0)';

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form);
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            Logger::warning('Discord API request failed', [
                'url' => $url,
                'code' => $code,
                'error' => $error,
            ]);
            return null;
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
