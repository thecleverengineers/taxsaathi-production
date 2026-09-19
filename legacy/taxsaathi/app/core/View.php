<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        $viewsPath = dirname(__DIR__) . '/views/';
        $viewFile  = $viewsPath . $view . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $viewFile);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout !== null && $layout !== '') {
            $layoutFile = $viewsPath . $layout . '.php';

            if (!is_file($layoutFile)) {
                throw new \RuntimeException('Layout not found: ' . $layoutFile);
            }

            require $layoutFile;
            return;
        }

        echo $content;
    }
}