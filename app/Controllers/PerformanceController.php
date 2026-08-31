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
        $service = new PerformanceService($this->db);

        return $this->view('performance/index', [
            'title' => 'Performance board — Orion Bets',
            'metaDescription' => 'Transparent tracking of Orion Bets analysis outcomes. Seeded figures are demo data until a live desk record is entered.',
            'stats' => $service->summary($range),
            'charts' => $service->chartPayload($range),
            'range' => $range,
        ]);
    }
}
