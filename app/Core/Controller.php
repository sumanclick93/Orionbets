<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Application $app;
    protected Request $request;
    protected Response $response;
    protected Session $session;
    protected Auth $auth;
    protected Database $db;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->response = $app->response;
        $this->session = $app->session;
        $this->auth = $app->auth;
        $this->db = $app->db;
    }

    protected function view(string $view, array $data = [], ?string $layout = 'marketing'): string
    {
        return View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        $this->response->json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): never
    {
        $this->response->redirect($url, $status);
    }

    protected function back(): never
    {
        $this->response->back();
    }

    protected function flash(string $key, mixed $value): void
    {
        $this->session->flash($key, $value);
    }

    protected function oldInput(array $input): void
    {
        $this->session->flash('_old', $input);
    }

    protected function errors(array $errors): void
    {
        $this->session->flash('errors', $errors);
    }
}
