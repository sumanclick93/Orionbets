<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\LeagueRepository;
use App\Repositories\PickRepository;
use App\Repositories\SportRepository;

final class PickController extends Controller
{
    public function index(): string
    {
        return $this->listing('live', 'Daily analysis — Orion Bets', 'Upcoming Orion Bets plays, live odds, and the active Playbook. Informational tracking only.');
    }

    public function playbook(): string
    {
        return $this->listing('live', 'The Playbook — Orion Bets', 'Active Playbook picks with live odds and analysis. Paid Members see the full card.');
    }

    public function show(string $slug): string
    {
        $pick = (new PickRepository($this->db))->findBySlug($slug);
        if (!$pick) {
            throw new \App\Core\Exceptions\HttpException(404, 'That analysis could not be found.');
        }
        if ((int) ($pick['is_active'] ?? 1) !== 1 && !auth()->isAdmin()) {
            throw new \App\Core\Exceptions\HttpException(404, 'That analysis could not be found.');
        }

        $gated = pick_should_gate($pick);

        return $this->view('picks/show', [
            'title' => $pick['title'] . ' — Orion Bets',
            'metaDescription' => $gated
                ? 'Live Playbook analysis is available to Paid Members. Informational tracking only.'
                : excerpt((string) $pick['analysis_excerpt'], 155),
            'pick' => $pick,
            'gated' => $gated,
        ]);
    }

    public function results(): string
    {
        return $this->view('picks/results', [
            'title' => 'Results — Orion Bets',
            'metaDescription' => 'Completed Orion Bets notes. Every result is posted publicly, win or lose.',
            'results' => (new PickRepository($this->db))->recentResults(80),
        ]);
    }

    private function listing(string $scope, string $title, string $meta): string
    {
        $filters = [
            'sport' => (string) $this->request->query('sport', ''),
            'league' => (string) $this->request->query('league', ''),
            'status' => (string) $this->request->query('status', ''),
            'access' => (string) $this->request->query('access', ''),
            'date' => (string) $this->request->query('date', ''),
            'q' => (string) $this->request->query('q', ''),
            'visible' => true,
            'live' => $scope === 'live',
        ];
        $page = max(1, (int) $this->request->query('page', 1));

        $result = (new PickRepository($this->db))->search($filters, $page, 9);

        return $this->view('picks/index', [
            'title' => $title,
            'metaDescription' => $meta,
            'picks' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 9,
            'filters' => $filters,
            'sports' => (new SportRepository($this->db))->allActive(),
            'leagues' => (new LeagueRepository($this->db))->all(),
            'playbook' => $scope === 'live',
        ]);
    }
}
