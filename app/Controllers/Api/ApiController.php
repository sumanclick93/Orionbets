<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Repositories\EventRepository;
use App\Repositories\LeagueRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PickRepository;
use App\Repositories\SportRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\PerformanceService;

final class ApiController extends Controller
{
    public function picks(): never
    {
        $result = (new PickRepository($this->db))->search([
            'sport' => (string) $this->request->query('sport', ''),
            'status' => (string) $this->request->query('status', ''),
        ], max(1, (int) $this->request->query('page', 1)), 20);
        $this->json(['data' => $this->publicPicks($result['data']), 'total' => $result['total']]);
    }

    public function pick(string $id): never
    {
        $pick = (new PickRepository($this->db))->findById((int) $id);
        if (!$pick) {
            $this->json(['error' => 'Not found'], 404);
        }
        $this->json(['data' => $this->publicPick($pick)]);
    }

    public function performance(): never
    {
        $range = (string) $this->request->query('range', 'all');
        $service = new PerformanceService($this->db);
        $this->json([
            'summary' => $service->summary($range),
            'charts' => $service->chartPayload($range),
            'disclaimer' => 'DEMO DATA unless replaced by a live desk record.',
        ]);
    }

    public function sports(): never
    {
        $this->json(['data' => (new SportRepository($this->db))->allActive()]);
    }

    public function leagues(): never
    {
        $this->json(['data' => (new LeagueRepository($this->db))->all()]);
    }

    public function events(): never
    {
        $this->json(['data' => (new EventRepository($this->db))->upcoming()]);
    }

    public function me(): never
    {
        $user = $this->auth->user();
        $this->json([
            'data' => [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'roles' => $user['roles'],
                'premium' => $this->auth->isPremium(),
            ],
        ]);
    }

    public function mePicks(): never
    {
        $result = (new PickRepository($this->db))->search([], 1, 30);
        $this->json(['data' => $this->publicPicks($result['data'])]);
    }

    public function meSubscription(): never
    {
        $this->json(['data' => (new SubscriptionRepository($this->db))->currentForUser((int) $this->auth->id())]);
    }

    public function meNotifications(): never
    {
        $this->json(['data' => (new NotificationRepository($this->db))->forUser((int) $this->auth->id())]);
    }

    private function publicPicks(array $rows): array
    {
        return array_map(fn ($row) => $this->publicPick($row), $rows);
    }

    private function publicPick(array $pick): array
    {
        $gated = pick_should_gate($pick);
        return [
            'id' => (int) $pick['id'],
            'slug' => $pick['slug'],
            'title' => $pick['title'],
            'sport' => $pick['sport_name'] ?? null,
            'league' => $pick['league_name'] ?? null,
            'event' => $pick['event_name'] ?? null,
            'event_at' => $pick['event_at'] ?? null,
            'excerpt' => $gated ? null : $pick['analysis_excerpt'],
            'analysis' => $gated ? null : $pick['analysis'],
            'selection' => $gated ? null : ($pick['selection_line'] ?? null),
            'odds' => $gated ? null : ($pick['odds'] ?? null),
            'units' => $gated ? null : ($pick['units'] ?? null),
            'sportsbook' => $gated ? null : ($pick['sportsbook'] ?? null),
            'gated' => $gated,
            'confidence' => $gated ? null : (int) $pick['confidence'],
            'status' => $pick['status'],
            'is_premium' => (bool) $pick['is_premium'],
            'published_at' => $pick['published_at'],
            'result' => $pick['result'] ?? null,
        ];
    }
}
