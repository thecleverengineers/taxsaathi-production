<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/frontend'): void
    {
        view($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }
}
