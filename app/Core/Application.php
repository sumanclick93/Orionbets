<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Middleware\CsrfMiddleware;
use App\Setup\Schema;
use Throwable;

final class Application
{
    private static ?self $instance = null;

    public Router $router;
    public Request $request;
    public Response $response;
    public Database $db;
    public Session $session;
    public Auth $auth;

    private function __construct()
    {
        Config::load();

        $this->request = new Request();
        $this->response = new Response();
        $this->session = new Session();
        $this->db = Database::getInstance();
        $this->auth = new Auth($this->session, $this->db);
        $this->router = new Router($this);

        if (PHP_SAPI !== 'cli') {
            $this->session->start();
            Csrf::ensure($this->session);
        }
    }

    public static function boot(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            return self::boot();
        }

        return self::$instance;
    }

    public function run(): void
    {
        try {
            Schema::tryEnsure($this->db);
            try {
                \App\Services\EverflowService::make($this->db)->capture($this->request);
            } catch (Throwable) {
                // Attribution must never block the page.
            }
            $this->registerMiddlewareAliases();
            $this->loadRoutes();
            (new \App\Middleware\GeoBlockMiddleware())->handle($this->request, $this);
            $this->router->dispatch($this->request);
        } catch (HttpException $e) {
            $this->renderHttpError($e->getStatusCode(), $e->getMessage());
        } catch (Throwable $e) {
            Logger::error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $message = Env::bool('APP_DEBUG') ? $e->getMessage() : 'An unexpected error occurred.';
            $this->renderHttpError(500, $message);
        }
    }

    private function registerMiddlewareAliases(): void
    {
        $this->router->alias('csrf', CsrfMiddleware::class);
        $this->router->alias('auth', \App\Middleware\AuthMiddleware::class);
        $this->router->alias('guest', \App\Middleware\GuestMiddleware::class);
        $this->router->alias('admin', \App\Middleware\AdminMiddleware::class);
        $this->router->alias('role', \App\Middleware\RoleMiddleware::class);
        $this->router->alias('premium', \App\Middleware\PremiumMiddleware::class);
        $this->router->alias('throttle', \App\Middleware\RateLimitMiddleware::class);
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require BASE_PATH . '/routes/web.php';
        require BASE_PATH . '/routes/api.php';
    }

    private function renderHttpError(int $status, string $message): void
    {
        http_response_code($status);

        $view = match ($status) {
            403 => 'errors/403',
            404 => 'errors/404',
            default => 'errors/500',
        };

        echo View::render($view, [
            'status' => $status,
            'message' => $message,
            'title' => $status === 404 ? 'Page not found' : ($status === 403 ? 'Access denied' : 'Server error'),
        ], 'error');
    }
}
