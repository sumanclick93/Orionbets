<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CheckoutRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;

final class MemberHistoryService
{
    public function __construct(
        private Database $db,
        private UserRepository $users,
        private SubscriptionRepository $subscriptions,
        private CheckoutRepository $checkouts
    ) {
    }

    public static function make(Database $db): self
    {
        return new self(
            $db,
            new UserRepository($db),
            new SubscriptionRepository($db),
            new CheckoutRepository($db)
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array{
     *   registered_email:string,
     *   paypal_emails:list<string>,
     *   checkout_emails:list<string>,
     *   purchases:list<array<string,mixed>>,
     *   subscriptions:list<array<string,mixed>>,
     *   timeline:list<array<string,mixed>>
     * }
     */
    public function dossier(array $user): array
    {
        $userId = (int) $user['id'];
        $registeredEmail = $this->users->originalEmail($user);
        $checkouts = $this->checkouts->forMember(
            $userId,
            [$registeredEmail, (string) ($user['email'] ?? '')],
            (string) ($user['checkout_cookie'] ?? '')
        );
        $transactions = $this->subscriptions->transactions($userId);
        $subscriptions = $this->subscriptions->forUser($userId);
        $logs = $this->auditRows($userId);
        $claimedAt = $this->claimedAt($user, $logs);

        $purchases = $this->purchases($transactions, $checkouts, $registeredEmail, $claimedAt);
        $paypalEmails = [];
        $checkoutEmails = [];
        foreach ($purchases as $purchase) {
            if (($purchase['checkout_email'] ?? '') !== '') {
                $checkoutEmails[] = (string) $purchase['checkout_email'];
            }
            if (($purchase['paypal_email'] ?? '') !== '') {
                $paypalEmails[] = (string) $purchase['paypal_email'];
            }
        }
        foreach ($checkouts as $session) {
            $email = strtolower(trim((string) ($session['email'] ?? '')));
            if ($email !== '') {
                $checkoutEmails[] = $email;
            }
        }

        return [
            'registered_email' => $registeredEmail,
            'paypal_emails' => array_values(array_unique($paypalEmails)),
            'checkout_emails' => array_values(array_unique($checkoutEmails)),
            'purchases' => $purchases,
            'subscriptions' => $subscriptions,
            'timeline' => $this->timeline($user, $purchases, $checkouts, $logs, $registeredEmail),
        ];
    }

    /**
     * @param list<array<string, mixed>> $transactions
     * @param list<array<string, mixed>> $checkouts
     * @return list<array<string, mixed>>
     */
    private function purchases(array $transactions, array $checkouts, string $registeredEmail, ?string $claimedAt): array
    {
        $sessionsByOrder = [];
        foreach ($checkouts as $session) {
            $orderId = trim((string) ($session['provider_order_id'] ?? ''));
            if ($orderId !== '') {
                $sessionsByOrder[$orderId] = $session;
            }
        }

        $usedSessions = [];
        $rows = [];
        foreach ($transactions as $tx) {
            $payload = $this->decode($tx['payload'] ?? null);
            $orderId = trim((string) ($payload['order_id'] ?? $tx['provider_transaction_id'] ?? ''));
            $session = $orderId !== '' ? ($sessionsByOrder[$orderId] ?? null) : null;
            if ($session) {
                $usedSessions[(int) $session['id']] = true;
            }
            $provider = $this->providerLabel((string) ($tx['provider'] ?? $session['provider'] ?? ''));
            $at = (string) ($tx['created_at'] ?? '');
            $checkoutEmail = strtolower(trim((string) ($payload['email'] ?? $session['email'] ?? $registeredEmail)));
            $paypalEmail = strtolower(trim((string) ($payload['paypal_payer_email'] ?? '')));
            $rows[] = [
                'at' => $at,
                'amount_cents' => (int) ($tx['amount_cents'] ?? 0),
                'currency' => (string) ($tx['currency'] ?? 'USD'),
                'status' => (string) ($tx['status'] ?? ''),
                'provider' => $provider,
                'provider_key' => strtolower((string) ($tx['provider'] ?? $session['provider'] ?? '')),
                'mode' => $this->purchaseMode($at, $claimedAt),
                'checkout_email' => $checkoutEmail,
                'paypal_email' => $paypalEmail,
                'order_id' => $orderId,
                'plan_name' => (string) ($session['plan_name'] ?? ''),
                'description' => (string) ($tx['description'] ?? ''),
            ];
        }

        foreach ($checkouts as $session) {
            if (!empty($usedSessions[(int) $session['id']])) {
                continue;
            }
            if (($session['status'] ?? '') !== 'completed') {
                continue;
            }
            $at = (string) ($session['completed_at'] ?? $session['created_at'] ?? '');
            $payload = $this->decode($session['payload'] ?? null);
            $rows[] = [
                'at' => $at,
                'amount_cents' => 0,
                'currency' => 'USD',
                'status' => 'completed',
                'provider' => $this->providerLabel((string) ($session['provider'] ?? '')),
                'provider_key' => strtolower((string) ($session['provider'] ?? '')),
                'mode' => $this->purchaseMode($at, $claimedAt),
                'checkout_email' => strtolower(trim((string) ($session['email'] ?? $registeredEmail))),
                'paypal_email' => strtolower(trim((string) ($payload['paypal_payer_email'] ?? ''))),
                'order_id' => (string) ($session['provider_order_id'] ?? ''),
                'plan_name' => (string) ($session['plan_name'] ?? ''),
                'description' => 'Checkout completed',
            ];
        }

        usort($rows, static fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return $rows;
    }

    /**
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $purchases
     * @param list<array<string, mixed>> $checkouts
     * @param list<array<string, mixed>> $logs
     * @return list<array<string, mixed>>
     */
    private function timeline(array $user, array $purchases, array $checkouts, array $logs, string $registeredEmail): array
    {
        $events = [];
        $startedAsGuest = $this->startedAsGuest($user, $logs);
        $events[] = [
            'at' => (string) ($user['created_at'] ?? ''),
            'title' => $startedAsGuest ? 'Guest account created' : 'Account registered',
            'detail' => $registeredEmail,
            'tone' => 'account',
        ];

        foreach ($purchases as $purchase) {
            $bits = [
                $purchase['mode'] === 'guest' ? 'Guest checkout' : 'Signed-in / registered checkout',
                $purchase['provider'],
            ];
            if ($purchase['plan_name'] !== '') {
                $bits[] = $purchase['plan_name'];
            }
            if ((int) $purchase['amount_cents'] > 0) {
                $bits[] = money((int) $purchase['amount_cents'], (string) $purchase['currency']);
            }
            if ($purchase['checkout_email'] !== '') {
                $bits[] = 'Registered/checkout email ' . $purchase['checkout_email'];
            }
            if ($purchase['paypal_email'] !== '') {
                $bits[] = 'PayPal wallet ' . $purchase['paypal_email'];
            }
            if ($purchase['order_id'] !== '') {
                $bits[] = 'Order ' . $purchase['order_id'];
            }
            $events[] = [
                'at' => (string) $purchase['at'],
                'title' => 'Purchase via ' . $purchase['provider'],
                'detail' => implode(' · ', array_filter($bits)),
                'tone' => 'purchase',
            ];
        }

        foreach ($checkouts as $session) {
            $status = (string) ($session['status'] ?? '');
            if ($status === 'completed') {
                continue;
            }
            $events[] = [
                'at' => (string) ($session['created_at'] ?? ''),
                'title' => 'Checkout started · ' . $this->providerLabel((string) ($session['provider'] ?? '')),
                'detail' => trim((string) ($session['email'] ?? '') . ' · ' . $status . ' · ' . (string) ($session['plan_name'] ?? '')),
                'tone' => 'checkout',
            ];
        }

        $labels = [
            'guest_created' => 'Guest checkout row created',
            'guest_claimed' => 'Guest account claimed',
            'registration' => 'Email registration completed',
            'discord_registration' => 'Discord account created',
            'discord_linked' => 'Discord linked to this account',
            'discord_login' => 'Signed in with Discord',
            'login' => 'Signed in with email',
            'checkout_completed' => 'Payment recorded',
            'user_suspended' => 'Account suspended',
            'user_unsuspended' => 'Account reinstated',
            'user_deleted' => 'Account soft-deleted',
            'user_restored' => 'Account restored',
            'user_updated' => 'Member record updated',
            'password_changed' => 'Password changed',
            'account_deleted' => 'Member deleted their account',
        ];
        foreach ($logs as $log) {
            $action = (string) ($log['action'] ?? '');
            if (in_array($action, ['checkout_completed', 'login', 'discord_login'], true)) {
                continue;
            }
            $meta = $this->decode($log['metadata'] ?? null);
            $detail = $labels[$action] ?? str_replace('_', ' ', $action);
            if (!empty($meta['channel'])) {
                $detail .= ' · ' . $meta['channel'];
            }
            if (!empty($meta['original_email'])) {
                $detail .= ' · ' . $meta['original_email'];
            }
            $events[] = [
                'at' => (string) ($log['created_at'] ?? ''),
                'title' => $labels[$action] ?? ucfirst(str_replace('_', ' ', $action)),
                'detail' => $detail,
                'tone' => str_contains($action, 'delete') || str_contains($action, 'suspend') ? 'alert' : 'account',
            ];
        }

        if (!empty($user['last_login_at'])) {
            $events[] = [
                'at' => (string) $user['last_login_at'],
                'title' => 'Last login',
                'detail' => (string) ($user['last_login_ip'] ?? ''),
                'tone' => 'account',
            ];
        }

        usort($events, static fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        $seen = [];
        $unique = [];
        foreach ($events as $event) {
            $key = $event['at'] . '|' . $event['title'] . '|' . $event['detail'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $event;
        }

        return $unique;
    }

    /**
     * @param list<array<string, mixed>> $logs
     */
    private function startedAsGuest(array $user, array $logs): bool
    {
        if ((int) ($user['is_guest'] ?? 0) === 1) {
            return true;
        }
        foreach ($logs as $log) {
            if (($log['action'] ?? '') === 'guest_created' || ($log['action'] ?? '') === 'guest_claimed') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<string, mixed>> $logs
     */
    private function claimedAt(array $user, array $logs): ?string
    {
        $times = [];
        foreach ($logs as $log) {
            if (in_array($log['action'] ?? '', ['guest_claimed', 'registration', 'discord_registration'], true)) {
                $times[] = (string) $log['created_at'];
            }
        }
        if ($times === []) {
            return !empty($user['is_guest']) ? null : (string) ($user['created_at'] ?? null);
        }
        sort($times);
        return $times[0] ?? null;
    }

    private function purchaseMode(string $at, ?string $claimedAt): string
    {
        if ($claimedAt === null || $claimedAt === '') {
            return 'guest';
        }
        return $at !== '' && $at < $claimedAt ? 'guest' : 'member';
    }

    private function providerLabel(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return match ($provider) {
            'paypal' => 'PayPal',
            'upgradechat', 'upgrade.chat', 'upgrade_chat' => 'Upgrade.Chat',
            'demo' => 'Demo',
            '' => 'Unknown',
            default => ucfirst($provider),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditRows(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs
             WHERE user_id = :id OR (entity_type = 'user' AND entity_id = :sid)
             ORDER BY created_at DESC
             LIMIT 80",
            ['id' => $userId, 'sid' => (string) $userId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
