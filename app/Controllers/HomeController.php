<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\PickRepository;
use App\Services\PerformanceService;

final class HomeController extends Controller
{
    public function index(): string
    {
        $picks = new PickRepository($this->db);
        $performance = (new PerformanceService($this->db))->summary('all');

        return $this->view('home/index', [
            'title' => settings('seo_title') ?: 'Orion Bets',
            'metaDescription' => settings('seo_description') ?: 'Our best bets, sent before kickoff. Daily picks. Public record. No excuses.',
            'featured' => $picks->featured(),
            'stats' => $performance,
            'countdownAt' => settings('countdown_at') ?: '2026-09-09T20:20:00-04:00',
            'countdownLabel' => settings('countdown_label') ?: 'Season One kicks off in',
        ]);
    }
}
