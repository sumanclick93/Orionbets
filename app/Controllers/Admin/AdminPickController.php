<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Repositories\EventRepository;
use App\Repositories\LeagueRepository;
use App\Repositories\PickRepository;
use App\Repositories\SportRepository;
use App\Services\ActionNetworkService;
use App\Services\AuditService;
use App\Services\PickService;

final class AdminPickController extends Controller
{
    public function index(): string
    {
        $archived = $this->request->query('view', '') === 'archived';
        $perPageQuery = (string) $this->request->query('per_page', '10');
        $perPage = strtolower($perPageQuery) === 'all' ? 10000 : max(1, (int) $perPageQuery);
        $filters = [
            'q' => (string) $this->request->query('q', ''),
            'status' => (string) $this->request->query('status', ''),
            'league' => (string) $this->request->query('league', ''),
            'active' => (string) $this->request->query('active', ''),
            'date' => (string) $this->request->query('date', ''),
            'archived' => $archived,
        ];

        $result = (new PickRepository($this->db))->search($filters, max(1, (int) $this->request->query('page', 1)), $perPage);
        $perfService = new \App\Services\PerformanceService($this->db);

        return $this->view('admin/picks/index', [
            'title' => 'Manage picks — Orion Bets',
            'picks' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $perPageQuery,
            'perPageNum' => $perPage,
            'archived' => $archived,
            'filters' => $filters,
            'availableLeagues' => $perfService->getAvailableLeagues(),
            'lastSync' => ActionNetworkService::make($this->db)->lastSync(),
        ], 'admin');
    }

    public function create(): string
    {
        return $this->form(null);
    }

    public function edit(string $id): string
    {
        $pick = (new PickRepository($this->db))->findById((int) $id);
        if (!$pick) {
            throw new \App\Core\Exceptions\HttpException(404, 'Pick not found.');
        }
        return $this->form($pick);
    }

    public function store(): never
    {
        $this->persist(null);
    }

    public function update(string $id): never
    {
        $this->persist((int) $id);
    }

    public function toggleStatus(string $id): never
    {
        $pick = (new PickRepository($this->db))->toggleActive((int) $id);
        if (!$pick) {
            throw new HttpException(404, 'Pick not found.');
        }
        (new AuditService($this->db))->log($this->auth->id(), 'pick_toggled', 'pick', $id, $this->request, [
            'is_active' => (int) $pick['is_active'],
        ]);
        $active = (int) $pick['is_active'] === 1;
        if ($this->request->isAjax()) {
            $this->json([
                'ok' => true,
                'id' => (int) $pick['id'],
                'is_active' => $active,
                'label' => $active ? 'Active' : 'Hidden',
            ]);
        }
        $this->flash('success', $active ? 'Pick is visible on the site.' : 'Pick is hidden from the site.');
        $this->redirect('/admin/picks');
    }

    public function destroy(string $id): never
    {
        (new PickRepository($this->db))->archive((int) $id);
        (new AuditService($this->db))->log($this->auth->id(), 'pick_archived', 'pick', $id, $this->request);
        $this->flash('success', 'Analysis archived.');
        $this->redirect('/admin/picks');
    }

    public function restore(string $id): never
    {
        $picks = new PickRepository($this->db);
        $pick = $picks->findById((int) $id, true);
        if (!$pick) {
            throw new \App\Core\Exceptions\HttpException(404, 'Pick not found.');
        }

        $picks->restore((int) $id);
        (new AuditService($this->db))->log($this->auth->id(), 'pick_restored', 'pick', $id, $this->request);
        $this->flash('success', 'Analysis restored.');
        $this->redirect('/admin/picks');
    }

    private function form(?array $pick): string
    {
        return $this->view('admin/picks/form', [
            'title' => $pick ? 'Edit analysis' : 'New analysis',
            'pick' => $pick,
            'sports' => (new SportRepository($this->db))->all(),
            'leagues' => (new LeagueRepository($this->db))->all(),
            'events' => (new EventRepository($this->db))->upcoming(),
        ], 'admin');
    }

    private function persist(?int $id): never
    {
        $v = Validator::make($this->request->all(), [
            'title' => 'required|max:190',
            'sport_id' => 'required|integer',
            'status' => 'required',
        ]);
        if ($v->fails()) {
            $this->errors($v->errors());
            $this->oldInput($this->request->all());
            $this->redirect($id ? '/admin/picks/' . $id . '/edit' : '/admin/picks');
        }

        $service = new PickService($this->db, new PickRepository($this->db), new AuditService($this->db));
        $service->save($this->request->all(), (int) $this->auth->id(), $this->request, $id);
        $this->flash('success', 'Analysis saved.');
        $this->redirect('/admin/picks');
    }
}
