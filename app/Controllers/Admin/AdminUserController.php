<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\MemberHistoryService;

final class AdminUserController extends Controller
{
    public function index(): string
    {
        $filters = $this->filters();
        $repo = new UserRepository($this->db);
        $result = $repo->paginate($filters);

        $users = array_map(static function (array $user) use ($repo): array {
            $user['display_email'] = $repo->originalEmail($user);
            return $user;
        }, $result['data']);

        $exportParams = array_filter($filters, static fn ($v) => $v !== '' && $v !== 'all');

        return $this->view('admin/users/index', [
            'title' => 'Members — Orion Bets',
            'users' => $users,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'q' => $filters['q'],
            'status' => $filters['status'],
            'role' => $filters['role'],
            'tier' => $filters['tier'],
            'exportUrl' => url('/admin/users/export-csv' . ($exportParams ? '?' . http_build_query($exportParams) : '')),
        ], 'admin');
    }

    public function exportCsv(): never
    {
        $filters = $this->filters();
        $repo = new UserRepository($this->db);
        $rows = $repo->exportUsers($filters);

        $filename = 'users-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new HttpException(500, 'Could not start the CSV export.');
        }

        fputcsv($out, [
            'User ID',
            'First Name',
            'Last Name',
            'Email',
            'Discord Link Status',
            'Discord ID',
            'Assigned Roles',
            'Tier Badge',
            'Account Status',
            'Is Guest',
            'Registered Date',
            'Last Login Date',
            'Last Login IP',
        ]);

