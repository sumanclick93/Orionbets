<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Repositories\PlanRepository;
use App\Services\AuditService;

final class AdminPlanController extends Controller
{
    public function index(): string
    {
        $repo = new PlanRepository($this->db);
        $plans = $repo->allWithSubscriberCounts();

        return $this->view('admin/plans/index', [
            'title' => 'Subscription Plans Management — Orion Bets',
            'plans' => $plans,
            'exportUrl' => url('/admin/plans/export-csv'),
        ], 'admin');
    }

    public function store(): never
    {
        $payload = $this->planPayload(true);
        if ($payload === null) {
            $this->redirect('/admin/plans');
        }

        $id = (new PlanRepository($this->db))->create($payload);
        (new AuditService($this->db))->log($this->auth->id(), 'plan_created', 'plan', (string) $id, $this->request, [
            'name' => $payload['name'],
            'price_cents' => $payload['price_cents'],
        ]);

        $this->flash('success', 'Plan created successfully. It is now active in the catalog.');
        $this->redirect('/admin/plans');
    }

    public function update(string $id): never
    {
        $planId = (int) $id;
        $repo = new PlanRepository($this->db);
        $existing = $repo->find($planId);
        if (!$existing) {
            throw new HttpException(404, 'Subscription plan not found.');
        }

        $payload = $this->planPayload(false);
        if ($payload === null) {
            $this->redirect('/admin/plans');
        }

        $repo->update($planId, $payload);
        (new AuditService($this->db))->log($this->auth->id(), 'plan_updated', 'plan', (string) $planId, $this->request, [
            'name' => $payload['name'],
            'price_cents' => $payload['price_cents'],
        ]);

        $this->flash('success', 'Plan #' . $planId . ' (' . $payload['name'] . ') updated successfully.');
        $this->redirect('/admin/plans');
    }

    public function toggleStatus(string $id): never
    {
        $planId = (int) $id;
        $repo = new PlanRepository($this->db);
        $existing = $repo->find($planId);
        if (!$existing) {
            throw new HttpException(404, 'Subscription plan not found.');
        }

        $isActive = $repo->toggleStatus($planId);
        (new AuditService($this->db))->log($this->auth->id(), 'plan_status_toggled', 'plan', (string) $planId, $this->request, [
            'is_active' => $isActive ? 1 : 0,
        ]);

        $this->flash('success', 'Plan #' . $planId . ' is now ' . ($isActive ? 'Active' : 'Archived') . '.');
        $this->redirect('/admin/plans');
    }

    public function destroy(string $id): never
    {
        $planId = (int) $id;
        $repo = new PlanRepository($this->db);
        $existing = $repo->find($planId);
        if (!$existing) {
            throw new HttpException(404, 'Subscription plan not found.');
        }

        // Check if there are active subscribers attached to this plan
        $activeSubs = 0;
        if ($this->db->tableExists('subscriptions')) {
            $activeSubs = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM subscriptions WHERE plan_id = :pid AND status IN ('active', 'trialing')",
                ['pid' => $planId]
            );
        }

        if ($activeSubs > 0) {
            // Cannot hard delete a plan with active subscribers; archive it instead
            $repo->update($planId, ['is_active' => 0]);
            $this->flash('error', 'Cannot delete plan with active subscribers. It has been archived instead.');
            $this->redirect('/admin/plans');
        }

        $repo->delete($planId);
        (new AuditService($this->db))->log($this->auth->id(), 'plan_deleted', 'plan', (string) $planId, $this->request, [
            'name' => $existing['name'],
        ]);

        $this->flash('success', 'Plan #' . $planId . ' deleted successfully.');
        $this->redirect('/admin/plans');
    }

    public function exportCsv(): never
    {
        $repo = new PlanRepository($this->db);
        $plans = $repo->allWithSubscriberCounts();

        $filename = 'subscription-plans-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new HttpException(500, 'Could not start the CSV export.');
        }

        fputcsv($out, [
            'Plan ID',
            'Plan Name',
            'Slug',
            'Price ($)',
            'Currency',
            'Billing Interval',
            'Badge',
            'Active Subscribers Count',
            'Total Subscribers Count',
            'Status',
            'Is Featured',
            'Sort Order',
            'Payment URL',
            'Features',
            'Created Date',
            'Updated Date',
        ]);

        foreach ($plans as $plan) {
            $priceFormatted = number_format(((int) ($plan['price_cents'] ?? 0)) / 100, 2, '.', '');
            $features = implode('; ', json_decode_array($plan['features'] ?? null));

            fputcsv($out, [
                $plan['id'] ?? '',
                $plan['name'] ?? '',
                $plan['slug'] ?? '',
                $priceFormatted,
                $plan['currency'] ?? 'USD',
                $plan['billing_interval'] ?? 'month',
                $plan['badge'] ?? '',
                $plan['active_subscribers_count'] ?? 0,
                $plan['total_subscribers_count'] ?? 0,
                !empty($plan['is_active']) ? 'Active' : 'Archived',
                !empty($plan['is_featured']) ? 'Yes' : 'No',
                $plan['sort_order'] ?? 0,
                $plan['payment_url'] ?? '',
                $features,
                $plan['created_at'] ?? '',
                $plan['updated_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
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
            'is_active' => $this->request->post('is_active') ? 1 : 0,
        ];

        if ($creating) {
            $payload['slug'] = $slug !== '' ? $slug : 'plan-' . bin2hex(random_bytes(3));
            if (!$this->request->has('is_active')) {
                $payload['is_active'] = 1;
            }
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
