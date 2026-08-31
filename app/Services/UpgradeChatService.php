<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use RuntimeException;

final class UpgradeChatService
{
    private const API = 'https://api.upgrade.chat';

    public function parseWebhook(array $payload): array
    {
        $event = $payload;
        if (isset($payload['data']) && is_array($payload['data']) && !isset($payload['body']) && !isset($payload['uuid'])) {
            $event = $payload['data'];
        }

        $type = strtolower((string) ($event['type'] ?? $payload['type'] ?? $payload['event'] ?? ''));
        $eventId = (string) ($event['id'] ?? $payload['id'] ?? $payload['event_id'] ?? '');
        $body = $event['body'] ?? $event['order'] ?? $payload['order'] ?? $event;
        if (!is_array($body)) {
            $body = $event;
        }
        if (isset($body['order']) && is_array($body['order']) && !isset($body['order_items']) && !isset($body['uuid'])) {
            $body = array_merge($body, $body['order']);
        }
        if (isset($body['latest_order']) && is_array($body['latest_order'])) {
            $body = array_merge($body, $body['latest_order']);
        }

        $order = $this->normalizeOrder($body);
        $order['event_type'] = $type !== '' ? $type : ($order['uuid'] !== '' ? 'order.created' : '');
        $order['event_id'] = $eventId;
        if (str_starts_with($type, 'subscription')) {
            $order['is_subscription'] = true;
        }

        return $order;
    }

    public function normalizeOrder(array $order): array
    {
        $user = is_array($order['user'] ?? null) ? $order['user'] : [];
        $items = is_array($order['order_items'] ?? null) ? $order['order_items'] : [];
        $firstItem = is_array($items[0] ?? null) ? $items[0] : [];
        $product = is_array($firstItem['product'] ?? null) ? $firstItem['product'] : [];

        $uuid = (string) ($order['uuid'] ?? $order['id'] ?? $order['order_id'] ?? '');
        $email = strtolower(trim((string) (
            $user['email']
            ?? $order['email']
            ?? $order['customer_email']
            ?? $order['user_email']
            ?? ''
        )));
        $name = trim((string) (
            $user['username']
            ?? $user['name']
            ?? $order['name']
            ?? $order['customer_name']
            ?? ''
        ));
        $productId = strtolower((string) ($product['uuid'] ?? $order['product_id'] ?? $order['productUuid'] ?? ''));
        $txn = (string) (
            $order['payment_processor_record_id']
            ?? $order['transaction_id']
            ?? $order['charge_id']
            ?? $uuid
        );
        $total = $order['total'] ?? $order['amount'] ?? $firstItem['price'] ?? 0;
        $status = strtolower((string) ($order['status'] ?? ''));
        if ($status === '' && !empty($order['cancelled_at'])) {
            $status = 'cancelled';
        }
        if ($status === '' && $uuid !== '') {
            $status = 'completed';
        }

        $meta = is_array($order['metadata'] ?? null) ? $order['metadata'] : [];
        $custom = is_array($order['custom'] ?? null) ? $order['custom'] : [];
        $efTid = trim((string) (
            $order['ef_transaction_id']
            ?? $order['_ef_transaction_id']
            ?? $meta['ef_transaction_id']
            ?? $custom['ef_transaction_id']
            ?? $order['adv1']
            ?? $meta['adv1']
            ?? ''
        ));

        return [
            'uuid' => $uuid,
            'email' => $email,
            'name' => $name,
            'product_id' => $productId,
            'product_name' => (string) ($product['name'] ?? $order['product_name'] ?? ''),
            'processor' => strtolower((string) ($order['payment_processor'] ?? $order['processor'] ?? 'upgradechat')),
            'transaction_id' => $txn,
            'total' => is_numeric($total) ? (float) $total : 0.0,
            'is_subscription' => !empty($order['is_subscription']) || !empty($order['subscription_id']),
            'cancelled_at' => $order['cancelled_at'] ?? null,
            'status' => $status !== '' ? $status : 'completed',
            'currency' => strtoupper((string) ($order['currency'] ?? $order['currency_code'] ?? 'USD')) ?: 'USD',
            'ef_transaction_id' => $efTid,
            'metadata' => $meta !== [] ? $meta : $custom,
            'raw' => $order,
        ];
    }

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function assertCheckoutProduct(string $url): void
    {
        $productId = strtolower(upgrade_chat_product_id($url));
        if ($productId === '') {
            throw new RuntimeException('This plan needs a full Upgrade.Chat product checkout URL. It must look like https://upgrade.chat/STORE_ID/p/PRODUCT_ID.');
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? 'upgrade.chat'));
        $path = (string) ($parts['path'] ?? '/');
        $clean = 'https://' . $host . $path;
        $html = $this->fetchStoreHtml($clean);
        if ($html === '') {
            return;
        }
        if (!preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            return;
        }
        $data = json_decode($match[1], true);
        $products = is_array($data) ? ($data['props']['pageProps']['account']['products'] ?? null) : null;
        if (!is_array($products) || $products === []) {
            return;
        }
        foreach ($products as $product) {
            if (strtolower((string) ($product['uuid'] ?? '')) === $productId) {
                return;
            }
        }

