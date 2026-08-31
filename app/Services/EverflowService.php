<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Repositories\CheckoutRepository;
use App\Repositories\EverflowRepository;

final class EverflowService
{
    public const COOKIE = 'orion_ef_tid';
    public const COOKIE_TID = 'ef_tid';
    public const JS_COOKIE = 'ef_transaction_id';
    public const COOKIE_IMP = 'ef_iid';
    public const JS_COOKIE_IMP = 'ef_impression_id';
    public const SESSION_KEY = 'everflow_tracking';
    public const TTL = 86400 * 90;

    public function __construct(
        private Database $db,
        private EverflowRepository $clicks,
        private CheckoutRepository $checkouts
    ) {
    }

    public static function make(Database $db): self
    {
        return new self($db, new EverflowRepository($db), new CheckoutRepository($db));
    }

    public static function normalizeId(string $value): string
    {
        $value = trim($value);
        return is_everflow_transaction_id($value) ? strtolower($value) : '';
    }

    public function capture(Request $request): string
    {
        $path = rtrim($request->path(), '/') ?: '/';
        if (
            str_starts_with($path, '/webhooks')
            || str_starts_with($path, '/api/webhook')
            || str_starts_with($path, '/everflow')
            || str_starts_with($path, '/admin')
        ) {
            return $this->currentId($request);
        }

        $incoming = $this->paramsFromRequest($request);
        $tid = $this->idFromRequest($request);
        $imp = $this->impressionFromRequest($request);
        if ($imp !== '') {
            $incoming['impression_id'] = $imp;
            $this->persistImpressionCookie($imp);
        }
        if ($tid !== '') {
            $incoming['click_type'] = $incoming['click_type'] ?? 'redirect';
        } elseif ($imp !== '') {
            $tid = $imp;
            $incoming['transaction_id'] = $tid;
            $incoming['click_type'] = 'impression';
        } else {
            $tid = $this->currentId($request);
        }

        if ($tid === '' && $this->shouldCaptureLanding($request)) {
            $fetched = $this->fetchServerClick($request, $incoming);
            if ($fetched !== '') {
                $tid = $fetched;
                $incoming['transaction_id'] = $tid;
                if (empty($incoming['click_type'])) {
                    $incoming['click_type'] = $this->hasAttribution($incoming) ? 'direct' : 'landing';
                }
            }
        }

        $tracking = $this->mergeTracking($this->sessionTracking(), $incoming);
        if ($tid !== '') {
            $tracking['transaction_id'] = $tid;
            if (($tracking['click_type'] ?? '') !== 'impression') {
                $this->persistCookie($tid);
            }
        }

        if ($tracking !== []) {
            $this->storeSession($tracking);
        }

        $fromInbound = $this->idFromRequest($request) !== ''
            || $imp !== ''
            || $this->hasAttribution($incoming);
        if ($tid !== '' && $this->shouldCaptureLanding($request)) {
            $extra = $incoming;
            if (!$fromInbound) {
                unset($extra['click_type']);
            }
            $this->rememberClick($tid, $request, null, null, $extra);
        }

        return $tid;
    }

    /**
     * Persist a JS SDK / first-party click (or impression) from the browser.
     *
     * @param array<string, mixed> $input
     */
    public function ingest(Request $request, array $input): string
    {
        $incoming = $this->sanitizeTracking($input);
        $tid = self::normalizeId((string) ($incoming['transaction_id'] ?? ''));
        $imp = self::normalizeId((string) ($incoming['impression_id'] ?? $input['impression_id'] ?? ''));
        $clickType = strtolower(trim((string) ($incoming['click_type'] ?? $input['click_type'] ?? '')));

        if ($tid === '' && $imp !== '') {
            $tid = $imp;
            $incoming['transaction_id'] = $tid;
            $incoming['impression_id'] = $imp;
            $clickType = $clickType !== '' ? $clickType : 'impression';
        }
        if (in_array($clickType, ['redirect', 'direct', 'impression', 'landing'], true)) {
            $incoming['click_type'] = $clickType;
        } else {
            unset($incoming['click_type']);
        }

        if ($tid === '' && !$this->hasAttribution($incoming)) {
            return '';
        }

        if ($tid !== '' && ($incoming['click_type'] ?? '') !== 'impression') {
            $this->persistCookie($tid);
        }
        if ($imp !== '') {
            $this->persistImpressionCookie($imp);
        }

        $this->storeSession($this->mergeTracking($this->sessionTracking(), $incoming));
        $this->rememberClick($tid, $request, null, null, $incoming);

        return $tid;
    }

    /**
     * Reuse a cookie click id, or create one with Everflow before checkout/signup.
     *
     * @param array<string, mixed> $input
     */
    public function ensureClick(Request $request, array $input = []): string
    {
        $tid = $this->currentId($request, $input);
        if ($tid === '') {
            $tid = $this->fetchServerClick($request, $this->trackingFrom($request, $input));
        }
        if ($tid === '') {
            return '';
        }

        $this->persistCookie($tid);
        $tracking = ['transaction_id' => $tid, 'click_type' => 'landing'] + $this->trackingFrom($request, $input);
        $this->storeSession($tracking);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $this->rememberClick($tid, $request, $email !== '' ? $email : null, null, $tracking);

        return $tid;
    }

