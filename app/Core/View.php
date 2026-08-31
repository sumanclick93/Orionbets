<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'marketing'): string
    {
        $viewFile = self::path($view);

        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        $data['authUser'] = auth()->user();
        $data['isPremium'] = auth()->isPremium();
        $data['isFreeMember'] = auth()->isFreeMember();
        $data['settings'] = $data['settings'] ?? settings();
        $data['currentPath'] = request()->path();

        extract($data, EXTR_SKIP);

        ob_start();
        include $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            return $content;
        }

        ob_start();
        include $layoutFile;
        return (string) ob_get_clean();
    }

    public static function component(string $componentName, array $data = []): string
    {
        $componentFile = VIEW_PATH . '/components/' . $componentName . '.php';
        if (!is_file($componentFile)) {
            return '';
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $componentFile;
        return (string) ob_get_clean();
    }

    public static function path(string $view): string
    {
        $view = str_replace('.', '/', $view);
        return VIEW_PATH . '/' . $view . '.php';
    }
}
