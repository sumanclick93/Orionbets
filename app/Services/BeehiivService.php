<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;

final class BeehiivService
{
    private string $apiKey;
    private string $publicationId;

    public function __construct(?string $apiKey = null, ?string $publicationId = null)
    {
        $this->apiKey = trim($apiKey ?? (string) Env::get('BEEHIIV_API_KEY', ''));
        $this->publicationId = trim($publicationId ?? (string) Env::get('BEEHIIV_PUBLICATION_ID', ''));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->publicationId !== '';
    }

    /**
     * Trigger Welcome Email and subscriber sync on Beehiiv via API v2.
     *
     * @param string $email Subscriber email address
     * @param string|null $firstName Subscriber first name
     * @param string|null $lastName Subscriber last name
     * @return bool True if successful, false otherwise
     */
    public function subscribeAndSendWelcome(string $email, ?string $firstName = null, ?string $lastName = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('Beehiiv subscribe skipped: invalid email address', ['email' => $email]);
            return false;
        }

        if (!$this->isConfigured()) {
            Logger::info('Beehiiv subscribe skipped: API key or publication ID not configured', [
                'email' => $email,
            ]);
            return false;
        }

        $url = 'https://api.beehiiv.com/v2/publications/' . rawurlencode($this->publicationId) . '/subscriptions';

        $payload = [
            'email' => $email,
            'reactivate_existing' => true,
            'send_welcome_email' => true,
            'utm_source' => 'website_registration',
        ];

        $first = trim($firstName ?? '');
        $last = trim($lastName ?? '');

        if ($first !== '') {
            $payload['first_name'] = $first;
        }
        if ($last !== '') {
            $payload['last_name'] = $last;
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $jsonPayload = json_encode($payload);

        $ch = curl_init($url);
        if ($ch === false) {
            Logger::error('Beehiiv subscribe failed: cURL initialization error');
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            Logger::error('Beehiiv API cURL request failed', [
                'email' => $email,
                'error' => $curlError,
            ]);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            Logger::info('Beehiiv subscriber sync successful', [
                'email' => $email,
                'status_code' => $httpCode,
            ]);
            return true;
        }

        Logger::error('Beehiiv API returned non-success response', [
            'email' => $email,
            'status_code' => $httpCode,
            'response' => $response,
        ]);

        return false;
    }
}
