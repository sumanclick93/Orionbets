<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Services\CheckoutService;
use RuntimeException;
use Throwable;

final class WebhookController extends Controller
{
    public function upgradeChatPing(): never
    {
        $this->json([
            'ok' => true,
            'endpoint' => 'upgrade-chat',
            'aliases' => [
                url('/webhooks/upgrade-chat'),
                url('/api/webhook-upgradechat'),
                url('/api/webhook-upgradechat.php'),
            ],
            'message' => 'Ready for order.created, order.completed, order.updated, subscription.created, and subscription.renewed.',
        ]);
    }

    public function upgradeChat(): never
    {
        $payload = $this->request->json();
        if ($payload === []) {
            $payload = $this->request->all();
        }

        Logger::info('Upgrade.Chat webhook received', [
            'keys' => array_keys($payload),
            'ip' => $this->request->ip(),
            'path' => $this->request->path(),
        ]);

        try {
            $result = CheckoutService::make($this->db)->handleWebhook($payload, $this->request);
            $this->json($result);
        } catch (RuntimeException $e) {
            Logger::warning('Upgrade.Chat webhook rejected', ['error' => $e->getMessage()]);
            $this->json(['ok' => false, 'error' => $e->getMessage()], 401);
        } catch (Throwable $e) {
            Logger::error('Upgrade.Chat webhook failed', ['error' => $e->getMessage()]);
            $this->json(['ok' => false, 'error' => 'Webhook failed'], 500);
        }
    }
}
