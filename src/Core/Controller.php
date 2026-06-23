<?php

declare(strict_types=1);

namespace Fahri\LapakId\Core;

class Controller
{
    protected View $view;
    protected ?Database $database = null;

    public function __construct()
    {
        $this->view = new View();
    }

    protected function render(string $template, array $data = []): string
    {
        return $this->view->render($template, $data);
    }

    protected function db(): Database
    {
        if ($this->database === null) {
            $this->database = new Database();
        }

        return $this->database;
    }

    protected function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    protected function postArray(string $key): array
    {
        $value = $_POST[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Please login as admin to continue.');
            $this->redirect('/login');
        }
    }
}
