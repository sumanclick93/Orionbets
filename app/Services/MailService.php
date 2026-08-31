<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;

final class MailService
{
    public function __construct(private Mailer $mailer)
    {
    }

    public function send(string $to, string $subject, string $template, array $data = []): bool
    {
        return $this->mailer->send($to, $subject, $template, $data);
    }
}
