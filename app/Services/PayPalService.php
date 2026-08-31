<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use RuntimeException;

final class PayPalService
{
    private const LIVE_API = 'https://api-m.paypal.com';
    private const SANDBOX_API = 'https://api-m.sandbox.paypal.com';

    private ?array $tokenCache = null;

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function clientId(): string
    {
        return trim((string) (Env::get('PAYPAL_CLIENT_ID') ?: config('app.paypal.client_id', '') ?: ''));
    }

    public function environment(): string
    {
        $env = strtolower(trim((string) (Env::get('PAYPAL_ENV') ?: config('app.paypal.env', 'sandbox') ?: 'sandbox')));
        return $env === 'live' ? 'live' : 'sandbox';
    }

    public function getAccessToken(): string
    {
        if ($this->tokenCache && time() < (int) ($this->tokenCache['expires_at'] ?? 0)) {
            return (string) $this->tokenCache['access_token'];
        }

        if (!$this->configured()) {
            throw new RuntimeException('PayPal is not configured.');
        }

        $response = $this->request('POST', '/v1/oauth2/token', [
            'form' => ['grant_type' => 'client_credentials'],
            'basic' => true,
        ]);

        $token = trim((string) ($response['access_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('PayPal could not issue an access token.');
        }

        $this->tokenCache = [
            'access_token' => $token,
            'expires_at' => time() + max(30, ((int) ($response['expires_in'] ?? 300)) - 60),
        ];

        return $token;
    }

    /**
     * @param array{name?:string,price_cents?:int|string,currency?:string,billing_interval?:string} $planData
     * @param array{email?:string,first_name?:string,last_name?:string,custom_id?:string} $customerData
     * @return array<string, mixed>
     */
    public function createOrder(array $planData, array $customerData): array
    {
        $amount = number_format(((int) ($planData['price_cents'] ?? 0)) / 100, 2, '.', '');
        if ((float) $amount <= 0) {
            throw new RuntimeException('This plan does not have a payable amount.');
        }

        $currency = strtoupper(trim((string) ($planData['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        $customId = trim((string) ($customerData['custom_id'] ?? ''));
        $description = trim((string) ($planData['name'] ?? 'Orion Bets plan'));
        if ($description === '') {
            $description = 'Orion Bets plan';
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'description' => substr($description, 0, 127),
                'custom_id' => $customId !== '' ? substr($customId, 0, 127) : null,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $amount,
                ],
            ]],
            'application_context' => [
                'brand_name' => substr((string) (config('app.name') ?: 'Orion Bets'), 0, 127),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];

        if ($payload['purchase_units'][0]['custom_id'] === null) {
            unset($payload['purchase_units'][0]['custom_id']);
        }

        // Do not send payer.email_address. PayPal's hosted login belongs to the PayPal
        // wallet, which we cannot lock. Prefilling a different address also fails sandbox
        // captures. Orion Bets stores membership against the checkout-form email instead.

        $headers = [];
        if ($customId !== '') {
            $headers[] = 'PayPal-Request-Id: ' . $customId;
        }

        $order = $this->request('POST', '/v2/checkout/orders', [
            'json' => $payload,
            'headers' => $headers,
            'auth' => true,
        ]);

        $id = trim((string) ($order['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('PayPal did not return an order id.');
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new RuntimeException('PayPal order id is missing.');
        }

        $captured = $this->request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', [
            'json' => new \stdClass(),
            'headers' => ['Prefer: return=representation'],
            'auth' => true,
        ]);

        $status = strtoupper((string) ($captured['status'] ?? ''));
        if ($status === 'COMPLETED' || $status === 'APPROVED') {
            return $captured;
        }

        if ($status === 'ORDER_ALREADY_CAPTURED' || $this->alreadyCaptured($captured)) {
            return $this->getOrder($orderId);
        }

        throw new RuntimeException('PayPal did not complete this capture.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new RuntimeException('PayPal order id is missing.');
        }

        return $this->request('GET', '/v2/checkout/orders/' . rawurlencode($orderId), [
            'auth' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $order
     */
    public function captureId(array $order): string
    {
        $units = is_array($order['purchase_units'] ?? null) ? $order['purchase_units'] : [];
        foreach ($units as $unit) {
            $captures = is_array($unit['payments']['captures'] ?? null) ? $unit['payments']['captures'] : [];
            foreach ($captures as $capture) {
                $id = trim((string) ($capture['id'] ?? ''));
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return trim((string) ($order['id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $order
     */
    public function capturedAmount(array $order): array
    {
        $units = is_array($order['purchase_units'] ?? null) ? $order['purchase_units'] : [];
        foreach ($units as $unit) {
            $captures = is_array($unit['payments']['captures'] ?? null) ? $unit['payments']['captures'] : [];
            foreach ($captures as $capture) {
                $amount = is_array($capture['amount'] ?? null) ? $capture['amount'] : [];
                $value = (float) ($amount['value'] ?? 0);
                if ($value > 0) {
                    return [
                        'total' => $value,
                        'currency' => strtoupper((string) ($amount['currency_code'] ?? 'USD')),
                    ];
                }
            }
        }

        $amount = is_array($units[0]['amount'] ?? null) ? $units[0]['amount'] : [];
        return [
            'total' => (float) ($amount['value'] ?? 0),
            'currency' => strtoupper((string) ($amount['currency_code'] ?? 'USD')),
        ];
    }

    /**
     * @param array<string, mixed> $order
     */
    public function payerEmail(array $order, string $fallback = ''): string
    {
        $payer = is_array($order['payer'] ?? null) ? $order['payer'] : [];
        $email = strtolower(trim((string) ($payer['email_address'] ?? $payer['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $fallback;
    }

    private function clientSecret(): string
    {
        return trim((string) (Env::get('PAYPAL_CLIENT_SECRET') ?: config('app.paypal.client_secret', '') ?: ''));
    }

    private function apiBase(): string
    {
        return $this->environment() === 'live' ? self::LIVE_API : self::SANDBOX_API;
    }

    /**
     * @param array{form?:array<string,string>,json?:mixed,headers?:list<string>,auth?:bool,basic?:bool} $opts
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $opts = []): array
    {
        $url = $this->apiBase() . $path;
        $headers = array_values($opts['headers'] ?? []);
        $headers[] = 'Accept: application/json';

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('PayPal request could not start.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if (!empty($opts['basic'])) {
            $options[CURLOPT_USERPWD] = $this->clientId() . ':' . $this->clientSecret();
        } elseif (!empty($opts['auth'])) {
            $headers[] = 'Authorization: Bearer ' . $this->getAccessToken();
        }

        if (isset($opts['form'])) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_POSTFIELDS] = http_build_query($opts['form']);
        } elseif (array_key_exists('json', $opts)) {
            $headers[] = 'Content-Type: application/json';
            $encoded = json_encode($opts['json'], JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                curl_close($ch);
                throw new RuntimeException('PayPal request could not be encoded.');
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::error('PayPal API request failed', ['url' => $path, 'error' => $error, 'code' => $code]);
            throw new RuntimeException('PayPal could not be reached. Try again.');
        }

        $decoded = json_decode((string) $body, true);
        $payload = is_array($decoded) ? $decoded : [];

        if ($code >= 400) {
            $name = (string) ($payload['name'] ?? $payload['error'] ?? '');
            if ($name === 'UNPROCESSABLE_ENTITY' && $this->alreadyCaptured($payload)) {
                return $payload;
            }

            Logger::warning('PayPal API error', [
                'url' => $path,
                'code' => $code,
                'name' => $name,
                'message' => $payload['message'] ?? $payload['error_description'] ?? null,
            ]);

            throw new RuntimeException($this->publicError($payload, $code));
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function alreadyCaptured(array $payload): bool
    {
        $name = strtoupper((string) ($payload['name'] ?? ''));
        if ($name === 'ORDER_ALREADY_CAPTURED') {
            return true;
        }

        $details = is_array($payload['details'] ?? null) ? $payload['details'] : [];
        foreach ($details as $detail) {
            $issue = strtoupper((string) ($detail['issue'] ?? ''));
            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publicError(array $payload, int $code): string
    {
        $message = trim((string) ($payload['message'] ?? $payload['error_description'] ?? ''));
        if ($message !== '') {
            return 'PayPal: ' . $message;
        }

        return $code === 401
            ? 'PayPal credentials were rejected. Check PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.'
            : 'PayPal could not complete this payment. Try again.';
    }
}
