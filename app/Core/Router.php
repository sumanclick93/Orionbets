<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use Closure;

final class Router
{
    private Application $app;
    private array $routes = [];
    private array $aliases = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function alias(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    public function get(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function any(string $path, array|string $handler, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $this->add($method, $path, $handler, $middleware);
        }
    }

    private function add(string $method, string $path, array|string $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => rtrim($path, '/') ?: '/',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            $this->runMiddleware($route['middleware'], $request);
            $this->invoke($route['handler'], $params);
            return;
        }

        throw new HttpException(404, 'The page you requested could not be found.');
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function runMiddleware(array $middleware, Request $request): void
    {
        foreach ($middleware as $item) {
            $params = [];
            $name = $item;

            if (is_string($item) && str_contains($item, ':')) {
                [$name, $raw] = explode(':', $item, 2);
                $params = explode(',', $raw);
            }

            $class = $this->aliases[$name] ?? $name;
            $instance = new $class();

            if ($instance instanceof MiddlewareInterface) {
                $instance->handle($request, $this->app, $params);
            } elseif (is_callable($instance)) {
                $instance($request, $this->app, $params);
            }
        }
    }

    private function invoke(array|string $handler, array $params): void
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
        } else {
            [$class, $method] = $handler;
        }

        if (!str_contains($class, '\\')) {
            $class = 'App\\Controllers\\' . $class;
        }

        $controller = new $class($this->app);

        $result = $controller->{$method}(...array_values($params));

        if (is_string($result)) {
            echo $result;
        }
    }
}
