<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Request;
use App\Repositories\CheckoutRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Repositories\WebhookEventRepository;
use RuntimeException;

final class CheckoutService
{
    public function __construct(
        private Database $db,
        private CheckoutRepository $checkouts,
        private PlanRepository $plans,
        private SubscriptionRepository $subscriptions,
        private UserRepository $users,
        private WebhookEventRepository $webhooks,
        private UpgradeChatService $upgradeChat,
        private PayPalService $paypal,
        private NotificationService $notifications,
        private AuditService $audit,
        private Mailer $mailer
    ) {
    }

    public static function make(Database $db): self
    {
        $users = new UserRepository($db);
        $mailer = new Mailer();
        return new self(
            $db,
            new CheckoutRepository($db),
            new PlanRepository($db),
            new SubscriptionRepository($db),
            $users,
            new WebhookEventRepository($db),
            new UpgradeChatService(),
            new PayPalService(),
            new NotificationService($db, new NotificationRepository($db), $mailer, $users),
            new AuditService($db),
            $mailer
        );
    }

    public function browserId(Request $request): string
    {
        $id = (string) $request->cookie('orion_cid', '');
        if (!preg_match('/^[a-f0-9]{32,64}$/', $id)) {
            $id = bin2hex(random_bytes(16));
        }
        $this->writeCookie('orion_cid', $id, 86400 * 400);
        return $id;
    }

