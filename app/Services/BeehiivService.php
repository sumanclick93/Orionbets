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
        $this->apiKey = trim($apiKey ?? (string) (Env::get('BEEHIIV_API_KEY') ?: config('app.beehiiv.api_key', '') ?: ''));
        $this->publicationId = trim($publicationId ?? (string) (Env::get('BEEHIIV_PUBLICATION_ID') ?: config('app.beehiiv.publication_id', '') ?: ''));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->publicationId !== '';
    }

    /**
     * Backward-compatible helper method.
     */
    public function subscribeAndSendWelcome(string $email, ?string $firstName = null, ?string $lastName = null): bool
    {
        return $this->syncSubscriber(
            email: $email,
            sendWelcomeEmail: false,
            tier: 'free_member',
            firstName: $firstName,
            lastName: $lastName,
            utmSource: 'website_registration'
        );
    }

    /**
     * Sync subscriber with Beehiiv REST API v2.
     *
     * @param string $email Subscriber email address
     * @param bool $sendWelcomeEmail Trigger welcome email (true for confirmed purchases)
     * @param string $tier Segmentation tier ('free_member' or 'paid_member')
     * @param string|null $firstName Subscriber first name
     * @param string|null $lastName Subscriber last name
     * @param string $utmSource Ingestion origin tag
     * @return bool True if API sync succeeded, false otherwise
     */
    public function syncSubscriber(
        string $email,
        bool $sendWelcomeEmail = false,
        string $tier = 'free_member',
        ?string $firstName = null,
        ?string $lastName = null,
        string $utmSource = 'website'
    ): bool {
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

        $isPaid = ($tier === 'paid_member' || $tier === 'premium');
        $beehiivTier = $isPaid ? 'premium' : 'free';
        $tags = $isPaid ? ['paid_member', 'picks_subscriber'] : ['free_member'];

        $customFields = [];
        $first = trim((string) $firstName);
        $last = trim((string) $lastName);

        if ($first !== '') {
            $customFields[] = ['name' => 'First Name', 'value' => $first];
        }
        if ($last !== '') {
            $customFields[] = ['name' => 'Last Name', 'value' => $last];
        }
        $customFields[] = ['name' => 'Membership Tier', 'value' => $tier];

        $payload = [
            'email' => $email,
            'reactivate_existing' => true,
            'send_welcome_email' => $sendWelcomeEmail,
            'utm_source' => $utmSource,
            'tier' => $beehiivTier,
            'tags' => $tags,
            'custom_fields' => $customFields,
        ];

        $jsonPayload = json_encode($payload);

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        try {
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
                    'tier' => $tier,
                    'welcome_email' => $sendWelcomeEmail,
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
        } catch (\Throwable $e) {
            Logger::error('Beehiiv subscriber sync exception', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