        throw new RuntimeException('Upgrade.Chat does not have this product. Open the product in Upgrade.Chat, copy its checkout URL, and paste it on Admin → Subscriptions for this plan.');
    }

    public function webhookSecret(): string
    {
        return trim((string) Env::get('UPGRADECHAT_WEBHOOK_SECRET', ''));
    }

    public function authorized(string $headerSecret, string $querySecret, string $raw = '', string $signature = ''): bool
    {
        $secret = $this->webhookSecret();
        if ($secret === '') {
            return true;
        }

        if ($headerSecret !== '' && hash_equals($secret, $headerSecret)) {
            return true;
        }
        if ($querySecret !== '' && hash_equals($secret, $querySecret)) {
            return true;
        }
        if ($signature !== '' && $raw !== '') {
            $sig = trim($signature);
            if (str_starts_with(strtolower($sig), 'sha256=')) {
                $sig = substr($sig, 7);
            }
            $expected = hash_hmac('sha256', $raw, $secret);
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    public function findOrderByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !$this->configured()) {
            return null;
        }

        $orders = $this->request('GET', '/v1/orders?limit=50');
        $rows = is_array($orders['data'] ?? null) ? $orders['data'] : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeOrder($row);
            if ($normalized['email'] === $email && $normalized['uuid'] !== '') {
                return $normalized;
            }
        }

        return null;
    }

    public function getOrder(string $uuid): ?array
    {
        if ($uuid === '' || !$this->configured()) {
            return null;
        }

        $res = $this->request('GET', '/v1/orders/' . rawurlencode($uuid));
        $row = is_array($res['data'] ?? null) ? $res['data'] : null;
        return $row ? $this->normalizeOrder($row) : null;
    }

    public function validateEvent(string $eventId): bool
    {
        if ($eventId === '' || !$this->configured()) {
            return false;
        }

        $res = $this->request('GET', '/v1/webhook-events/' . rawurlencode($eventId) . '/validate');
        return !empty($res['valid']);
    }

    private function fetchStoreHtml(string $url): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'User-Agent: Mozilla/5.0 (compatible; OrionBetsCheckout/1.0)',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return '';
        }

        return (string) $body;
    }

    private function clientId(): string
    {
        return trim((string) Env::get('UPGRADECHAT_CLIENT_ID', ''));
    }

    private function clientSecret(): string
    {
        return trim((string) Env::get('UPGRADECHAT_CLIENT_SECRET', ''));
    }

    private function request(string $method, string $path): array
    {
        $token = $this->token();
        if ($token === '') {
            return [];
        }

        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            Logger::warning('Upgrade.Chat API request failed', ['path' => $path, 'code' => $code]);
            return [];
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function token(): string
    {
        $id = $this->clientId();
        $secret = $this->clientSecret();
        if ($id === '' || $secret === '') {
            return '';
        }

        $ch = curl_init(self::API . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $id,
                'client_secret' => $secret,
            ]),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            Logger::warning('Upgrade.Chat OAuth failed', ['code' => $code]);
            return '';
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? (string) ($decoded['access_token'] ?? '') : '';
    }
}
