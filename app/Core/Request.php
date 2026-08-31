<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $get;
    private array $post;
    private array $server;
    private array $cookies;
    private array $files;
    private ?array $json = null;
    private ?string $rawBody = null;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;

        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($this->post['_method'])) {
            $this->server['REQUEST_METHOD'] = strtoupper((string) $this->post['_method']);
        }
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $script = $this->server['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');

        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return '/' . ltrim($path, '/');
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($this->header('Accept', ''), 'application/json');
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path(), '/api');
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->get);
    }

    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->get);
    }

    public function filled(string $key): bool
    {
        $val = $this->input($key);
        return $val !== null && trim((string) $val) !== '';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }
        return $out;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$serverKey] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }

        return $this->rawBody;
    }

    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $decoded = json_decode($this->rawBody(), true);
        $this->json = is_array($decoded) ? $decoded : [];

        return $this->json;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        if (!isset($this->files[$key]) || !is_array($this->files[$key])) {
            return null;
        }

        return $this->files[$key];
    }

    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        if ($file === null) {
            return false;
        }

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (is_array($error)) {
            return !empty($error) && ($error[0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        }

        return $error === UPLOAD_ERR_OK && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name']);
    }

    public function allFiles(): array
    {
        return $this->files;
    }
}