    public function start(array $input, Request $request, ?array $authUser): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $url = trim((string) ($input['url'] ?? $input['payment_url'] ?? ''));
        $planId = (int) ($input['plan_id'] ?? 0);
        $viaDiscord = strtolower(trim((string) ($input['channel'] ?? ''))) === 'discord';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email so this checkout can be matched.');
        }
        if ($name === '') {
            throw new RuntimeException('Enter the name that should appear on this checkout.');
        }
        if (!is_upgrade_chat_url($url)) {
            throw new RuntimeException('This plan needs a valid Upgrade.Chat payment link.');
        }
        $this->upgradeChat->assertCheckoutProduct($url);

        $plan = $planId > 0 ? $this->plans->find($planId) : $this->plans->findByPaymentUrl($url);
        if (!$plan && $planId > 0) {
            throw new RuntimeException('That plan is not available.');
        }

        $token = bin2hex(random_bytes(24));
        $cookie = $this->browserId($request);
        $this->writeCookie('orion_pay', $token, 86400 * 7);
        $everflow = EverflowService::make($this->db);
        $efTid = $everflow->ensureClick($request, $input + ['email' => $email]);
        $efTracking = $everflow->trackingFrom($request, $input);
        $everflow->rememberClick($efTid, $request, $email, $cookie);

        $row = [
            'token' => $token,
            'user_id' => $authUser['id'] ?? null,
            'plan_id' => $plan['id'] ?? null,
            'email' => $email,
            'name' => $name,
            'payment_url' => $url,
            'product_id' => upgrade_chat_product_id($url),
            'browser_cookie' => $cookie,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent(), 0, 255),
            'status' => 'pending',
            'provider' => 'upgradechat',
            'payload' => json_encode(['everflow' => $efTracking]),
        ];
        if ($this->db->columnExists('checkout_sessions', 'everflow_transaction_id')) {
            $row['everflow_transaction_id'] = $efTid !== '' ? $efTid : null;
        }

        $id = $this->checkouts->create($row);

        $session = $this->checkouts->findByToken($token);
        $payUrl = $this->checkoutUrl($url, $email, $name, $token, $cookie, $efTid, $viaDiscord);
        $frameUrl = url('checkout-frame.php') . '?u=' . rawurlencode($payUrl) . '&sid=' . rawurlencode($token) . '&r=' . rawurlencode(url('/thank-you?token=' . $token));
        if ($viaDiscord) {
            $frameUrl .= '&mode=discord';
        }

        Logger::info('Checkout started', [
            'id' => $id,
            'email' => $email,
            'plan_id' => $plan['id'] ?? null,
            'channel' => $viaDiscord ? 'discord' : 'upgradechat',
        ]);

        $this->everflowCheckoutStarted($everflow, $request, $token, $authUser, $email);

        return [
            'token' => $token,
            'pay_url' => $payUrl,
            'frame_url' => $frameUrl,
            'session' => $this->publicSession($session ?? ['token' => $token, 'status' => 'pending', 'email' => $email]),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $authUser
     * @return array{id:string,token:string,session:array<string,mixed>}
     */
    public function startPaypal(array $input, Request $request, ?array $authUser): array
    {
        if (!$this->paypal->configured()) {
            throw new RuntimeException('PayPal checkout is not configured.');
        }

        $planId = (int) ($input['plan_id'] ?? $input['planId'] ?? 0);
        $first = trim((string) ($input['first_name'] ?? $input['firstName'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? $input['lastName'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if ($authUser) {
            if ($first === '') {
                $first = trim((string) ($authUser['first_name'] ?? ''));
            }
            if ($last === '') {
                $last = trim((string) ($authUser['last_name'] ?? ''));
            }
            if ($email === '') {
                $email = strtolower(trim((string) ($authUser['email'] ?? '')));
            }
        }

        if ($planId < 1) {
            throw new RuntimeException('Choose a plan to pay with PayPal.');
        }
        if ($first === '') {
            throw new RuntimeException('Enter your first name.');
        }
        if ($last === '') {
            throw new RuntimeException('Enter your last name.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }

        $plan = $this->plans->find($planId);
        if (!$plan || (int) ($plan['is_active'] ?? 1) !== 1) {
            throw new RuntimeException('That plan is not available.');
        }
        if ((int) ($plan['price_cents'] ?? 0) <= 0) {
            throw new RuntimeException('This plan does not require a PayPal payment.');
        }

        $token = bin2hex(random_bytes(24));
        $cookie = $this->browserId($request);
        $this->writeCookie('orion_pay', $token, 86400 * 7);
        $everflow = EverflowService::make($this->db);
        $efTid = $everflow->ensureClick($request, $input + ['email' => $email]);
        $efTracking = $everflow->trackingFrom($request, $input);
        $everflow->rememberClick($efTid, $request, $email, $cookie);
        $name = trim($first . ' ' . $last);

        $row = [
            'token' => $token,
            'user_id' => $authUser['id'] ?? null,
            'plan_id' => $plan['id'],
            'email' => $email,
            'name' => $name,
            'payment_url' => 'paypal',
            'browser_cookie' => $cookie,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent(), 0, 255),
            'status' => 'pending',
            'provider' => 'paypal',
            'payload' => json_encode(['everflow' => $efTracking]),
        ];
        if ($this->db->columnExists('checkout_sessions', 'everflow_transaction_id')) {
            $row['everflow_transaction_id'] = $efTid !== '' ? $efTid : null;
        }

        $id = $this->checkouts->create($row);

        try {
            $order = $this->paypal->createOrder($plan, [
                'email' => $email,
                'first_name' => $first,
                'last_name' => $last,
                'custom_id' => $token,
            ]);
        } catch (\Throwable $e) {
            $this->checkouts->update($id, [
                'status' => 'failed',
                'payload' => json_encode(['error' => $e->getMessage()]),
            ]);
            throw $e;
        }
        $orderId = trim((string) ($order['id'] ?? ''));
        if ($orderId === '') {
            throw new RuntimeException('PayPal did not return an order id.');
        }

        $this->checkouts->update($id, [
            'provider_order_id' => $orderId,
            'payload' => json_encode([
                'paypal_order' => $order,
                'first_name' => $first,
                'last_name' => $last,
                'everflow' => $efTracking,
            ]),
        ]);

        Logger::info('PayPal checkout started', [
            'id' => $id,
            'email' => $email,
            'plan_id' => $plan['id'],
            'paypal_order_id' => $orderId,
        ]);

        $this->everflowCheckoutStarted($everflow, $request, $token, $authUser, $email);

        $session = $this->checkouts->findByToken($token) ?? ['token' => $token, 'status' => 'pending', 'email' => $email];

        return [
            'id' => $orderId,
            'token' => $token,
            'session' => $this->publicSession($session),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $authUser
     * @return array{status:string,redirectUrl:string,session:array<string,mixed>}
     */
    public function capturePaypal(array $input, Request $request, ?array $authUser): array
    {
        if (!$this->paypal->configured()) {
            throw new RuntimeException('PayPal checkout is not configured.');
        }

        $orderId = trim((string) ($input['orderID'] ?? $input['order_id'] ?? $input['id'] ?? ''));
        $planId = (int) ($input['plan_id'] ?? $input['planId'] ?? 0);
        $first = trim((string) ($input['first_name'] ?? $input['firstName'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? $input['lastName'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if ($orderId === '') {
            throw new RuntimeException('PayPal order id is missing.');
        }

        $session = $this->checkouts->findByOrderId($orderId);
        if (!$session) {
            $token = trim((string) ($request->cookie('orion_pay', '') ?: ($input['token'] ?? '')));
            $session = $token !== '' ? $this->checkouts->findByToken($token) : null;
        }
        if (!$session) {
            throw new RuntimeException('This PayPal checkout session was not found.');
        }
        if (($session['provider'] ?? '') !== 'paypal') {
            throw new RuntimeException('This checkout is not a PayPal order.');
        }

        if ((string) ($session['status'] ?? '') === 'completed') {
            $public = $this->publicSession($session);
            return [
                'status' => 'success',
                'redirectUrl' => (string) $public['thank_you_url'],
                'session' => $public,
            ];
        }

        $this->checkouts->update((int) $session['id'], ['status' => 'processing']);

        try {
            $captured = $this->paypal->captureOrder($orderId);
        } catch (\Throwable $e) {
            $this->checkouts->update((int) $session['id'], ['status' => 'pending']);
            throw $e;
        }
        $amount = $this->paypal->capturedAmount($captured);
        $paypalPayer = $this->paypal->payerEmail($captured);
        $email = $this->accountEmailFromCheckout(
            (string) ($session['email'] ?? ''),
            $email,
            $paypalPayer
        );
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = trim((string) ($session['name'] ?? 'Guest'));
        }

        $session['plan_id'] = $planId > 0 ? $planId : ($session['plan_id'] ?? null);
        $result = $this->fulfill($session, [
            'email' => $email,
            'name' => $name,
            'uuid' => $orderId,
            'transaction_id' => $this->paypal->captureId($captured),
            'total' => $amount['total'],
            'currency' => $amount['currency'],
            'status' => 'completed',
            'provider' => 'paypal',
            'processor' => 'paypal',
            'product_id' => '',
            'paypal_payer_email' => $paypalPayer,
        ], $request);

        try {
            $this->notifyEverflow($session, [
                'email' => $email,
                'uuid' => $orderId,
                'transaction_id' => $result['transaction_id'] ?? $orderId,
                'total' => $result['amount'] ?? $amount['total'],
                'currency' => $result['currency'] ?? $amount['currency'],
                'status' => 'completed',
                'provider' => 'paypal',
                'is_subscription' => (($result['plan']['billing_interval'] ?? '') !== ''),
            ], $result, $request, 'order.completed');
        } catch (\Throwable $e) {
            Logger::warning('Everflow after PayPal capture failed', ['error' => $e->getMessage()]);
        }

        $fresh = $this->checkouts->findByToken((string) $session['token']) ?? $session;
        $public = $this->publicSession($fresh);

        return [
            'status' => 'success',
            'redirectUrl' => (string) $public['thank_you_url'],
            'session' => $public,
        ];
    }

    public function publicStatus(string $token, Request $request): array
    {
        $session = $this->checkouts->findByToken($token);
        if (!$session) {
            throw new RuntimeException('Checkout was not found.');
        }

        if ($session['status'] === 'pending' || $session['status'] === 'processing') {
            if ($session['status'] === 'pending') {
                $this->checkouts->update((int) $session['id'], ['status' => 'processing']);
                $session['status'] = 'processing';
            }
            $age = time() - strtotime((string) $session['created_at']);
            $probe = $request->query('probe', '') === '1' || $request->query('confirm', '') === '1';
            if ($probe || ($age >= 8 && ($age <= 25 || ($age % 15) <= 3))) {
                $this->tryConfirmFromApi($session);
                $session = $this->checkouts->findByToken($token) ?? $session;
            }
        }

        return $this->publicSession($session, $request);
    }

    public function handleWebhook(array $payload, Request $request): array
    {
        $headerSecret = (string) ($request->header('X-Upgrade-Chat-Secret') ?? $request->header('X-Webhook-Secret') ?? '');
        $auth = (string) ($request->header('Authorization') ?? '');
        if ($headerSecret === '' && str_starts_with($auth, 'Bearer ')) {
            $headerSecret = trim(substr($auth, 7));
        }
        $querySecret = (string) $request->query('secret', '');
        $signature = (string) (
            $request->header('X-Upgrade-Chat-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Signature')
            ?? ''
        );
        if (!$this->upgradeChat->authorized($headerSecret, $querySecret, $request->rawBody(), $signature)) {
            throw new RuntimeException('Webhook secret did not match.');
        }

        $order = $this->upgradeChat->parseWebhook($payload);
        $type = strtolower((string) ($order['event_type'] ?? ''));
        Logger::info('Upgrade.Chat webhook parsed', [
            'event_type' => $type,
            'event_id' => $order['event_id'] ?? null,
            'email' => $order['email'] ?? null,
            'order_id' => $order['uuid'] ?? null,
            'product_id' => $order['product_id'] ?? null,
            'product_name' => $order['product_name'] ?? null,
            'amount' => $order['total'] ?? null,
            'currency' => $order['currency'] ?? null,
            'everflow_transaction_id' => $order['ef_transaction_id'] ?? null,
        ]);
        if (in_array($type, ['order.deleted', 'order.cancelled'], true) || ($order['status'] ?? '') === 'cancelled') {
            $order['status'] = 'cancelled';
        }

        $eventId = (string) ($order['event_id'] ?? '');
        if ($eventId !== '' && $this->webhooks->findByEventId('upgradechat', $eventId)) {
            return ['ok' => true, 'duplicate' => true];
        }

        try {
            $webhookId = $this->webhooks->create([
                'provider' => 'upgradechat',
                'event_id' => $eventId !== '' ? $eventId : null,
                'event_type' => $type !== '' ? $type : ($order['status'] ?? 'order'),
                'status' => 'received',
                'payload' => json_encode($payload),
            ]);
        } catch (\Throwable $e) {
            if ($eventId !== '' && $this->webhooks->findByEventId('upgradechat', $eventId)) {
                return ['ok' => true, 'duplicate' => true];
            }
            throw $e;
        }

        $trusted = false;
        if ($eventId !== '' && $this->upgradeChat->configured()) {
            $trusted = $this->upgradeChat->validateEvent($eventId);
        }

        $session = $this->matchSession($order, $request);
        if (!$session && ($order['email'] ?? '') !== '') {
            $session = $this->checkouts->findOpenByEmail($order['email'])
                ?: $this->checkouts->findLatestByEmail($order['email']);
        }

        if (!$session && !$trusted && ($order['email'] ?? '') === '') {
            $this->webhooks->update($webhookId, ['status' => 'ignored', 'error' => 'No matching checkout or email.']);
            return ['ok' => true, 'ignored' => true];
        }

        try {
            $result = $this->fulfill($session, $order, $request);
        } catch (\Throwable $e) {
            $this->webhooks->update($webhookId, ['status' => 'error', 'error' => $e->getMessage()]);
            throw $e;
        }

        $posted = ['ok' => true, 'skipped' => true, 'reason' => 'not_run'];
        try {
            $posted = $this->notifyEverflow($session, $order, $result, $request, $type);
        } catch (\Throwable $e) {
            Logger::warning('Everflow after Upgrade.Chat webhook failed', ['error' => $e->getMessage()]);
            $posted = ['ok' => false, 'skipped' => true, 'reason' => 'exception'];
        }

        $this->webhooks->update($webhookId, [
            'status' => 'processed',
            'error' => $this->everflowNote($posted),
        ]);
        return [
            'ok' => true,
            'processed' => true,
            'user_id' => $result['user']['id'] ?? null,
            'everflow' => $posted,
        ];
    }

    public function fulfill(?array $session, array $order, Request $request): array
    {
        $session = $session ?? [];
        $provider = (string) ($order['provider'] ?? $session['provider'] ?? 'upgradechat');
        $sessionEmail = strtolower(trim((string) ($session['email'] ?? '')));
        $orderEmail = strtolower(trim((string) ($order['email'] ?? '')));
        // PayPal login can use a different wallet email (especially sandbox). Membership
        // always stays on the email collected on our checkout form.
        if ($provider === 'paypal' && $sessionEmail !== '' && filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
            $email = $sessionEmail;
        } else {
            $email = $orderEmail !== '' ? $orderEmail : $sessionEmail;
        }
        $name = trim((string) ($order['name'] ?: ($session['name'] ?? 'Guest')));
        $orderId = (string) ($order['uuid'] ?? '');
        if ($email === '' && $orderId !== '') {
            $fromSub = $this->subscriptions->findByProviderSubscription($orderId);
            if ($fromSub) {
                $fromUser = $this->users->findById((int) $fromSub['user_id']);
                $email = strtolower(trim((string) ($fromUser['email'] ?? '')));
                if ($name === 'Guest' && $fromUser) {
                    $name = trim((string) (($fromUser['first_name'] ?? '') . ' ' . ($fromUser['last_name'] ?? '')));
                }
            }
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $providerHint = (string) ($order['provider'] ?? $session['provider'] ?? 'upgradechat');
            throw new RuntimeException(
                $providerHint === 'paypal'
                    ? 'PayPal order is missing an email.'
                    : 'Upgrade.Chat order is missing an email.'
            );
        }

        $cookie = (string) ($session['browser_cookie'] ?? $this->browserId($request));
        $user = $this->users->findByEmail($email);
        if (!$user && !empty($session['user_id'])) {
            $user = $this->users->findById((int) $session['user_id']);
        }
        if (!$user) {
            // PayPal/Upgrade.Chat guest checkout: placeholder credentials, is_guest=1, same user_id for later claim.
            $user = $this->createGuest($email, $name, $cookie, $request);
        } elseif (empty($user['checkout_cookie'])) {
            $this->users->update((int) $user['id'], ['checkout_cookie' => $cookie]);
            $user['checkout_cookie'] = $cookie;
        }

        $plan = null;
        if (!empty($session['plan_id'])) {
            $plan = $this->plans->find((int) $session['plan_id']);
        }
        if (!$plan && ($order['product_id'] ?? '') !== '') {
            $plan = $this->plans->findByPaymentUrl($order['product_id']);
        }
        if (!$plan && !empty($session['payment_url']) && is_upgrade_chat_url((string) $session['payment_url'])) {
            $plan = $this->plans->findByPaymentUrl((string) $session['payment_url']);
        }
        if (!$plan) {
            $provider = (string) ($order['provider'] ?? $session['provider'] ?? 'upgradechat');
            throw new RuntimeException(
                $provider === 'paypal'
                    ? 'No matching Orion Bets plan for this PayPal order.'
                    : 'No matching Orion Bets plan for this Upgrade.Chat product.'
            );
        }

        $orderId = (string) ($order['uuid'] ?? '');
        $txnId = (string) ($order['transaction_id'] ?: $orderId);
        $cancelled = ($order['status'] ?? '') === 'cancelled' || !empty($order['cancelled_at']);

        $provider = (string) ($order['provider'] ?? $session['provider'] ?? 'upgradechat');
        if ($provider === '') {
            $provider = 'upgradechat';
        }

        $existingSub = $orderId !== '' ? $this->subscriptions->findByProviderSubscription($orderId) : null;
        if ($cancelled) {
            if ($existingSub) {
                $this->subscriptions->update((int) $existingSub['id'], [
                    'status' => 'cancelled',
                    'cancelled_at' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($session) {
                $this->checkouts->update((int) $session['id'], [
                    'status' => 'cancelled',
                    'provider_order_id' => $orderId !== '' ? $orderId : ($session['provider_order_id'] ?? null),
                    'payload' => json_encode($order),
                ]);
            }
            return ['user' => $user, 'cancelled' => true];
        }

        $current = $this->subscriptions->currentForUser((int) $user['id']);
        if ($current && in_array($current['status'], ['active', 'trialing'], true) && (int) $current['plan_id'] !== (int) $plan['id']) {
            $this->subscriptions->update((int) $current['id'], [
                'status' => 'superseded',
                'ends_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!$existingSub) {
            $starts = date('Y-m-d H:i:s');
            $renews = date('Y-m-d H:i:s', strtotime('+1 ' . (($plan['billing_interval'] ?? '') === 'year' ? 'year' : 'month')));
            $subId = $this->subscriptions->create([
                'user_id' => $user['id'],
                'plan_id' => $plan['id'],
                'status' => 'active',
                'starts_at' => $starts,
                'ends_at' => $renews,
                'renews_at' => $renews,
                'provider' => $provider,
                'provider_subscription_id' => $orderId !== '' ? $orderId : null,
            ]);
        } else {
            $subId = (int) $existingSub['id'];
            $this->subscriptions->update($subId, [
                'status' => 'active',
                'user_id' => $user['id'],
                'plan_id' => $plan['id'],
            ]);
        }

        if ($txnId === '' || !$this->subscriptions->findTransactionByProviderId($txnId)) {
            $amount = isset($order['total']) && (float) $order['total'] > 0
                ? (int) round(((float) $order['total']) * 100)
                : (int) $plan['price_cents'];
            $this->subscriptions->addTransaction([
                'subscription_id' => $subId,
                'user_id' => $user['id'],
                'amount_cents' => $amount,
                'currency' => $plan['currency'] ?? 'USD',
                'status' => 'completed',
                'provider' => $provider,
                'provider_transaction_id' => $txnId !== '' ? $txnId : null,
                'description' => ($provider === 'paypal' ? 'PayPal ' : 'Upgrade.Chat ') . ($plan['name'] ?? 'plan') . ' · ' . $email,
                'payload' => json_encode([
                    'order_id' => $orderId,
                    'email' => $email,
                    'paypal_payer_email' => $order['paypal_payer_email'] ?? null,
                    'browser_cookie' => $cookie,
                    'status' => $order['status'] ?? 'completed',
                    'processor' => $order['processor'] ?? null,
                    'product_id' => $order['product_id'] ?? null,
                ]),
            ]);
        }

        if (($plan['slug'] ?? '') !== 'free') {
            $this->users->assignRole((int) $user['id'], 'premium_user');
        }

        if ($session) {
            $update = [
                'status' => 'completed',
                'user_id' => $user['id'],
                'plan_id' => $plan['id'],
                'provider' => $provider,
                'provider_order_id' => $orderId !== '' ? $orderId : ($session['provider_order_id'] ?? null),
                'provider_transaction_id' => $txnId !== '' ? $txnId : ($session['provider_transaction_id'] ?? null),
                'payload' => json_encode($order),
                'completed_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->db->columnExists('checkout_sessions', 'everflow_transaction_id') && empty($session['everflow_transaction_id'])) {
                $efTid = EverflowService::make($this->db)->resolveId($session, $order, $request);
                if ($efTid !== '') {
                    $update['everflow_transaction_id'] = $efTid;
                    $session['everflow_transaction_id'] = $efTid;
                }
            }
            $this->checkouts->update((int) $session['id'], $update);
        }

        $fresh = $this->users->findById((int) $user['id']) ?? $user;
        $isGuest = (int) ($fresh['is_guest'] ?? 0) === 1;
        $this->notifications->send(
            (int) $fresh['id'],
            'subscription',
            'Payment confirmed',
            $plan['name'] . ' is now active. Signed in with this email, you will see billing history.'
        );
        $this->audit->log((int) $fresh['id'], 'checkout_completed', 'subscription', (string) $subId, $request, [
            'order_id' => $orderId,
            'email' => $email,
            'guest' => $isGuest,
        ]);

        if ($isGuest) {
            $this->sendClaimMail($fresh);
        }

        $amount = isset($order['total']) && (float) $order['total'] > 0
            ? (float) $order['total']
            : ((int) ($plan['price_cents'] ?? 0) / 100);

        try {
            $customerName = trim(($fresh['first_name'] ?? '') . ' ' . ($fresh['last_name'] ?? ''));
            if ($customerName === '') {
                $customerName = $name !== '' ? $name : 'Member';
            }
            $paymentMethodName = $provider === 'paypal' ? 'PayPal' : 'Upgrade.Chat';
            $receiptOrderRef = $orderId !== '' ? $orderId : ($txnId !== '' ? $txnId : 'ord_' . bin2hex(random_bytes(6)));

            $this->mailer->send($email, 'Your Orion Bets Receipt — Order #' . $receiptOrderRef, 'receipt', [
                'customerName' => $customerName,
                'orderId' => $receiptOrderRef,
                'planName' => $plan['name'] ?? 'Membership Plan',
                'amount' => $amount,
                'currency' => strtoupper((string) ($order['currency'] ?? $plan['currency'] ?? 'USD')),
                'date' => date('F j, Y'),
                'paymentMethod' => $paymentMethodName,
                'billingInterval' => $plan['billing_interval'] ?? 'month',
                'user' => $fresh,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to send transactional receipt email', ['error' => $e->getMessage()]);
        }

        return [
            'user' => $fresh,
            'plan' => $plan,
            'guest' => $isGuest,
            'amount' => $amount,
            'currency' => strtoupper((string) ($order['currency'] ?? $plan['currency'] ?? 'USD')),
            'order_id' => $orderId,
            'transaction_id' => $txnId,
        ];
    }

    /**
     * Create an unclaimed checkout member. Subscriptions and premium_user are attached by fulfill()
     * on this same user_id so a later email or Discord claim keeps history intact.
     */
    public function createGuest(string $email, string $name, string $cookie, Request $request): array
    {
        $parts = split_person_name($name);
        $id = $this->users->create([
            'first_name' => $parts['first_name'],
            'last_name' => $parts['last_name'],
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'timezone' => 'UTC',
            'theme_preference' => 'system',
            'is_active' => 1,
            'is_guest' => 1,
            'checkout_cookie' => $cookie,
            'age_confirmed_at' => date('Y-m-d H:i:s'),
            'terms_accepted_at' => date('Y-m-d H:i:s'),
            'privacy_accepted_at' => date('Y-m-d H:i:s'),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->users->assignRole($id, 'user');
        $this->seedPreferences($id);
        $this->audit->log($id, 'guest_created', 'user', (string) $id, $request, ['email' => $email, 'cookie' => $cookie]);

        return $this->users->findById($id) ?? ['id' => $id, 'email' => $email, 'is_guest' => 1];
    }

    public function claimGuest(array $user, array $input, Request $request): array
    {
        return (new AuthService($this->db, $this->users, $this->mailer, $this->audit))
            ->claimGuest($user, $input, $request);
    }

    private function notifyEverflow(?array $session, array $order, array $result, Request $request, string $eventType): array
    {
        try {
            $session = $session ?? [];
            if (!empty($result['cancelled'])) {
                return ['ok' => true, 'skipped' => true, 'reason' => 'cancelled'];
            }

            $everflow = EverflowService::make($this->db);
            if (!$everflow->shouldConvert($eventType, (string) ($order['status'] ?? 'completed'))) {
                return ['ok' => true, 'skipped' => true, 'reason' => 'event_not_convertible'];
            }

            $tid = $everflow->resolveId($session, $order, $request);
            $email = (string) ($result['user']['email'] ?? $order['email'] ?? $session['email'] ?? '');
            $kind = $everflow->conversionKind($eventType, !empty($order['is_subscription']));
            $saleId = (string) ($result['order_id'] ?? $order['uuid'] ?? '');
            $chargeId = (string) ($result['transaction_id'] ?? $order['transaction_id'] ?? '');
            $provider = strtolower((string) ($order['provider'] ?? $session['provider'] ?? ''));
            $orderId = $saleId !== '' ? $saleId : $chargeId;
            $orderNumber = $chargeId !== '' ? $chargeId : $saleId;
            if ($provider === 'paypal') {
                if ($saleId !== '' && $chargeId !== '') {
                    $orderId = $saleId;
                    $orderNumber = $chargeId;
                }
            }
            if ($kind === 'rebill') {
                $orderId = ($chargeId !== '' && $chargeId !== $saleId) ? $chargeId : ($saleId !== '' ? $saleId . '-rebill' : $chargeId);
                $orderNumber = $chargeId !== '' ? $chargeId : $orderId;
            }
            $amount = (float) ($result['amount'] ?? $order['total'] ?? 0);
            $currency = (string) ($result['currency'] ?? $order['currency'] ?? 'USD');
            $stored = [];
            $rawPayload = $session['payload'] ?? null;
            if (is_string($rawPayload) && $rawPayload !== '') {
                $decoded = json_decode($rawPayload, true);
                if (is_array($decoded) && is_array($decoded['everflow'] ?? null)) {
                    $stored = $decoded['everflow'];
                }
            }
            $tracking = $everflow->trackingFrom($request, array_merge($stored, $order));

            if ($tid !== '' && $email !== '') {
                $everflow->rememberClick($tid, $request, $email, (string) ($session['browser_cookie'] ?? ''));
            }

            $posted = $everflow->fireConversion([
                'kind' => $kind,
                'transaction_id' => $tid,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'amount' => $amount,
                'currency' => $currency,
                'email' => $email,
                'event_type' => $eventType,
                'user_id' => $result['user']['id'] ?? $session['user_id'] ?? null,
                'sub1' => $tracking['sub1'] ?? '',
                'sub2' => $tracking['sub2'] ?? '',
                'sub3' => $tracking['sub3'] ?? '',
                'sub4' => $tracking['sub4'] ?? '',
                'sub5' => $tracking['sub5'] ?? '',
            ]);

            Logger::info('Everflow conversion result', $posted + ['event_type' => $eventType, 'order_id' => $orderId]);

            return $posted;
        } catch (\Throwable $e) {
            Logger::warning('Everflow notify failed', ['error' => $e->getMessage(), 'event_type' => $eventType]);
            return ['ok' => false, 'skipped' => true, 'reason' => 'exception'];
        }
    }

    private function everflowNote(array $posted): ?string
    {
        if (!empty($posted['duplicate'])) {
            return 'Everflow duplicate postback skipped';
        }
        if (!empty($posted['skipped'])) {
            return 'Everflow skipped: ' . (string) ($posted['reason'] ?? 'unknown');
        }
        if (isset($posted['status'])) {
            return 'Everflow HTTP ' . (string) $posted['status'];
        }
        if (!empty($posted['ok'])) {
            return 'Everflow posted';
        }

        return 'Everflow postback failed';
    }

    private function matchSession(array $order, Request $request): ?array
    {
        $raw = is_array($order['raw'] ?? null) ? $order['raw'] : [];
        $meta = is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [];
        $ref = (string) ($raw['client_reference_id'] ?? $raw['cid'] ?? $meta['client_reference_id'] ?? '');
        if ($ref !== '') {
            $byRef = $this->checkouts->findByToken($ref);
            if ($byRef) {
                return $byRef;
            }
        }

        if (!empty($order['uuid'])) {
            $byOrder = $this->checkouts->findByOrderId((string) $order['uuid']);
            if ($byOrder) {
                return $byOrder;
            }
        }

        $email = strtolower(trim((string) ($order['email'] ?? '')));
        if ($email !== '') {
            $open = $this->checkouts->findOpenByEmail($email);
            if ($open) {
                return $open;
            }
        }

        $cookie = (string) $request->cookie('orion_pay', '');
        if ($cookie !== '') {
            $byToken = $this->checkouts->findByToken($cookie);
            if ($byToken) {
                return $byToken;
            }
        }

        return null;
    }

    /**
     * Guest membership is keyed to the email collected on our checkout form.
     * PayPal wallet login may use a different address (sandbox buyer, another PayPal account).
     */
    private function accountEmailFromCheckout(string $sessionEmail, string $postedEmail = '', string $providerEmail = ''): string
    {
        foreach ([$sessionEmail, $postedEmail, $providerEmail] as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return '';
    }

    private function tryConfirmFromApi(array $session): void
    {
        if (!$this->upgradeChat->configured()) {
            return;
        }

        $order = null;
        if (!empty($session['provider_order_id'])) {
            $order = $this->upgradeChat->getOrder((string) $session['provider_order_id']);
        }
        if (!$order) {
            $order = $this->upgradeChat->findOrderByEmail((string) $session['email']);
        }
        if (!$order || ($order['status'] ?? '') === 'cancelled') {
            return;
        }

        $productOk = ($session['product_id'] ?? '') === '' || ($order['product_id'] ?? '') === '' || $session['product_id'] === $order['product_id'];
        if (!$productOk) {
            return;
        }

        try {
            $result = $this->fulfill($session, $order, request());
            $this->notifyEverflow($session, $order, $result, request(), 'order.completed');
        } catch (\Throwable $e) {
            Logger::warning('Checkout API confirm failed', ['error' => $e->getMessage()]);
        }
    }

    private function checkoutUrl(string $url, string $email, string $name, string $token, string $cookie, string $efTid = '', bool $viaDiscord = false): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }

        $query = [];
        parse_str((string) ($parsed['query'] ?? ''), $query);
        $query['email'] = $query['email'] ?? $email;
        $query['name'] = $query['name'] ?? $name;
        if ($viaDiscord) {
            unset($query['guest'], $query['email_checkout']);
        } else {
            $query['guest'] = '1';
            $query['email_checkout'] = '1';
        }
        $query['client_reference_id'] = $token;
        $complete = url('/checkout/complete?token=' . $token);
        $query['return_url'] = $complete;
        $query['success_url'] = $complete;
        $query['redirect_url'] = $complete;
        if ($efTid !== '') {
            $query['adv1'] = $efTid;
        }
        unset($query['cid'], $query['ef_transaction_id']);

        $rebuilt = (isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '')
            . ($parsed['host'] ?? '')
            . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
            . ($parsed['path'] ?? '');
        $qs = http_build_query($query);
        return $qs !== '' ? $rebuilt . '?' . $qs : $rebuilt;
    }

    private function publicSession(array $session, ?Request $request = null): array
    {
        $user = null;
        if (!empty($session['user_id'])) {
            $user = $this->users->findById((int) $session['user_id']);
        }

        $plan = null;
        if (!empty($session['plan_id'])) {
            $plan = $this->plans->find((int) $session['plan_id']);
        }
        $amount = null;
        $currency = 'USD';
        if ($plan) {
            $amount = ((int) $plan['price_cents']) / 100;
            $currency = strtoupper((string) ($plan['currency'] ?? 'USD'));
        }

        $email = (string) ($session['email'] ?? '');
        $registerQuery = ['next' => '/dashboard'];
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $registerQuery['email'] = $email;
        }

        return [
            'token' => $session['token'],
            'status' => $session['status'],
            'email' => $session['email'],
            'order_id' => $session['provider_order_id'] ?? null,
            'transaction_id' => $session['provider_transaction_id'] ?? null,
            'everflow_transaction_id' => $session['everflow_transaction_id'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'guest' => $user ? (int) ($user['is_guest'] ?? 0) === 1 : true,
            'claim_url' => url('/forgot-password'),
            'login_url' => url('/login'),
            'register_url' => url('/register?' . http_build_query($registerQuery)),
            'discord_url' => url('/auth/discord?next=' . rawurlencode('/dashboard')),
            'account_url' => url('/account/subscription'),
            'thank_you_url' => url('/thank-you?token=' . urlencode((string) $session['token'])),
        ];
    }

    private function sendClaimMail(array $user): void
    {
        $token = bin2hex(random_bytes(32));
        $this->db->delete('password_resets', 'email = :e', ['e' => $user['email']]);
        $this->db->insert('password_resets', [
            'email' => $user['email'],
            'token' => hash('sha256', $token),
        ]);
        $this->mailer->send($user['email'], 'Your Orion Bets guest access', 'guest-access', [
            'user' => $user,
            'url' => url('/reset-password?token=' . $token),
            'login_url' => url('/login'),
            'register_url' => url('/register'),
        ]);
    }

    private function seedPreferences(int $userId): void
    {
        foreach (['email', 'in_app'] as $channel) {
            foreach (['daily_pick', 'pick_result', 'subscription', 'account'] as $event) {
                $exists = $this->db->fetch(
                    'SELECT id FROM notification_preferences WHERE user_id = :u AND channel = :c AND event_type = :e',
                    ['u' => $userId, 'c' => $channel, 'e' => $event]
                );
                if ($exists) {
                    continue;
                }
                $this->db->insert('notification_preferences', [
                    'user_id' => $userId,
                    'channel' => $channel,
                    'event_type' => $event,
                    'enabled' => 1,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed>|null $authUser
     */
    private function everflowCheckoutStarted(
        EverflowService $everflow,
        Request $request,
        string $token,
        ?array $authUser,
        string $email
    ): void {
        try {
            $everflow->trackFunnel('checkout', $request, [
                'user_id' => $authUser['id'] ?? null,
                'email' => $email,
                'order_id' => 'checkout-' . $token,
                'event_type' => 'checkout_started',
                'amount' => 0,
            ]);
        } catch (\Throwable) {
        }
    }

    private function writeCookie(string $name, string $value, int $ttl): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => Env::bool('SESSION_SECURE'),
        ]);
    }
}
