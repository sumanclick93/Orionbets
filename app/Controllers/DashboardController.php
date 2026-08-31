<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\NotificationRepository;
use App\Repositories\PickRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\PerformanceService;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $uid = (int) $this->auth->id();
        $picks = new PickRepository($this->db);

        return $this->view('dashboard/index', [
            'title' => 'Desk — Orion Bets',
            'subscription' => (new SubscriptionRepository($this->db))->currentForUser($uid),
            'today' => $picks->todayPlaybook(),
            'results' => $picks->recentResults(6),
            'stats' => (new PerformanceService($this->db))->summary('30d'),
            'notifications' => (new NotificationRepository($this->db))->forUser($uid, 8),
        ], 'dashboard');
    }

    public function picks(): string
    {
        $filters = [
            'sport' => (string) $this->request->query('sport', ''),
            'status' => (string) $this->request->query('status', ''),
            'date' => (string) $this->request->query('date', ''),
            'q' => (string) $this->request->query('q', ''),
        ];
        $result = (new PickRepository($this->db))->search($filters + ['visible' => true], max(1, (int) $this->request->query('page', 1)), 20);

        return $this->view('dashboard/picks', [
            'title' => 'Playbook archive — Orion Bets',
            'picks' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'filters' => $filters,
        ], 'dashboard');
    }

    public function results(): string
    {
        return $this->view('dashboard/results', [
            'title' => 'Results — Orion Bets',
            'results' => (new PickRepository($this->db))->recentResults(40),
        ], 'dashboard');
    }
}
