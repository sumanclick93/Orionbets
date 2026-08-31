<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\PickRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Services\PerformanceService;

final class AdminDashboardController extends Controller
{
    public function index(): string
    {
        $users = new UserRepository($this->db);
        $subs = new SubscriptionRepository($this->db);
        $picks = new PickRepository($this->db);
        $counts = $picks->counts();

        return $this->view('admin/index', [
            'title' => 'Operations — Orion Bets',
            'users' => $users->countActive(),
            'subscribers' => $subs->activeCount(),
            'dau' => $users->dailyActive(1),
            'published' => $counts['published'],
            'completed' => $counts['completed'],
            'revenue' => $subs->revenueCents(),
            'recent' => $users->recent(),
            'stats' => (new PerformanceService($this->db))->summary('all'),
        ], 'admin');
    }
}
