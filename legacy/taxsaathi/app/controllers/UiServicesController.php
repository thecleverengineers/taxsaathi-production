<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Throwable;

final class UiServicesController
{
    public function index(): void
    {
        try {
            $services = $this->fetchServices();

            $featuredServices = array_values(array_filter(
                $services,
                static fn(array $service): bool => (int)($service['is_featured'] ?? 0) === 1
            ));

            $this->render('services/index', [
                'title'            => 'Services',
                'siteSettings'     => [],
                'services'         => $services,
                'featuredServices' => $featuredServices,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            $this->renderPlainError('Application error: ' . $e->getMessage());
        }
    }

    public function show(): void
    {
        try {
            $slug = trim((string)($_GET['slug'] ?? ''));

            if ($slug === '') {
                header('Location: /services');
                exit;
            }

            $service = $this->fetchServiceBySlug($slug);

            if ($service === null) {
                $this->abortNotFound('Service not found.');
                return;
            }

            $relatedServices = $this->fetchRelatedServices((int)($service['id'] ?? 0), 3);

            $this->render('services/show', [
                'title'           => (string)($service['title'] ?? 'Service Details'),
                'siteSettings'    => [],
                'service'         => $service,
                'relatedServices' => $relatedServices,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            $this->renderPlainError('Application error: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchServices(): array
    {
        $sql = "
            SELECT
                id,
                title,
                slug,
                excerpt,
                description,
                filing_fee,
                turnaround_days,
                icon,
                sort_order,
                is_featured,
                is_active,
                created_at,
                updated_at
            FROM services
            WHERE is_active = 1
            ORDER BY is_featured DESC, sort_order ASC, id ASC
        ";

        $stmt = $this->db()->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchServiceBySlug(string $slug): ?array
    {
        $sql = "
            SELECT
                id,
                title,
                slug,
                excerpt,
                description,
                filing_fee,
                turnaround_days,
                icon,
                sort_order,
                is_featured,
                is_active,
                created_at,
                updated_at
            FROM services
            WHERE slug = :slug
              AND is_active = 1
            LIMIT 1
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRelatedServices(int $excludeId, int $limit = 3): array
    {
        $limit = max(1, (int)$limit);

        $sql = "
            SELECT
                id,
                title,
                slug,
                excerpt,
                description,
                filing_fee,
                turnaround_days,
                icon,
                sort_order,
                is_featured,
                is_active,
                created_at,
                updated_at
            FROM services
            WHERE is_active = 1
              AND id <> :exclude_id
            ORDER BY is_featured DESC, sort_order ASC, id ASC
            LIMIT {$limit}
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':exclude_id' => $excludeId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1';
        $name = defined('DB_NAME') ? (string)DB_NAME : 'taxsathi2';
        $user = defined('DB_USER') ? (string)DB_USER : 'taxsathi2';
        $pass = defined('DB_PASS') ? (string)DB_PASS : 'taxsathi2';
        $port = defined('DB_PORT') ? (string)DB_PORT : '3306';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }

    private function render(string $view, array $data = []): void
    {
        $baseDir = dirname(__DIR__);

        $viewCandidates = [
            $baseDir . '/views/' . ltrim($view, '/') . '.php',
            $baseDir . '/Views/' . ltrim($view, '/') . '.php',
        ];

        $layoutCandidates = [
            $baseDir . '/views/layouts/public.php',
            $baseDir . '/Views/layouts/public.php',
            $baseDir . '/views/layouts/public.php',
            $baseDir . '/Views/layouts/public.php',
        ];

        $viewFile = null;
        foreach ($viewCandidates as $candidate) {
            if (is_file($candidate)) {
                $viewFile = $candidate;
                break;
            }
        }

        if ($viewFile === null) {
            http_response_code(500);
            $this->renderPlainError('View not found: ' . $view);
            return;
        }

        $layoutFile = null;
        foreach ($layoutCandidates as $candidate) {
            if (is_file($candidate)) {
                $layoutFile = $candidate;
                break;
            }
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string)ob_get_clean();

        if ($layoutFile !== null) {
            require $layoutFile;
            return;
        }

        echo $content;
    }

    private function abortNotFound(string $message = 'Not Found'): void
    {
        http_response_code(404);

        echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 - Not Found</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f7fcf9;color:#091413;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.box{max-width:640px;width:100%;background:#fff;border:1px solid rgba(40,90,72,.12);border-radius:24px;padding:32px;box-shadow:0 20px 60px rgba(9,20,19,.08);text-align:center}
h1{margin:0 0 12px;font-size:42px}
p{margin:0 0 18px;color:#4e625b;line-height:1.7}
a{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 20px;border-radius:999px;background:linear-gradient(135deg,#285A48,#091413);color:#fff;text-decoration:none;font-weight:700}
</style>
</head>
<body>
    <div class="box">
        <h1>404</h1>
        <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
        <a href="/services">Back to Services</a>
    </div>
</body>
</html>';
        exit;
    }

    private function renderPlainError(string $message): void
    {
        echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Error</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f7fcf9;color:#091413;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.box{max-width:760px;width:100%;background:#fff;border:1px solid rgba(40,90,72,.12);border-radius:24px;padding:32px;box-shadow:0 20px 60px rgba(9,20,19,.08)}
h1{margin:0 0 12px;font-size:32px}
p{margin:0;color:#4e625b;line-height:1.7;white-space:pre-wrap;word-break:break-word}
</style>
</head>
<body>
    <div class="box">
        <h1>Error</h1>
        <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
    </div>
</body>
</html>';
    }
}