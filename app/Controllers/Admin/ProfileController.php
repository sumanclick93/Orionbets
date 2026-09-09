<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Validator;
use App\Repositories\UserRepository;
use App\Services\AuditService;

final class ProfileController extends Controller
{
    public function index(): string
    {
        $user = $this->auth->user();

        return $this->view('pages/admin/profile/index', [
            'title' => 'Admin Profile & Security — Orion Bets',
            'user' => $user,
        ], 'admin');
    }

    public function updateProfile(): never
    {
        $user = $this->auth->user();
        if (!$user) {
            $this->redirect('/login');
        }

        $firstName = trim((string) ($this->request->post('first_name') ?? $user['first_name'] ?? ''));
        $lastName = trim((string) ($this->request->post('last_name') ?? $user['last_name'] ?? ''));
        $email = strtolower(trim((string) $this->request->post('email', '')));

        $v = Validator::make(
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
            ],
            [
                'first_name' => 'required|max:80',
                'last_name' => 'max:80',
                'email' => 'required|email',
            ],
            [
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'email' => 'Email address',
            ]
        );

        if ($v->fails()) {
            $this->errors($v->errors());
            $this->redirect('/admin/profile');
        }

        // Uniqueness check: Ensure new email is not already taken by another user (id != current user id)
        $existing = $this->db->fetch(
            'SELECT id FROM users WHERE email = :email AND id != :id AND deleted_at IS NULL LIMIT 1',
            ['email' => $email, 'id' => (int) $user['id']]
        );

        if ($existing !== null) {
            $this->flash('error', 'That email address is already in use by another user.');
            $this->redirect('/admin/profile');
        }

        $repo = new UserRepository($this->db);
        $repo->update((int) $user['id'], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);

        (new AuditService($this->db))->log(
            (int) $user['id'],
            'admin_profile_updated',
            'user',
            (string) $user['id'],
            $this->request,
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'new_email' => $email,
            ]
        );

        $this->flash('success', 'Admin profile details updated successfully.');
        $this->redirect('/admin/profile');
    }

    public function updateEmail(): never
    {
        $this->updateProfile();
    }

    public function updatePassword(): never
    {
        $user = $this->auth->user();
        if (!$user) {
            $this->redirect('/login');
        }

        $currentPassword = (string) $this->request->post('current_password', '');
        $newPassword = (string) ($this->request->post('new_password') ?? $this->request->post('password') ?? '');
        $confirmPassword = (string) ($this->request->post('confirm_password') ?? $this->request->post('password_confirmation') ?? '');

        $input = [
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'confirm_password' => $confirmPassword,
        ];

        $v = Validator::make(
            $input,
            [
                'current_password' => 'required',
                'new_password' => 'required|min:8',
                'confirm_password' => 'required',
            ],
            [
                'current_password' => 'Current password',
                'new_password' => 'New password',
                'confirm_password' => 'Confirm new password',
            ]
        );

        if ($v->fails()) {
            $this->errors($v->errors());
            $this->redirect('/admin/profile');
        }

        if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            $this->flash('error', 'Current password is incorrect.');
            $this->redirect('/admin/profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->flash('error', 'New password and confirm new password do not match.');
            $this->redirect('/admin/profile');
        }

        $repo = new UserRepository($this->db);
        $repo->update((int) $user['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        (new AuditService($this->db))->log(
            (int) $user['id'],
            'admin_password_updated',
            'user',
            (string) $user['id'],
            $this->request
        );

        $this->flash('success', 'Admin password updated successfully.');
        $this->redirect('/admin/profile');
    }
}
