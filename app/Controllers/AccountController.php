<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Validator;
use App\Repositories\NotificationRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;

final class AccountController extends Controller
{
    public function settings(): string
    {
        $uid = (int) $this->auth->id();
        return $this->view('account/settings', [
            'title' => 'Account settings — Orion Bets',
            'user' => $this->auth->user(),
            'prefs' => (new NotificationRepository($this->db))->preferences($uid),
            'subscription' => (new SubscriptionRepository($this->db))->currentForUser($uid),
            'plans' => (new PlanRepository($this->db))->allActive(),
        ], 'dashboard');
    }

    public function updateSettings(): never
    {
        $v = Validator::make($this->request->all(), [
            'first_name' => 'required|max:80',
            'last_name' => 'required|max:80',
            'timezone' => 'required',
            'theme_preference' => 'required|in:light,dark,system',
        ]);
        if ($v->fails()) {
            $this->errors($v->errors());
            $this->redirect('/account/settings');
        }

        $repo = new UserRepository($this->db);
        $user = $this->auth->user();
        $userData = [
            'first_name' => trim((string) $this->request->post('first_name')),
            'last_name' => trim((string) $this->request->post('last_name')),
            'timezone' => (string) $this->request->post('timezone'),
            'theme_preference' => (string) $this->request->post('theme_preference'),
        ];

        // Avatar Upload or Removal
        $uploadService = new \App\Services\UploadService();
        if ($this->request->post('remove_avatar') === '1') {
            if (!empty($user['avatar'])) {
                $uploadService->deleteFile($user['avatar']);
            }
            $userData['avatar'] = null;
        } elseif ($this->request->hasFile('avatar_file')) {
            try {
                $avatarPath = $uploadService->uploadImage($this->request->file('avatar_file'), 'avatars');
                if (!empty($user['avatar'])) {
                    $uploadService->deleteFile($user['avatar']);
                }
                $userData['avatar'] = $avatarPath;
            } catch (\Throwable $e) {
                $this->flash('error', 'Avatar upload failed: ' . $e->getMessage());
                $this->redirect('/account/settings');
            }
        }

        $repo->update((int) $this->auth->id(), $userData);

        $notif = new NotificationRepository($this->db);
        foreach (['daily_pick', 'pick_result', 'subscription', 'account'] as $event) {
            $notif->upsertPreference((int) $this->auth->id(), 'email', $event, (bool) $this->request->post('email_' . $event));
            $notif->upsertPreference((int) $this->auth->id(), 'in_app', $event, (bool) $this->request->post('inapp_' . $event));
        }

        (new AuditService($this->db))->log($this->auth->id(), 'account_updated', 'user', (string) $this->auth->id(), $this->request);
        $this->flash('success', 'Profile settings saved.');
        $this->redirect('/account/settings');
    }

    public function changePassword(): never
    {
        $current = (string) $this->request->post('current_password', '');
        $new = (string) ($this->request->post('new_password') ?? $this->request->post('password') ?? '');
        $confirm = (string) ($this->request->post('confirm_password') ?? $this->request->post('password_confirmation') ?? '');

        $input = [
            'current_password' => $current,
            'password' => $new,
            'password_confirmation' => $confirm,
        ];

        $v = Validator::make($input, [
            'current_password' => 'required',
            'password' => 'required|min:10|confirmed',
        ], [
            'current_password' => 'Current password',
            'password' => 'New password',
        ]);

        if ($v->fails()) {
            $this->errors($v->errors());
            $this->redirect('/account/settings');
        }

        $user = $this->auth->user();
        if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
            $this->flash('error', 'Current password is incorrect.');
            $this->redirect('/account/settings');
        }

        (new UserRepository($this->db))->update((int) $user['id'], [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
        ]);
        (new AuditService($this->db))->log((int) $user['id'], 'password_changed', 'user', (string) $user['id'], $this->request);
        $this->flash('success', 'Password updated successfully.');
        $this->redirect('/account/settings');
    }

    public function requestPasswordReset(): never
    {
        $user = $this->auth->user();
        if (!$user || empty($user['email'])) {
            $this->flash('error', 'Could not locate account email.');
            $this->redirect('/account/settings');
        }

        $service = new \App\Services\AuthService(
            $this->db,
            new UserRepository($this->db),
            new Mailer(),
            new AuditService($this->db)
        );

        $service->requestReset((string) $user['email'], $this->request);
        $this->flash('success', 'A password setup / reset link has been sent to ' . $user['email'] . '. Please check your inbox.');
        $this->redirect('/account/settings');
    }

    public function deleteAccount(): never
    {
        $id = (int) $this->auth->id();
        $repo = new UserRepository($this->db);
        $user = $repo->findById($id, true);
        $repo->softDelete($id);
        (new AuditService($this->db))->log($id, 'account_deleted', 'user', (string) $id, $this->request, [
            'original_email' => $user ? $repo->originalEmail($user) : null,
        ]);
        $this->auth->logout();
        $this->flash('success', 'Account deactivated.');
        $this->redirect('/');
    }

    public function subscription(): string
    {
        $uid = (int) $this->auth->id();
        return $this->view('account/subscription', [
            'title' => 'Subscription — Orion Bets',
            'current' => (new SubscriptionRepository($this->db))->currentForUser($uid),
            'transactions' => (new SubscriptionRepository($this->db))->transactions($uid),
            'plans' => (new PlanRepository($this->db))->allActive(),
        ], 'dashboard');
    }

    public function updateSubscription(): never
    {
        $action = (string) $this->request->post('action');
        $service = new SubscriptionService(
            $this->db,
            new SubscriptionRepository($this->db),
            new PlanRepository($this->db),
            new UserRepository($this->db),
            new NotificationService($this->db, new NotificationRepository($this->db), new Mailer(), new UserRepository($this->db)),
            new AuditService($this->db),
            new Mailer()
        );

        if ($action === 'cancel') {
            $service->cancel((int) $this->auth->id(), $this->request, (string) $this->request->post('cancel_reason'));
            $this->flash('success', 'Subscription cancelled.');
            $this->redirect('/account/subscription');
        }

        $planId = (int) $this->request->post('plan_id');
        $service->subscribe((int) $this->auth->id(), $planId, $this->request);
        $this->flash('success', 'Plan updated in demo billing mode. No live payment was processed.');
        $this->redirect('/account/subscription');
    }
}
