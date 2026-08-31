<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\CheckoutService;
use RuntimeException;
use Throwable;

final class CheckoutController extends Controller
{
    public function start(): never
    {
        $payload = $this->request->json();
        if ($payload === []) {
            $payload = $this->request->all();
        }

        if ($this->auth->check()) {
            $user = $this->auth->user();
            if (empty($payload['email'])) {
                $payload['email'] = $user['email'] ?? '';
            }
            if (empty($payload['name'])) {
                $payload['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
        }

        try {
            $result = CheckoutService::make($this->db)->start($payload, $this->request, $this->auth->user());
            $this->json($result);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => 'Checkout could not start. Try again.'], 500);
        }
    }

    public function paypalCreateOrder(): never
    {
        try {
            $this->json(CheckoutService::make($this->db)->startPaypal($this->payload(), $this->request, $this->auth->user()));
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => 'PayPal could not start. Try again.'], 500);
        }
    }

    public function paypalCaptureOrder(): never
    {
        try {
            $this->json(CheckoutService::make($this->db)->capturePaypal($this->payload(), $this->request, $this->auth->user()));
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => 'PayPal could not complete this payment. Try again.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $payload = $this->request->json();
        return $payload !== [] ? $payload : $this->request->all();
    }

    public function status(): never
    {
        $token = trim((string) ($this->request->query('token', '') ?: $this->request->cookie('orion_pay', '')));
        try {
            $this->json(CheckoutService::make($this->db)->publicStatus($token, $this->request));
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        }
    }

    public function complete(): string
    {
        $token = trim((string) $this->request->query('token', ''));
        $tokenJs = json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $originJs = json_encode(web_base_url(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $thanksJs = json_encode(url('/thank-you' . ($token !== '' ? '?token=' . $token : '')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment received</title>'
            . '<style>body{margin:0;background:#fff;color:#202942;font-family:Arial,sans-serif;display:grid;place-items:center;min-height:100vh;padding:2rem;text-align:center}p{max-width:22rem;line-height:1.45}</style></head><body>'
            . '<p>Payment received. You can stay on this page — Orion Bets is confirming the Upgrade.Chat order.</p>'
            . '<script>(function(){var token=' . $tokenJs . ';var origin=' . $originJs . ';var thanks=' . $thanksJs . ';'
            . 'var payload={source:"orion-checkout",event:"complete",token:token};'
            . 'try{if(window.opener&&!window.opener.closed){window.opener.postMessage(payload,origin||"*");window.close();return;}}catch(err){}'
            . 'try{if(window.parent&&window.parent!==window){window.parent.postMessage(payload,origin||"*");return;}}catch(err){}'
            . 'window.location.replace(thanks);'
            . '})();</script></body></html>';
    }
}
