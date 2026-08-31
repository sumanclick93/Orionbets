<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function redirect(string $url, int $status = 302): never
    {
        if (!str_starts_with($url, 'http')) {
            $url = url($url);
        }

        header('Location: ' . $url, true, $status);
        exit;
    }

    public function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? url('/');
        header('Location: ' . $referer, true, 302);
        exit;
    }

    public function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
