<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Mailer;
use App\Core\Request;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;

final class SubscriptionService
{
    public function __construct(
        private Database $db,
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private UserRepository $users,
        private NotificationService $notifications,
        private AuditService $audit,
        private Mailer $mailer
    ) {
    }

    public function subscribe(int $userId, int $planId, Request $request): void
    {
        $plan = $this->plans->find($planId);
        if (!$plan || !(int) $plan['is_active']) {
            throw new \RuntimeException('That plan is not available.');
        }

        $current = $this->subscriptions->currentForUser($userId);
        if ($current && in_array($current['status'], ['active', 'trialing'], true)) {
            $this->subscriptions->update((int) $current['id'], [
                'status' => 'superseded',
                'ends_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $starts = date('Y-m-d H:i:s');
        $renews = date('Y-m-d H:i:s', strtotime('+1 ' . ($plan['billing_interval'] === 'year' ? 'year' : 'month')));

        $subId = $this->subscriptions->create([
            'user_id' => $userId,
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => $starts,
            'ends_at' => $renews,
            'renews_at' => $renews,
            'provider' => 'demo',
            'provider_subscription_id' => 'demo_' . bin2hex(random_bytes(6)),
        ]);

        $this->subscriptions->addTransaction([
            'subscription_id' => $subId,
            'user_id' => $userId,
            'amount_cents' => (int) $plan['price_cents'],
            'currency' => $plan['currency'],
            'status' => 'completed',
            'provider' => 'demo',
            'provider_transaction_id' => 'demo_tx_' . bin2hex(random_bytes(4)),
            'description' => 'Demo activation — no live payment processed · ' . $plan['name'],
        ]);

        if ($plan['slug'] !== 'free') {
            $this->users->assignRole($userId, 'premium_user');
        }

        $user = $this->users->findById($userId);
        $this->mailer->send($user['email'], 'Your Orion Bets subscription', 'subscription', [
            'user' => $user,
            'title' => 'Subscription confirmed',
            'body' => 'Your ' . $plan['name'] . ' plan is active in demo mode. A live billing provider can be connected later.',
        ]);
        $this->notifications->send($userId, 'subscription', 'Plan updated', $plan['name'] . ' is now active (demo billing).');
        $this->audit->log($userId, 'subscription_changed', 'subscription', (string) $subId, $request, ['plan' => $plan['slug']]);
    }

    public function cancel(int $userId, Request $request, ?string $reason = null): void
    {
        $current = $this->subscriptions->currentForUser($userId);
        if (!$current) {
            return;
        }

        $this->subscriptions->update((int) $current['id'], [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => $reason,
        ]);

        $user = $this->users->findById($userId);
        $this->mailer->send($user['email'], 'Subscription cancelled', 'cancel', [
            'user' => $user,
            'title' => 'Subscription cancelled',
            'body' => 'Your Playbook access remains until the current period ends in this demo.',
        ]);
        $this->notifications->send($userId, 'subscription', 'Subscription cancelled', 'Your plan was cancelled.');
        $this->audit->log($userId, 'subscription_cancelled', 'subscription', (string) $current['id'], $request);
    }
}
