<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\EventRepository;
use App\Services\AuditService;

final class AdminCatalogController extends Controller
{
    public function events(): string
    {
        $filters = [
            'q' => (string) $this->request->query('q', ''),
            'status' => (string) $this->request->query('status', ''),
            'league' => (string) $this->request->query('league', ''),
            'active' => (string) $this->request->query('active', ''),
            'date' => (string) $this->request->query('date', ''),
        ];

        $result = (new EventRepository($this->db))->search($filters, max(1, (int) $this->request->query('page', 1)), 50);
        $perfService = new \App\Services\PerformanceService($this->db);

        return $this->view('admin/events/index', [
            'title' => 'Events',
            'events' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'filters' => $filters,
            'availableLeagues' => $perfService->getAvailableLeagues(),
            'lastSync' => \App\Services\ActionNetworkService::make($this->db)->lastSync('scoreboard'),
        ], 'admin');
    }

    public function toggleEventStatus(string $id): never
    {
        $event = (new EventRepository($this->db))->toggleActive((int) $id);
        if (!$event) {
            throw new \App\Core\Exceptions\HttpException(404, 'Event not found.');
        }
        (new AuditService($this->db))->log($this->auth->id(), 'event_toggled', 'event', $id, $this->request, [
            'is_active' => (int) $event['is_active'],
        ]);
        $active = (int) $event['is_active'] === 1;
        if ($this->request->isAjax()) {
            $this->json([
                'ok' => true,
                'id' => (int) $event['id'],
                'is_active' => $active,
                'label' => $active ? 'Active' : 'Hidden',
            ]);
        }
        $this->flash('success', $active ? 'Event is visible.' : 'Event is hidden.');
        $this->redirect('/admin/events');
    }

    public function destroyEvent(string $id): never
    {
        (new EventRepository($this->db))->delete((int) $id);
        (new AuditService($this->db))->log($this->auth->id(), 'event_deleted', 'event', $id, $this->request);
        $this->flash('success', 'Event archived.');
        $this->redirect('/admin/events');
    }
}
