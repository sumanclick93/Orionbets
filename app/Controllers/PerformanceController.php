<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\PerformanceService;

final class PerformanceController extends Controller
{
    public function index(): string
    {
        $range = (string) $this->request->query('range', 'all');
        $season = (string) $this->request->query('season', '');
        $league = (string) $this->request->query('league', '');

        $service = new PerformanceService($this->db);

        $availableLeagues = $service->getAvailableLeagues();
        $availableSeasons = $service->getAvailableSeasons();

        return $this->view('performance/index', [
            'title' => 'Performance board — Orion Bets',
            'metaDescription' => 'Transparent tracking of Orion Bets analysis outcomes. Seeded figures are demo data until a live desk record is entered.',
            'stats' => $service->summary($range, $season, $league),
            'charts' => $service->chartPayload($range, $season, $league),
            'range' => $range,
            'season' => $season,
            'league' => $league,
            'availableLeagues' => $availableLeagues,
            'availableSeasons' => $availableSeasons,
        ]);
    }
}
