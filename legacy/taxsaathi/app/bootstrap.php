<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kolkata');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['security']['session_name'] ?? 'tax_saathi_session');
    session_start();
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass);
    $segments = explode('/', $relativePath);
    if (isset($segments[0])) {
        $segments[0] = strtolower($segments[0]);
    }

    $file = $baseDir . implode('/', $segments) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/core/helpers.php';

use App\Core\Container;
use App\Core\Database;
use App\Core\Router;

$container = new Container();
$container->set('config', $config);
$container->set('db', static fn() => new Database($config['db']));
$container->set('router', static fn() => new Router());

$GLOBALS['app_container'] = $container;

ensure_upload_directory();