        foreach ($rows as $user) {
            $deleted = !empty($user['deleted_at']);
            $suspended = !$deleted && empty($user['is_active']);
            $guest = !$deleted && !empty($user['is_guest']);

            $statusLabel = 'Active';
            if ($deleted) {
                $statusLabel = 'Deleted';
            } elseif ($suspended) {
                $statusLabel = 'Suspended';
            } elseif ($guest) {
                $statusLabel = 'Guest';
            }

            $discordStatus = !empty($user['discord_id']) ? 'Linked (' . $user['discord_id'] . ')' : 'None';

            fputcsv($out, [
                $user['id'] ?? '',
                $user['first_name'] ?? '',
                $user['last_name'] ?? '',
                $repo->originalEmail($user),
                $discordStatus,
                $user['discord_id'] ?? '',
                implode(', ', $user['roles'] ?? []),
                $user['tier'] ?? 'Free Member',
                $statusLabel,
                !empty($user['is_guest']) ? 'Yes' : 'No',
                $user['created_at'] ?? '',
                $user['last_login_at'] ?? '',
                $user['last_login_ip'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * @return array{q: string, status: string, role: string, tier: string, page: int, per_page: int}
     */
    private function filters(): array
    {
        $status = (string) $this->request->query('status', '');
        if (!in_array($status, ['', 'all', 'active', 'guest', 'suspended', 'deleted'], true)) {
            $status = '';
        }

        $role = (string) $this->request->query('role', '');
        if (!in_array($role, ['', 'all', 'user', 'premium_user', 'editor', 'admin', 'super_admin'], true)) {
            $role = '';
        }

        $tier = (string) $this->request->query('tier', '');
        if (!in_array($tier, ['', 'all', 'free', 'paid'], true)) {
            $tier = '';
        }

        return [
            'q' => trim((string) $this->request->query('q', '')),
            'status' => $status,
            'role' => $role,
            'tier' => $tier,
            'page' => max(1, (int) $this->request->query('page', 1)),
            'per_page' => max(10, min(100, (int) $this->request->query('per_page', 20))),
        ];
    }

    public function show(string $id): string
    {
        $user = $this->member((int) $id);
        $history = MemberHistoryService::make($this->db)->dossier($user);

        return $this->view('admin/users/show', [
            'title' => trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')) ?: $user['email'],
            'user' => $user,
            'history' => $history,
            'isSelf' => $this->isSelf($user),
            'canManage' => $this->canManage($user),
        ], 'admin');
    }

    public function update(string $id): never
    {
        if (!$this->auth->isAdmin()) {
            throw new HttpException(403, 'Editors cannot change member roles.');
        }

        $repo = new UserRepository($this->db);
        $user = $this->member((int) $id);
        if (!empty($user['deleted_at'])) {
            $this->flash('error', 'Restore this account before changing roles.');
            $this->redirect('/admin/users/' . $id);
        }

        $this->assertCanManage($user, 'update');

        $role = (string) $this->request->post('role', 'user');
        if (in_array($role, ['user', 'premium_user', 'editor', 'admin', 'super_admin'], true)) {
            if ($role === 'super_admin' && !$this->auth->isSuperAdmin()) {
                $this->flash('error', 'Only a super admin can assign that role.');
                $this->redirect('/admin/users/' . $id);
            }
            $repo->setRoles((int) $id, [$role]);
        }

        (new AuditService($this->db))->log($this->auth->id(), 'user_updated', 'user', $id, $this->request);
        $this->flash('success', 'Member updated.');
        $this->redirect('/admin/users/' . $id);
    }

    public function suspend(string $id): never
    {
        $repo = new UserRepository($this->db);
        $user = $this->member((int) $id);
        $this->assertCanManage($user, 'suspend');
        if (!empty($user['deleted_at'])) {
            $this->flash('error', 'This account is already deleted.');
            $this->redirect('/admin/users/' . $id);
        }

        $repo->suspend((int) $id);
        (new AuditService($this->db))->log($this->auth->id(), 'user_suspended', 'user', $id, $this->request, [
            'email' => $repo->originalEmail($user),
        ]);
        $this->flash('success', 'Account suspended. They cannot sign in.');
        $this->redirect('/admin/users/' . $id);
    }

    public function unsuspend(string $id): never
    {
        $repo = new UserRepository($this->db);
        $user = $this->member((int) $id);
        $this->assertCanManage($user, 'reinstate');
        if (!empty($user['deleted_at'])) {
            $this->flash('error', 'Restore this account first.');
            $this->redirect('/admin/users/' . $id);
        }

        $repo->unsuspend((int) $id);
        (new AuditService($this->db))->log($this->auth->id(), 'user_unsuspended', 'user', $id, $this->request, [
            'email' => $repo->originalEmail($user),
        ]);
        $this->flash('success', 'Account reinstated.');
        $this->redirect('/admin/users/' . $id);
    }

    public function destroy(string $id): never
    {
        $repo = new UserRepository($this->db);
        $user = $this->member((int) $id);
        $this->assertCanManage($user, 'delete');
        if (!empty($user['deleted_at'])) {
            $this->flash('error', 'This account is already deleted.');
            $this->redirect('/admin/users/' . $id);
        }

        $original = $repo->originalEmail($user);
        $repo->softDelete((int) $id);
        (new AuditService($this->db))->log($this->auth->id(), 'user_deleted', 'user', $id, $this->request, [
            'original_email' => $original,
        ]);
        $this->flash('success', 'Account deleted. History stays in the database and can be restored.');
        $this->redirect('/admin/users/' . $id);
    }

    public function restore(string $id): never
    {
        $repo = new UserRepository($this->db);
        $user = $this->member((int) $id);
        $this->assertCanManage($user, 'restore');
        if (empty($user['deleted_at'])) {
            $this->flash('error', 'This account is not deleted.');
            $this->redirect('/admin/users/' . $id);
        }

        try {
            $repo->restore((int) $id);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/users/' . $id);
        }

        (new AuditService($this->db))->log($this->auth->id(), 'user_restored', 'user', $id, $this->request, [
            'email' => $repo->originalEmail($user),
        ]);
        $this->flash('success', 'Account restored.');
        $this->redirect('/admin/users/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function member(int $id): array
    {
        $user = (new UserRepository($this->db))->findById($id, true);
        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isSelf(array $user): bool
    {
        return (int) $user['id'] === (int) $this->auth->id();
    }

    /**
     * @param array<string, mixed> $user
     */
    private function canManage(array $user): bool
    {
        if ($this->isSelf($user)) {
            return false;
        }
        $roles = $user['roles'] ?? [];
        if (in_array('super_admin', $roles, true) && !$this->auth->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function assertCanManage(array $user, string $action): void
    {
        if ($this->isSelf($user)) {
            $this->flash('error', 'You cannot ' . $action . ' your own account.');
            $this->redirect('/admin/users/' . $user['id']);
        }
        if (in_array('super_admin', $user['roles'] ?? [], true) && !$this->auth->isSuperAdmin()) {
            $this->flash('error', 'Only a super admin can ' . $action . ' a super admin.');
            $this->redirect('/admin/users/' . $user['id']);
        }
    }
}
