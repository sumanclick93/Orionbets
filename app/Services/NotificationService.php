<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Mailer;
use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;

final class NotificationService
{
    public function __construct(
        private Database $db,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private UserRepository $users
    ) {
    }

    public function send(int $userId, string $type, string $title, string $body, array $data = []): void
    {
        $prefs = $this->notifications->preferences($userId);
        $map = [];
        foreach ($prefs as $pref) {
            $map[$pref['channel'] . '.' . $pref['event_type']] = (int) $pref['enabled'] === 1;
        }

        $event = match ($type) {
            'daily_pick', 'pick_result', 'subscription', 'account' => $type,
            default => 'account',
        };

        if (($map['in_app.' . $event] ?? true) === true) {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
            ]);
        }

        if (($map['email.' . $event] ?? true) === true) {
            $user = $this->users->findById($userId);
            if ($user) {
                $template = match ($type) {
                    'daily_pick' => 'daily-pick',
                    'subscription' => 'subscription',
                    default => 'account',
                };
                $this->mailer->send($user['email'], $title, $template, [
                    'user' => $user,
                    'title' => $title,
                    'body' => $body,
                ]);
            }
        }
    }
}
