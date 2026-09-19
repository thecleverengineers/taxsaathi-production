<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $uri, array $action): void
    {
        $this->add('GET', $uri, $action);
    }

    public function post(string $uri, array $action): void
    {
        $this->add('POST', $uri, $action);
    }

    public function add(string $method, string $uri, array $action): void
    {
        $this->routes[strtoupper($method)][rtrim($uri, '/') ?: '/'] = $action;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        $action = $this->routes[strtoupper($method)][$path] ?? null;

        if (!$action || count($action) !== 2) {
            http_response_code(404);
            view('public/404', ['title' => 'Page not found'], 'layouts/blank');
            return;
        }

        [$controllerClass, $controllerMethod] = $action;
        $controller = new $controllerClass();
        $controller->{$controllerMethod}();
    }
}


$queryRoute = trim((string) ($_GET['route'] ?? ''));

if ($queryRoute !== '') {
    $decodedRoute = rawurldecode($queryRoute);
    $decodedRoute = str_replace('\\', '/', $decodedRoute);
    $decodedRoute = preg_replace('~/+~', '/', $decodedRoute) ?: $decodedRoute;
    $decodedRoute = trim($decodedRoute, '/');

    // Security: only allow normal route characters.
    if ($decodedRoute !== '' && preg_match('~^[A-Za-z0-9/_\-.]+$~', $decodedRoute) === 1) {
        $path = '/' . $decodedRoute;
    }
}