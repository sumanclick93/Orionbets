<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\View;
use App\Repositories\GeoRestrictionRepository;
use App\Services\GeoBlockService;
use App\Services\GeoIpService;
use App\Services\SettingsService;
use Throwable;

final class GeoBlockMiddleware implements MiddlewareInterface
{
    private static bool $ran = false;

    public function handle(Request $request, Application $app, array $params = []): void
    {
        if (self::$ran || $this->shouldBypass($request)) {
            return;
        }
        self::$ran = true;

        $location = [];
        $decision = [];
        $block = null;

        try {
            if (!$app->db->tableExists('geo_restrictions')) {
                return;
            }

            $block = new GeoBlockService(new GeoRestrictionRepository($app->db), new SettingsService($app->db));
            $location = (new GeoIpService())->locate($request, false);
            // Country / state / city rules are the source of truth. The admin
            // master switch must not leave a restricted region publicly open.
            $decision = $block->evaluate($location, false);

            if (empty($decision['blocked'])) {
                return;
            }

            if (!$block->enabled()) {
                $block->setEnabled(true);
            }
        } catch (Throwable $e) {
            \App\Core\Logger::error('Geo block check failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if (empty($decision['blocked']) || !$block instanceof GeoBlockService) {
                return;
            }
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $payload = [
            'error' => 'geo_restricted',
            'message' => 'This site is not available in your region.',
            'country' => $location['country'] ?? null,
            'state' => $location['state'] ?? null,
            'city' => $location['city'] ?? null,
        ];

        if ($request->isApi() || $request->isAjax()) {
            $app->response->json($payload, 451);
        }

        $copy = $block->message();
        $html = View::render('errors/geo-blocked', [
            'title' => $copy['title'],
            'kicker' => $copy['kicker'],
            'copy' => $copy['copy'],
            'location' => $location,
            'decision' => $decision,
            'status' => 451,
        ], 'blocked');

        $app->response->html($html, 451);
    }

    private function shouldBypass(Request $request): bool
    {
        $path = rtrim($request->path(), '/') ?: '/';
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/webhooks') || str_starts_with($path, '/api/webhook') || str_starts_with($path, '/everflow')) {
            return true;
        }

        return in_array($path, ['/login', '/logout', '/checkout/complete', '/checkout/status', '/thank-you'], true);
    }
}
