<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\PlanRepository;

final class PricingController extends Controller
{
    public function index(): string
    {
        $status = strtolower(trim((string) ($this->request->query('status', '') ?: $this->request->query('checkout', ''))));
        if (in_array($status, ['success', 'done', 'complete', 'completed'], true)) {
            $token = trim((string) ($this->request->query('token', '') ?: $this->request->cookie('orion_pay', '')));
            $this->redirect('/thank-you' . ($token !== '' ? '?token=' . rawurlencode($token) : ''));
        }

        return $this->view('pricing/index', [
            'title' => 'The Playbook — Orion Bets',
            'metaDescription' => 'The Playbook is a daily picks subscription. Every morning you get the play, the price, and the size. Lock the founders rate before the first whistle.',
            'plans' => (new PlanRepository($this->db))->allActive(),
        ]);
    }
}