    /**
     * Funnel S2S: lead (CPL) or checkout-started. Requires a dedicated Everflow event id
     * so these do not count as the default sale conversion.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function trackFunnel(string $kind, Request $request, array $payload = []): array
    {
        $kind = strtolower(trim($kind));
        $eventId = $this->eventIdFor($kind, (string) ($payload['event_id'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $orderId = trim((string) ($payload['order_id'] ?? ''));

        $tid = $this->currentId($request, $payload);
        if ($tid === '') {
            $tid = $this->resolveId(null, $payload, $request);
        }
        if ($tid === '') {
            $tid = $this->fetchServerClick($request, $this->trackingFrom($request, $payload));
            if ($tid !== '') {
                $this->persistCookie($tid);
                $this->storeSession(['transaction_id' => $tid] + $this->trackingFrom($request, $payload));
            }
        }

        if ($eventId === '' && in_array($kind, ['lead', 'contact', 'checkout'], true)) {
            $this->persistPostback([
                'kind' => $kind,
                'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                'email' => $email !== '' ? $email : null,
                'order_id' => $orderId !== '' ? $orderId : null,
                'order_number' => (string) ($payload['order_number'] ?? $orderId) ?: null,
                'transaction_id' => $tid !== '' ? $tid : null,
                'everflow_transaction_id' => $tid !== '' ? $tid : null,
                'amount' => $payload['amount'] ?? 0,
                'currency' => $payload['currency'] ?? 'USD',
                'event_type' => (string) ($payload['event_type'] ?? $kind),
                'status' => 'failed',
                'error_message' => 'event_id_not_configured',
            ]);
            return ['ok' => false, 'skipped' => true, 'reason' => 'event_id_not_configured'];
        }

        if ($tid === '') {
            $this->persistPostback([
                'kind' => $kind,
                'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                'email' => $email !== '' ? $email : null,
                'order_id' => $orderId !== '' ? $orderId : null,
                'order_number' => (string) ($payload['order_number'] ?? $orderId) ?: null,
                'amount' => $payload['amount'] ?? 0,
                'currency' => $payload['currency'] ?? 'USD',
                'event_type' => (string) ($payload['event_type'] ?? $kind),
                'status' => 'failed',
                'error_message' => 'missing_transaction_id',
            ]);
            return ['ok' => false, 'skipped' => true, 'reason' => 'missing_transaction_id'];
        }

        try {
            $this->rememberClick($tid, $request, $email !== '' ? $email : null);
        } catch (\Throwable) {
        }

        return $this->fireConversion(array_merge($payload, [
            'kind' => $kind,
            'transaction_id' => $tid,
            'event_id' => $eventId,
            'event_type' => (string) ($payload['event_type'] ?? $kind),
            'amount' => $payload['amount'] ?? 0,
            'email' => $email,
            'order_id' => $orderId,
            'order_number' => (string) ($payload['order_number'] ?? $orderId),
        ]));
    }

    public function currentId(Request $request, array $input = []): string
    {
        $session = $this->sessionTracking();
        foreach ([
            $input['ef_transaction_id'] ?? '',
            $input['ef_id'] ?? '',
            $input['transaction_id'] ?? '',
            $input['_ef_transaction_id'] ?? '',
            $session['transaction_id'] ?? '',
            $request->cookie(self::COOKIE_TID, ''),
            $request->cookie(self::COOKIE, ''),
            $request->cookie(self::JS_COOKIE, ''),
            $request->cookie('_ef_transaction_id', ''),
            $session['impression_id'] ?? '',
            $request->cookie(self::COOKIE_IMP, ''),
            $request->cookie(self::JS_COOKIE_IMP, ''),
        ] as $candidate) {
            $tid = self::normalizeId((string) $candidate);
            if ($tid !== '') {
                return $tid;
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public function sessionTracking(): array
    {
        try {
            $raw = app()->session->get(self::SESSION_KEY, []);
        } catch (\Throwable) {
            $raw = $_SESSION[self::SESSION_KEY] ?? [];
        }

        return is_array($raw) ? $this->sanitizeTracking($raw) : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function trackingFrom(Request $request, array $input = []): array
    {
        return $this->mergeTracking(
            $this->sessionTracking(),
            $this->sanitizeTracking($input),
            $this->paramsFromRequest($request)
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function rememberClick(string $tid, Request $request, ?string $email, ?string $browserCookie = null, array $extra = []): void
    {
        $tid = self::normalizeId($tid);
        $tracking = $this->mergeTracking($this->trackingFrom($request), $extra);
        if ($tid === '') {
            $tid = (string) ($tracking['transaction_id'] ?? '');
        }
        if (($tid === '' && !$this->hasAttribution($tracking)) || !$this->db->tableExists('everflow_clicks')) {
            return;
        }

        $landingUrl = $this->landingUrl($request);
        $ip = $request->ip();
        $affid = $this->firstNonEmpty($tracking['affid'] ?? '', $tracking['affiliate_id'] ?? '');
        $oid = $this->firstNonEmpty($tracking['oid'] ?? '', $tracking['offer_id'] ?? '');
        $imp = self::normalizeId((string) ($tracking['impression_id'] ?? ''));
        $clickType = strtolower(trim((string) ($tracking['click_type'] ?? '')));
        if ($clickType === '') {
            if ($imp !== '' && ($tid === '' || $tid === $imp)) {
                $clickType = 'impression';
            } elseif ($this->idFromRequest($request) !== '') {
                $clickType = 'redirect';
            }
        }

        try {
            $this->clicks->upsertClick([
                'transaction_id' => $tid !== '' ? $tid : null,
                'impression_id' => $imp !== '' ? $imp : null,
                'click_type' => $clickType !== '' ? $clickType : null,
                'source_id' => ($tracking['source_id'] ?? '') !== '' ? $tracking['source_id'] : null,
                'creative_id' => ($tracking['creative_id'] ?? '') !== '' ? $tracking['creative_id'] : null,
                'sub1' => $tracking['sub1'] ?? null,
                'sub2' => $tracking['sub2'] ?? null,
                'sub3' => $tracking['sub3'] ?? null,
                'sub4' => $tracking['sub4'] ?? null,
                'sub5' => $tracking['sub5'] ?? null,
                'affiliate_id' => $affid !== '' ? $affid : null,
                'affid' => $affid !== '' ? $affid : null,
                'offer_id' => $oid !== '' ? $oid : null,
                'oid' => $oid !== '' ? $oid : null,
                'landing_url' => $landingUrl !== '' ? $landingUrl : null,
                'landing_path' => substr($request->path(), 0, 255),
                'ip_address' => $ip,
                'ip' => $ip,
                'user_agent' => substr($request->userAgent(), 0, 255),
                'email' => $email ? strtolower($email) : null,
                'browser_cookie' => $browserCookie,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Everflow click persist failed', ['error' => $e->getMessage()]);
        }
    }

    public function resolveId(?array $session, array $order, Request $request): string
    {
        $session = $session ?? [];
        $raw = is_array($order['raw'] ?? null) ? $order['raw'] : [];
        $meta = is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [];
        $payload = $this->sessionPayload($session);
        $candidates = [
            $session['everflow_transaction_id'] ?? '',
            $payload['transaction_id'] ?? '',
            $order['ef_transaction_id'] ?? '',
            $raw['ef_transaction_id'] ?? '',
            $raw['_ef_transaction_id'] ?? '',
            $raw['transaction_id'] ?? '',
            $raw['adv1'] ?? '',
            $meta['ef_transaction_id'] ?? '',
            $meta['transaction_id'] ?? '',
            $meta['adv1'] ?? '',
            $session['impression_id'] ?? '',
            $payload['impression_id'] ?? '',
            $raw['impression_id'] ?? '',
            $raw['_ef_impression_id'] ?? '',
            $meta['impression_id'] ?? '',
        ];
        foreach ($candidates as $candidate) {
            $tid = self::normalizeId((string) $candidate);
            if ($tid !== '') {
                return $tid;
            }
        }

        $email = strtolower(trim((string) ($order['email'] ?? $session['email'] ?? '')));
        if ($email !== '' && $this->db->tableExists('everflow_clicks')) {
            $click = $this->clicks->findClickByEmail($email);
            $tid = self::normalizeId((string) ($click['transaction_id'] ?? ''));
            if ($tid !== '') {
                return $tid;
            }
            $fromCheckout = $this->checkouts->findLatestByEmail($email);
            $tid = self::normalizeId((string) ($fromCheckout['everflow_transaction_id'] ?? ''));
            if ($tid !== '') {
                return $tid;
            }
        }

        $cookie = (string) ($session['browser_cookie'] ?? '');
        if ($cookie !== '' && $this->db->tableExists('everflow_clicks')) {
            $click = $this->clicks->findClickByCookie($cookie);
            $tid = self::normalizeId((string) ($click['transaction_id'] ?? ''));
            if ($tid !== '') {
                return $tid;
            }
        }

        return $this->currentId($request);
    }

    public function shouldConvert(string $eventType, string $status): bool
    {
        if ($status === 'cancelled') {
            return false;
        }

        $type = strtolower($eventType);
        if ($type === '') {
            return $status === 'completed';
        }

        return in_array($type, [
            'order.created',
            'order.completed',
            'order.updated',
            'subscription.created',
            'subscription.renewed',
            'invoice.paid',
            'payment.succeeded',
        ], true);
    }

    public function conversionKind(string $eventType, bool $isSubscription): string
    {
        $type = strtolower($eventType);
        if (in_array($type, ['subscription.renewed', 'invoice.paid'], true)) {
            return 'rebill';
        }
        if ($type === 'order.updated' && $isSubscription) {
            return 'rebill';
        }

        return 'sale';
    }

    /**
     * Fire an Everflow S2S postback and persist the full outbound request/response.
     * Failures never throw — checkout must keep succeeding.
     */
    public function triggerPostback(array $payload): bool
    {
        try {
            $tid = self::normalizeId((string) ($payload['transaction_id'] ?? ''));
            $orderId = trim((string) ($payload['order_id'] ?? ''));
            $orderNumber = trim((string) ($payload['order_number'] ?? $payload['transaction_id_external'] ?? $payload['order_num'] ?? ''));
            $kind = (string) ($payload['kind'] ?? 'sale');
            $amount = isset($payload['amount']) ? round((float) $payload['amount'], 2) : 0.0;
            $currency = strtoupper(trim((string) ($payload['currency'] ?? 'USD'))) ?: 'USD';
            $eventType = trim((string) ($payload['event_type'] ?? 'sale')) ?: 'sale';
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $userId = isset($payload['user_id']) && (int) $payload['user_id'] > 0 ? (int) $payload['user_id'] : null;
            $subs = $this->resolveSubs($payload, $tid);

            if ($orderNumber === '' && $orderId !== '') {
                $orderNumber = $orderId;
            }

            $url = '';
            $error = null;
            $eventId = $this->eventIdFor($kind, (string) ($payload['event_id'] ?? ''));
            if ($tid === '') {
                $error = 'missing_transaction_id';
            } else {
                $url = $this->postbackUrl($tid, $amount, $orderId, $currency, $kind, $subs, $eventId, $email, $orderNumber);
                if ($url === '') {
                    $error = 'not_configured';
                }
            }

            $httpStatus = 0;
            $body = '';
            $curlError = null;
            if ($url !== '') {
                $result = $this->get($url);
                $httpStatus = (int) $result['status'];
                $body = (string) $result['body'];
                $curlError = $result['error'] !== '' ? (string) $result['error'] : null;
            }

            $ok = $url !== '' && $httpStatus >= 200 && $httpStatus < 400;
            $status = $ok ? 'success' : 'failed';
            if ($error !== null && $url === '') {
                $status = 'failed';
            }

            $this->persistPostback([
                'id' => isset($payload['postback_id']) ? (int) $payload['postback_id'] : 0,
                'kind' => $kind,
                'user_id' => $userId,
                'email' => $email !== '' ? $email : null,
                'order_id' => $orderId !== '' ? $orderId : null,
                'order_number' => $orderNumber !== '' ? $orderNumber : null,
                'transaction_id' => $tid !== '' ? $tid : null,
                'everflow_transaction_id' => $tid !== '' ? $tid : null,
                'amount' => $amount,
                'currency' => $currency,
                'event_type' => $eventType,
                'sub1' => $subs['sub1'] !== '' ? $subs['sub1'] : null,
                'sub2' => $subs['sub2'] !== '' ? $subs['sub2'] : null,
                'sub3' => $subs['sub3'] !== '' ? $subs['sub3'] : null,
                'sub4' => $subs['sub4'] !== '' ? $subs['sub4'] : null,
                'sub5' => $subs['sub5'] !== '' ? $subs['sub5'] : null,
                'postback_url' => $url !== '' ? $url : null,
                'url' => $url !== '' ? substr($url, 0, 700) : null,
                'http_status' => $httpStatus > 0 ? $httpStatus : null,
                'response_body' => $body !== '' ? $body : null,
                'response' => $body !== '' ? substr($body, 0, 65000) : null,
                'status' => $status,
                'error_message' => $this->firstNonEmpty((string) $error, (string) $curlError) ?: null,
            ]);

            Logger::info('Everflow S2S postback', [
                'kind' => $kind,
                'transaction_id' => $tid,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'email' => $email,
                'amount' => $amount,
                'status' => $httpStatus,
                'ok' => $ok,
            ]);

            return $ok;
        } catch (\Throwable $e) {
            Logger::warning('Everflow postback failed', ['error' => $e->getMessage()]);
            try {
                $this->persistPostback([
                    'kind' => (string) ($payload['kind'] ?? 'sale'),
                    'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                    'email' => $payload['email'] ?? null,
                    'order_id' => $payload['order_id'] ?? null,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                    'everflow_transaction_id' => $payload['transaction_id'] ?? null,
                    'amount' => $payload['amount'] ?? null,
                    'currency' => $payload['currency'] ?? 'USD',
                    'event_type' => $payload['event_type'] ?? 'sale',
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            } catch (\Throwable) {
            }

            return false;
        }
    }

    public function fireConversion(array $payload): array
    {
        $tid = self::normalizeId((string) ($payload['transaction_id'] ?? ''));
        $orderId = trim((string) ($payload['order_id'] ?? ''));
        $kind = (string) ($payload['kind'] ?? 'sale');

        if ($tid === '') {
            Logger::info('Everflow conversion skipped — no transaction id', [
                'order_id' => $orderId,
                'email' => $payload['email'] ?? null,
            ]);
            return ['ok' => false, 'skipped' => true, 'reason' => 'missing_transaction_id'];
        }

        if ($orderId !== '' && $this->db->tableExists('everflow_postbacks')) {
            $existing = $this->clicks->findPostback($kind, $orderId);
            $existingStatus = (string) ($existing['status'] ?? '');
            $existingHttp = (int) ($existing['http_status'] ?? 0);
            $alreadyOk = $existing && (
                $existingStatus === 'success'
                || ($existingStatus === '' && $existingHttp >= 200 && $existingHttp < 400)
            );
            if ($alreadyOk) {
                return ['ok' => true, 'duplicate' => true, 'id' => $existing['id']];
            }
            if ($existing) {
                $payload['postback_id'] = (int) $existing['id'];
            }
        }

        $ok = $this->triggerPostback($payload + ['transaction_id' => $tid, 'kind' => $kind]);
        $fresh = ($orderId !== '' && $this->db->tableExists('everflow_postbacks'))
            ? $this->clicks->findPostback($kind, $orderId)
            : null;

        return [
            'ok' => $ok,
            'status' => $fresh['http_status'] ?? null,
            'url' => $fresh['postback_url'] ?? $fresh['url'] ?? null,
            'id' => $fresh['id'] ?? null,
        ];
    }

    public function retryPostback(int $id): bool
    {
        $row = $this->clicks->findPostbackById($id);
        if (!$row) {
            return false;
        }

        return $this->triggerPostback([
            'postback_id' => $id,
            'kind' => (string) ($row['kind'] ?? 'sale'),
            'user_id' => $row['user_id'] ?? null,
            'email' => $row['email'] ?? null,
            'order_id' => $row['order_id'] ?? '',
            'order_number' => $row['order_number'] ?? $row['order_id'] ?? '',
            'transaction_id' => $row['transaction_id'] ?? $row['everflow_transaction_id'] ?? '',
            'amount' => $row['amount'] ?? 0,
            'currency' => $row['currency'] ?? 'USD',
            'event_type' => $row['event_type'] ?? 'sale',
            'event_id' => $this->eventIdFor((string) ($row['kind'] ?? 'sale')),
            'sub1' => $row['sub1'] ?? '',
            'sub2' => $row['sub2'] ?? '',
            'sub3' => $row['sub3'] ?? '',
            'sub4' => $row['sub4'] ?? '',
            'sub5' => $row['sub5'] ?? '',
        ]);
    }

    public function frontendConfig(): array
    {
        $cfg = everflow_config();
        return [
            'enabled' => !empty($cfg['enabled']),
            'sdk_url' => $cfg['sdk_url'],
            'offer_id' => $cfg['offer_id'] !== '' ? (int) $cfg['offer_id'] : null,
            'advertiser_id' => $cfg['advertiser_id'] !== '' ? (int) $cfg['advertiser_id'] : null,
            'affiliate_id' => $cfg['affiliate_id'] !== '' ? (int) $cfg['affiliate_id'] : null,
            'ingest_url' => url('/everflow/ingest'),
            'ttl_days' => 90,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paramsFromRequest(Request $request): array
    {
        $out = [];
        foreach ([
            'sub1', 'sub2', 'sub3', 'sub4', 'sub5',
            'affid', 'affiliate_id', 'oid', 'offer_id',
            'source_id', 'sourceid', 'sid',
            'creative_id', 'creativeid', 'crid',
            'click_type',
        ] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $out[$key] = substr($value, 0, 255);
            }
        }
        if (!empty($out['sourceid']) && empty($out['source_id'])) {
            $out['source_id'] = $out['sourceid'];
        }
        if (!empty($out['sid']) && empty($out['source_id'])) {
            $out['source_id'] = $out['sid'];
        }
        if (!empty($out['creativeid']) && empty($out['creative_id'])) {
            $out['creative_id'] = $out['creativeid'];
        }
        if (!empty($out['crid']) && empty($out['creative_id'])) {
            $out['creative_id'] = $out['crid'];
        }
        $tid = $this->idFromRequest($request);
        if ($tid !== '') {
            $out['transaction_id'] = $tid;
        }
        $imp = $this->impressionFromRequest($request);
        if ($imp !== '') {
            $out['impression_id'] = $imp;
        }

        return $out;
    }

    private function impressionFromRequest(Request $request): string
    {
        foreach (['impression_id', '_ef_impression_id', 'imp_id', 'ef_impression_id'] as $key) {
            $id = self::normalizeId((string) $request->query($key, ''));
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    private function idFromRequest(Request $request): string
    {
        foreach (['_ef_transaction_id', 'ef_transaction_id', 'ef_id', 'transaction_id'] as $key) {
            $tid = self::normalizeId((string) $request->query($key, ''));
            if ($tid !== '') {
                return $tid;
            }
        }

        return '';
    }

    private function shouldCaptureLanding(Request $request): bool
    {
        if (!$request->isGet() || $request->isAjax() || $request->isApi()) {
            return false;
        }

        $path = rtrim($request->path(), '/') ?: '/';
        foreach (['/admin', '/webhooks', '/api', '/everflow', '/checkout/status'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ask Everflow for a click id now (does not wait for browser JS).
     * Organic landings must send an affiliate id or Everflow returns nothing.
     *
     * @param array<string, mixed> $tracking
     */
    private function fetchServerClick(Request $request, array $tracking = []): string
    {
        $cfg = everflow_config();
        $host = (string) ($cfg['host'] ?? '');
        $oid = $this->firstNonEmpty((string) ($tracking['oid'] ?? $tracking['offer_id'] ?? ''), (string) ($cfg['offer_id'] ?? ''));
        $affid = $this->firstNonEmpty((string) ($tracking['affid'] ?? $tracking['affiliate_id'] ?? ''), (string) ($cfg['affiliate_id'] ?? ''));
        if ($host === '' || $oid === '' || $affid === '') {
            Logger::warning('Everflow server click skipped', [
                'host' => $host,
                'offer_id' => $oid,
                'affiliate_id' => $affid,
            ]);
            return '';
        }

        $query = array_filter([
            'nid' => (string) ($cfg['nid'] ?? ''),
            'oid' => $oid,
            'affid' => $affid,
            'async' => 'json',
            'sub1' => (string) ($tracking['sub1'] ?? ''),
            'sub2' => (string) ($tracking['sub2'] ?? ''),
            'sub3' => (string) ($tracking['sub3'] ?? ''),
            'sub4' => (string) ($tracking['sub4'] ?? ''),
            'sub5' => (string) ($tracking['sub5'] ?? ''),
            'source_id' => (string) ($tracking['source_id'] ?? 'organic'),
        ], static fn ($v) => $v !== '');

        $url = 'https://' . $host . '/sdk/click?' . http_build_query($query);
        $result = $this->get($url, [
            'Accept: application/json',
            'User-Agent: ' . ($request->userAgent() !== '' ? $request->userAgent() : 'OrionBets-Everflow/1.0'),
            'X-Forwarded-For: ' . $request->ip(),
        ]);
        $json = json_decode((string) $result['body'], true);
        $tid = self::normalizeId((string) ($json['transaction_id'] ?? ''));
        if ($tid === '') {
            Logger::warning('Everflow server click empty', [
                'status' => $result['status'],
                'body' => substr((string) $result['body'], 0, 300),
                'error' => $result['error'],
            ]);
        }

        return $tid;
    }

    private function persistCookie(string $tid): void
    {
        if ($tid === '' || headers_sent()) {
            return;
        }

        $options = [
            'expires' => time() + self::TTL,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => Env::bool('SESSION_SECURE') || request_is_https(),
        ];
        setcookie(self::COOKIE_TID, $tid, $options + ['httponly' => true]);
        setcookie(self::COOKIE, $tid, $options + ['httponly' => true]);
        setcookie(self::JS_COOKIE, $tid, $options + ['httponly' => false]);
        setcookie('_ef_transaction_id', $tid, $options + ['httponly' => false]);
        $_COOKIE[self::COOKIE_TID] = $tid;
        $_COOKIE[self::COOKIE] = $tid;
        $_COOKIE[self::JS_COOKIE] = $tid;
        $_COOKIE['_ef_transaction_id'] = $tid;
    }

    private function persistImpressionCookie(string $id): void
    {
        if ($id === '' || headers_sent()) {
            return;
        }

        $options = [
            'expires' => time() + self::TTL,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => Env::bool('SESSION_SECURE') || request_is_https(),
        ];
        setcookie(self::COOKIE_IMP, $id, $options + ['httponly' => true]);
        setcookie(self::JS_COOKIE_IMP, $id, $options + ['httponly' => false]);
        $_COOKIE[self::COOKIE_IMP] = $id;
        $_COOKIE[self::JS_COOKIE_IMP] = $id;
    }

    /**
     * @param array<string, mixed> $tracking
     */
    private function storeSession(array $tracking): void
    {
        $clean = $this->sanitizeTracking($tracking);
        if ($clean === []) {
            return;
        }

        try {
            app()->session->set(self::SESSION_KEY, $clean);
        } catch (\Throwable) {
            $_SESSION[self::SESSION_KEY] = $clean;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistPostback(array $row): void
    {
        if (!$this->db->tableExists('everflow_postbacks')) {
            return;
        }

        try {
            $id = (int) ($row['id'] ?? 0);
            unset($row['id']);
            if ($id > 0) {
                $this->clicks->updatePostback($id, $row);
                return;
            }

            $kind = (string) ($row['kind'] ?? 'sale');
            $orderId = (string) ($row['order_id'] ?? '');
            if ($orderId !== '') {
                $existing = $this->clicks->findPostback($kind, $orderId);
                if ($existing) {
                    $this->clicks->updatePostback((int) $existing['id'], $row);
                    return;
                }
            }

            $this->clicks->createPostback($row);
        } catch (\Throwable $e) {
            Logger::warning('Everflow postback log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{sub1:string,sub2:string,sub3:string,sub4:string,sub5:string}
     */
    private function resolveSubs(array $payload, string $tid): array
    {
        $fromPayload = $this->sanitizeTracking($payload);
        $fromSession = $this->sessionTracking();
        $fromClick = [];
        if ($tid !== '' && $this->db->tableExists('everflow_clicks')) {
            $click = $this->clicks->findClick($tid);
            if ($click) {
                $fromClick = $this->sanitizeTracking($click);
            }
        }

        $merged = $this->mergeTracking($fromClick, $fromSession, $fromPayload);
        $subs = ['sub1' => '', 'sub2' => '', 'sub3' => '', 'sub4' => '', 'sub5' => ''];
        foreach ($subs as $key => $_) {
            $subs[$key] = (string) ($merged[$key] ?? '');
        }

        return $subs;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, string>
     */
    private function sessionPayload(array $session): array
    {
        $raw = $session['payload'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $ef = $raw['everflow'] ?? $raw;
        return is_array($ef) ? $this->sanitizeTracking($ef) : [];
    }

    /**
     * @param array<string, mixed> ...$parts
     * @return array<string, string>
     */
    private function mergeTracking(array ...$parts): array
    {
        $out = [];
        foreach ($parts as $part) {
            foreach ($this->sanitizeTracking($part) as $key => $value) {
                if ($value !== '') {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitizeTracking(array $input): array
    {
        $out = [];
        $tid = self::normalizeId((string) (
            $input['transaction_id']
            ?? $input['ef_transaction_id']
            ?? $input['_ef_transaction_id']
            ?? $input['ef_id']
            ?? ''
        ));
        if ($tid !== '') {
            $out['transaction_id'] = $tid;
        }

        foreach (['sub1', 'sub2', 'sub3', 'sub4', 'sub5'] as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '') {
                $out[$key] = substr($value, 0, 255);
            }
        }

        $affid = $this->firstNonEmpty((string) ($input['affid'] ?? ''), (string) ($input['affiliate_id'] ?? ''));
        if ($affid !== '') {
            $out['affid'] = substr($affid, 0, 64);
            $out['affiliate_id'] = $out['affid'];
        }

        $oid = $this->firstNonEmpty((string) ($input['oid'] ?? ''), (string) ($input['offer_id'] ?? ''));
        if ($oid !== '') {
            $out['oid'] = substr($oid, 0, 64);
            $out['offer_id'] = $out['oid'];
        }

        $imp = self::normalizeId((string) (
            $input['impression_id']
            ?? $input['_ef_impression_id']
            ?? $input['imp_id']
            ?? $input['ef_impression_id']
            ?? ''
        ));
        if ($imp !== '') {
            $out['impression_id'] = $imp;
        }

        $source = $this->firstNonEmpty(
            (string) ($input['source_id'] ?? ''),
            (string) ($input['sourceid'] ?? ''),
            (string) ($input['sid'] ?? '')
        );
        if ($source !== '') {
            $out['source_id'] = substr($source, 0, 64);
        }

        $creative = $this->firstNonEmpty(
            (string) ($input['creative_id'] ?? ''),
            (string) ($input['creativeid'] ?? ''),
            (string) ($input['crid'] ?? '')
        );
        if ($creative !== '') {
            $out['creative_id'] = substr($creative, 0, 64);
        }

        $clickType = strtolower(trim((string) ($input['click_type'] ?? '')));
        if (in_array($clickType, ['redirect', 'direct', 'impression', 'landing'], true)) {
            $out['click_type'] = $clickType;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function hasAttribution(array $params): bool
    {
        foreach ([
            'transaction_id', 'impression_id', 'sub1', 'sub2', 'sub3', 'sub4', 'sub5',
            'affid', 'affiliate_id', 'oid', 'offer_id', 'source_id', 'creative_id',
        ] as $key) {
            if (trim((string) ($params[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{sub1?:string,sub2?:string,sub3?:string,sub4?:string,sub5?:string} $subs
     */
    private function postbackUrl(
        string $tid,
        float $amount,
        string $orderId,
        string $currency,
        string $kind,
        array $subs = [],
        string $eventId = '',
        string $email = '',
        string $orderNumber = ''
    ): string {
        $template = trim((string) Env::get('EVERFLOW_POSTBACK_URL', ''));
        $cfg = everflow_config();
        if ($template === '' && $cfg['host'] !== '') {
            $template = 'https://' . $cfg['host'] . '/SP?nid={nid}&transaction_id={transaction_id}&_ef_transaction_id={transaction_id}&amount={amount}&order_id={order_id}&order_number={order_number}&email={email}&currency={currency}';
        }
        if ($template === '') {
            return '';
        }

        if ($eventId === '') {
            $eventId = $this->eventIdFor($kind);
        }

        $orderNum = $orderNumber !== '' ? $orderNumber : $orderId;

        $url = strtr($template, [
            '{transaction_id}' => rawurlencode($tid),
            '{TRANSACTION_ID}' => rawurlencode($tid),
            '{amount}' => rawurlencode((string) round($amount, 2)),
            '{ORDER_AMOUNT}' => rawurlencode((string) round($amount, 2)),
            '{order_id}' => rawurlencode($orderId),
            '{ORDER_ID}' => rawurlencode($orderId),
            '{order_number}' => rawurlencode($orderNum),
            '{ORDER_NUMBER}' => rawurlencode($orderNum),
            '{order_num}' => rawurlencode($orderNum),
            '{ORDER_NUM}' => rawurlencode($orderNum),
            '{email}' => rawurlencode($email),
            '{EMAIL}' => rawurlencode($email),
            '{user_email}' => rawurlencode($email),
            '{currency}' => rawurlencode($currency),
            '{CURRENCY}' => rawurlencode($currency),
            '{event_id}' => rawurlencode($eventId),
            '{nid}' => rawurlencode((string) $cfg['nid']),
            '{domain}' => $cfg['host'],
            '{adv1}' => rawurlencode($orderNum),
            '{adv2}' => rawurlencode($email),
            '{sub1}' => rawurlencode((string) ($subs['sub1'] ?? '')),
            '{sub2}' => rawurlencode((string) ($subs['sub2'] ?? '')),
            '{sub3}' => rawurlencode((string) ($subs['sub3'] ?? '')),
            '{sub4}' => rawurlencode((string) ($subs['sub4'] ?? '')),
            '{sub5}' => rawurlencode((string) ($subs['sub5'] ?? '')),
        ]);

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        if ($cfg['nid'] !== '' && empty($query['nid'])) {
            $query['nid'] = $cfg['nid'];
        }
        if ($tid !== '' && empty($query['transaction_id'])) {
            $query['transaction_id'] = $tid;
        }
        if ($orderId !== '' && empty($query['order_id'])) {
            $query['order_id'] = $orderId;
        }
        if ($orderNum !== '' && empty($query['order_number'])) {
            $query['order_number'] = $orderNum;
        }
        if ($email !== '' && empty($query['email'])) {
            $query['email'] = $email;
        }
        if (empty($query['amount'])) {
            $query['amount'] = (string) round($amount, 2);
        }
        if (empty($query['currency'])) {
            $query['currency'] = $currency;
        }
        if ($eventId !== '' && empty($query['event_id'])) {
            $query['event_id'] = $eventId;
        }
        if ($eventId !== '' && empty($query['adv_event_id'])) {
            $query['adv_event_id'] = $eventId;
        }
        if ($orderNum !== '' && empty($query['adv1'])) {
            $query['adv1'] = $orderNum;
        }
        if ($email !== '' && empty($query['adv2'])) {
            $query['adv2'] = $email;
        }
        foreach (['sub1', 'sub2', 'sub3', 'sub4', 'sub5'] as $key) {
            $value = trim((string) ($subs[$key] ?? ''));
            if ($value !== '' && empty($query[$key])) {
                $query[$key] = $value;
            }
        }

        $scheme = $parts['scheme'] ?? 'https';
        $rebuilt = $scheme . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '/');
        $qs = http_build_query($query);
        return $qs !== '' ? $rebuilt . '?' . $qs : $rebuilt;
    }

    private function eventIdFor(string $kind, string $override = ''): string
    {
        $override = trim($override);
        if ($override !== '') {
            return $override;
        }

        $cfg = everflow_config();

        return match (strtolower(trim($kind))) {
            'lead', 'contact' => (string) ($cfg['lead_event_id'] ?? ''),
            'checkout' => (string) ($cfg['checkout_event_id'] ?? ''),
            'rebill' => (string) ($cfg['rebill_event_id'] ?? ''),
            default => '',
        };
    }

    /**
     * @param list<string> $headers
     * @return array{status:int,body:string,error:string}
     */
    private function get(string $url, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            $body = @file_get_contents($url);
            return [
                'status' => $body === false ? 0 : 200,
                'body' => $body === false ? '' : (string) $body,
                'error' => $body === false ? 'request_failed' : '',
            ];
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPGET => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($headers !== []) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => is_string($error) ? $error : '',
        ];
    }

    private function landingUrl(Request $request): string
    {
        try {
            $uri = $request->uri();
            if (str_starts_with($uri, 'http')) {
                return substr($uri, 0, 2000);
            }
            $base = rtrim((string) (function_exists('web_base_url') ? web_base_url() : ''), '/');
            return substr($base . $uri, 0, 2000);
        } catch (\Throwable) {
            return substr($request->path(), 0, 255);
        }
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
