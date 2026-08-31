<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\CheckoutRepository;
use App\Services\CheckoutService;
use App\Services\EverflowService;

final class ThankYouController extends Controller
{
    public function index(): string
    {
        $token = trim((string) ($this->request->query('token', '') ?: $this->request->cookie('orion_pay', '')));
        $session = null;
        $public = null;
        if ($token !== '') {
            try {
                $public = CheckoutService::make($this->db)->publicStatus($token, $this->request);
                $session = (new CheckoutRepository($this->db))->findByToken($token);
            } catch (\Throwable) {
                $session = (new CheckoutRepository($this->db))->findByToken($token);
                $public = $session ? [
                    'token' => $token,
                    'status' => $session['status'],
                    'email' => $session['email'],
                    'order_id' => $session['provider_order_id'] ?? null,
                    'transaction_id' => $session['provider_transaction_id'] ?? null,
                    'everflow_transaction_id' => $session['everflow_transaction_id'] ?? null,
                    'amount' => null,
                    'currency' => 'USD',
                ] : null;
            }
        }

        $ef = EverflowService::make($this->db);
        $status = (string) ($public['status'] ?? '');
        $paid = $status === 'completed';
        $pending = in_array($status, ['pending', 'processing'], true);

        return $this->view('checkout/thank-you', [
            'title' => $paid ? 'Payment confirmed — Orion Bets' : 'Confirming payment — Orion Bets',
            'metaDescription' => 'Your guest payment is confirmed. Check your email for Discord access to The Playbook.',
            'session' => $public,
            'token' => $token,
            'paid' => $paid,
            'pending' => $pending,
            'everflowTid' => EverflowService::normalizeId((string) (
                $public['everflow_transaction_id']
                ?? $session['everflow_transaction_id']
                ?? $ef->currentId($this->request)
            )),
            'everflow' => $ef->frontendConfig(),
        ]);
    }
}
