<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    public function send(string $to, string $subject, string $view, array $data = []): bool
    {
        $html = View::render('emails/' . $view, $data, null);
        $from = Env::get('MAIL_FROM_ADDRESS', 'hello@edgeplay.local');
        $fromName = Env::get('MAIL_FROM_NAME', 'Orion Bets');
        $mailer = Env::get('MAIL_MAILER', 'log');

        Logger::info('Mail queued', [
            'to' => $to,
            'subject' => $subject,
            'mailer' => $mailer,
        ]);

        if ($mailer === 'log' || $mailer === '' || $mailer === null) {
            $dir = STORAGE_PATH . '/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $payload = "To: {$to}\nSubject: {$subject}\nFrom: {$fromName} <{$from}>\n\n{$html}\n\n";
            file_put_contents($dir . '/mail-' . date('Y-m-d') . '.log', $payload, FILE_APPEND | LOCK_EX);
            return true;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . sprintf('%s <%s>', $fromName, $from),
        ];

        return mail($to, $subject, $html, implode("\r\n", $headers));
    }
}
