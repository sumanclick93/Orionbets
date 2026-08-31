<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Validator;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\AuditService;

final class AdminSubscriptionController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/subscriptions/index', [
            'title' => 'Plans & billing',
            'plans' => (new PlanRepository($this->db))->all(),
            'subscriptions' => (new SubscriptionRepository($this->db))->allWithUsers(),
            'checkouts' => (new \App\Repositories\CheckoutRepository($this->db))->recent(40),
            'webhooks' => (new \App\Repositories\WebhookEventRepository($this->db))->recent(20),
            'postbacks' => $this->db->tableExists('everflow_postbacks')
                ? (new \App\Repositories\EverflowRepository($this->db))->recentPostbacks(20)
                : [],
            'webhookUrl' => url('/webhooks/upgrade-chat'),
            'webhookAlias' => url('/api/webhook-upgradechat.php'),
            'everflow' => everflow_config(),
        ], 'admin');
    }

    public function storePlan(): never
    {
        $payload = $this->planPayload(true);
        if ($payload === null) {
            $this->redirect('/admin/subscriptions');
        }

        (new PlanRepository($this->db))->create($payload);
        (new AuditService($this->db))->log($this->auth->id(), 'plan_created', 'plan', null, $this->request);
        $this->flash('success', 'Plan created. Paid plans open checkout in the on-site modal.');
        $this->redirect('/admin/subscriptions');
    }

    public function updatePlan(string $id): never
    {
        $payload = $this->planPayload(false);
        if ($payload === null) {
            $this->redirect('/admin/subscriptions');
        }

        (new PlanRepository($this->db))->update((int) $id, $payload);
        $this->flash('success', 'Plan updated.');
        $this->redirect('/admin/subscriptions');
    }

    private function planPayload(bool $creating): ?array
    {
        $v = Validator::make($this->request->all(), [
            'name' => 'required|max:80',
            'description' => 'max:2000',
            'price' => 'numeric',
            'currency' => 'max:8',
            'billing_interval' => 'required|in:month,year,season',
            'badge' => 'max:40',
            'payment_url' => 'max:500',
        ]);

        $paymentUrl = $this->normalizedPaymentUrl();
        if ($paymentUrl === false) {
            $this->errors([
                'payment_url' => ['Paste a valid Upgrade.Chat checkout link, such as https://upgrade.chat/SERVER_ID/p/PRODUCT_ID.'],
            ]);
            $this->oldInput($this->request->all());
            $this->flash('error', 'The Upgrade.Chat payment link is invalid.');
            return null;
        }

        if ($v->fails()) {
            $this->errors($v->errors());
            $this->oldInput($this->request->all());
            $this->flash('error', $v->firstError() ?? 'Please check the plan details.');
            return null;
        }

        $slug = trim((string) $this->request->post('slug'));
        if ($creating && $slug === '') {
            $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $this->request->post('name')), '-'));
        }

        $features = array_values(array_filter(array_map('trim', explode("\n", (string) $this->request->post('features')))));
        $payload = [
            'name' => trim((string) $this->request->post('name')),
            'description' => trim((string) $this->request->post('description')),
            'price_cents' => (int) round(((float) $this->request->post('price', 0)) * 100),
            'currency' => strtoupper(trim((string) ($this->request->post('currency') ?: 'USD'))),
            'billing_interval' => (string) $this->request->post('billing_interval') ?: 'month',
            'features' => json_encode($features),
            'is_featured' => $this->request->post('is_featured') ? 1 : 0,
            'sort_order' => (int) $this->request->post('sort_order', 0),
            'badge' => trim((string) $this->request->post('badge')) ?: null,
            'payment_url' => $paymentUrl,
        ];

        if ($creating) {
            $payload['slug'] = $slug !== '' ? $slug : 'plan-' . bin2hex(random_bytes(3));
            $payload['is_active'] = 1;
        } else {
            $payload['is_active'] = $this->request->post('is_active') ? 1 : 0;
        }

        return $payload;
    }

    private function normalizedPaymentUrl(): string|false|null
    {
        $url = trim((string) $this->request->post('payment_url'));
        if ($url === '') {
            return null;
        }

        return is_upgrade_chat_url($url) ? $url : false;
    }
}
