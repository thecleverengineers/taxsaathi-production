<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

final class AdminSubscriptionPlanController extends Controller
{

    private ?Database $database = null;

    private function db(): Database
    {
        if (!$this->database instanceof Database) {
            $this->database = new Database($this->databaseConfig());
        }

        return $this->database;
    }

    private function databaseConfig(): array
    {
        $config = [];

        if (function_exists('config')) {
            foreach (['db', 'database'] as $configKey) {
                try {
                    $raw = config($configKey);

                    if (is_array($raw) && $raw !== []) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (\Throwable $e) {
                    $config = [];
                }
            }
        }

        foreach ([
            dirname(__DIR__) . '/config/config.php',
            dirname(__DIR__) . '/config/database.php',
            dirname(__DIR__, 2) . '/app/config/config.php',
            dirname(__DIR__, 2) . '/app/config/database.php',
            dirname(__DIR__, 2) . '/config/config.php',
            dirname(__DIR__, 2) . '/config/database.php',
        ] as $configFile) {
            if ($config !== [] || !is_file($configFile)) {
                continue;
            }

            try {
                $raw = require $configFile;

                if (!is_array($raw)) {
                    continue;
                }

                if (isset($raw['db']) && is_array($raw['db'])) {
                    $config = $this->normalizeDatabaseConfig($raw['db']);
                    break;
                }

                if (isset($raw['database']) && is_array($raw['database'])) {
                    $config = $this->normalizeDatabaseConfig($raw['database']);
                    break;
                }

                if (isset($raw['connections']) || isset($raw['driver']) || isset($raw['host'])) {
                    $config = $this->normalizeDatabaseConfig($raw);
                    break;
                }
            } catch (\Throwable $e) {
                $config = [];
            }
        }

        if ($config === []) {
            $env = static function (string $key, mixed $default = null): mixed {
                if (function_exists('env')) {
                    return env($key, $default);
                }

                $value = function_exists('getenv') ? getenv($key) : false;

                return $value !== false ? $value : $default;
            };

            $config = [
                'driver'   => (string) $env('DB_DRIVER', 'mysql'),
                'host'     => (string) $env('DB_HOST', '127.0.0.1'),
                'port'     => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', $env('DB_NAME', 'taxsathi2')),
                'charset'  => (string) $env('DB_CHARSET', 'utf8mb4'),
                'username' => (string) $env('DB_USERNAME', $env('DB_USER', 'taxsathi2')),
                'password' => (string) $env('DB_PASSWORD', $env('DB_PASS', '')),
            ];
        }

        if (($config['database'] ?? '') === '') {
            throw new RuntimeException('Database configuration is missing the database name.');
        }

        return $config;
    }

    private function normalizeDatabaseConfig(array $config): array
    {
        if (isset($config['connections']) && is_array($config['connections'])) {
            $default = (string) ($config['default'] ?? array_key_first($config['connections']));
            $connection = $config['connections'][$default] ?? [];

            if (is_array($connection) && $connection !== []) {
                $config = $connection;
            }
        }

        return [
            'driver'   => (string) ($config['driver'] ?? $config['type'] ?? 'mysql'),
            'host'     => (string) ($config['host'] ?? $config['hostname'] ?? '127.0.0.1'),
            'port'     => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? $config['dbname'] ?? $config['name'] ?? ''),
            'charset'  => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? $config['user'] ?? ''),
            'password' => (string) ($config['password'] ?? $config['pass'] ?? ''),
        ];
    }

    private function requireAdmin(): void
    {
        $user = function_exists('auth_user') ? (auth_user() ?: []) : ($_SESSION['user'] ?? $_SESSION['auth_user'] ?? []);
        $roleIds = array_map('intval', (array) ($user['role_ids'] ?? []));

        if (array_intersect([1, 2], $roleIds) !== []) {
            return;
        }

        if (function_exists('can')) {
            try {
                if (can('partners.subscription.manage') || can('subscription_plans.manage') || can('partners.manage') || can('orders.manage')) {
                    return;
                }
            } catch (\Throwable $e) {
            }
        }

        flash('error', 'Admin access required.');
        redirect('auth');
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'plan-' . date('YmdHis');
    }


    public function index(): void
    {
        $this->requireAdmin();

        $rows = $this->db()->fetchAll(
            'SELECT *
             FROM partner_subscription_plans
             ORDER BY sort_order ASC, id ASC'
        ) ?: [];

        $this->view('admin/subscription-plans', [
            'title' => 'Subscription Plans – Tax Saathi',
            'rows' => $rows,
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        $this->index();
    }

    public function store(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'Plan name is required.');
            redirect('admin/subscription-plans');
        }

        $slug = trim((string) input('slug'));
        if ($slug === '') {
            $slug = $this->slugify($name);
        } else {
            $slug = $this->slugify($slug);
        }

        $features = trim((string) input('features'));
        $featuresArray = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $features) ?: [])));
        $featuresJson = json_encode($featuresArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db()->execute(
            'INSERT INTO partner_subscription_plans
            (
                name,
                slug,
                description,
                price,
                duration_days,
                orders_limit,
                clients_limit,
                staff_limit,
                features_json,
                is_active,
                sort_order,
                created_at,
                updated_at
            )
            VALUES
            (
                :name,
                :slug,
                :description,
                :price,
                :duration_days,
                :orders_limit,
                :clients_limit,
                :staff_limit,
                :features_json,
                :is_active,
                :sort_order,
                :created_at,
                :updated_at
            )',
            [
                'name' => $name,
                'slug' => $slug,
                'description' => trim((string) input('description')),
                'price' => max(0, (float) input('price', 0)),
                'duration_days' => max(1, (int) input('duration_days', 30)),
                'orders_limit' => max(0, (int) input('orders_limit', 25)),
                'clients_limit' => max(0, (int) input('clients_limit', 0)),
                'staff_limit' => max(0, (int) input('staff_limit', 0)),
                'features_json' => $featuresJson,
                'is_active' => (int) input('is_active', 1) === 1 ? 1 : 0,
                'sort_order' => (int) input('sort_order', 0),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        flash('success', 'Subscription plan created successfully.');
        redirect('admin/subscription-plans');
    }

    public function update(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $id = (int) input('id');
        $plan = $this->db()->fetch(
            'SELECT * FROM partner_subscription_plans WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        if (!$plan) {
            flash('error', 'Subscription plan not found.');
            redirect('admin/subscription-plans');
        }

        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'Plan name is required.');
            redirect('admin/subscription-plans');
        }

        $slug = trim((string) input('slug'));
        if ($slug === '') {
            $slug = $this->slugify($name);
        } else {
            $slug = $this->slugify($slug);
        }

        $features = trim((string) input('features'));
        $featuresArray = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $features) ?: [])));
        $featuresJson = json_encode($featuresArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db()->execute(
            'UPDATE partner_subscription_plans
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 price = :price,
                 duration_days = :duration_days,
                 orders_limit = :orders_limit,
                 clients_limit = :clients_limit,
                 staff_limit = :staff_limit,
                 features_json = :features_json,
                 is_active = :is_active,
                 sort_order = :sort_order,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'name' => $name,
                'slug' => $slug,
                'description' => trim((string) input('description')),
                'price' => max(0, (float) input('price', 0)),
                'duration_days' => max(1, (int) input('duration_days', 30)),
                'orders_limit' => max(0, (int) input('orders_limit', 25)),
                'clients_limit' => max(0, (int) input('clients_limit', 0)),
                'staff_limit' => max(0, (int) input('staff_limit', 0)),
                'features_json' => $featuresJson,
                'is_active' => (int) input('is_active', 1) === 1 ? 1 : 0,
                'sort_order' => (int) input('sort_order', 0),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        flash('success', 'Subscription plan updated.');
        redirect('admin/subscription-plans');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $id = (int) input('id');

        $used = $this->db()->fetch(
            'SELECT COUNT(*) AS total
             FROM partner_subscriptions
             WHERE plan_id = :plan_id',
            ['plan_id' => $id]
        );

        if ((int) ($used['total'] ?? 0) > 0) {
            $this->db()->execute(
                'UPDATE partner_subscription_plans
                 SET is_active = 0,
                     updated_at = :updated_at
                 WHERE id = :id',
                [
                    'id' => $id,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );

            flash('warning', 'Plan is already used by partner subscriptions, so it was deactivated instead of deleted.');
            redirect('admin/subscription-plans');
        }

        $this->db()->execute(
            'DELETE FROM partner_subscription_plans WHERE id = :id',
            ['id' => $id]
        );

        flash('success', 'Subscription plan deleted.');
        redirect('admin/subscription-plans');
    }

    public function toggle(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $id = (int) input('id');

        $plan = $this->db()->fetch(
            'SELECT id, is_active FROM partner_subscription_plans WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        if (!$plan) {
            flash('error', 'Subscription plan not found.');
            redirect('admin/subscription-plans');
        }

        $this->db()->execute(
            'UPDATE partner_subscription_plans
             SET is_active = :is_active,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'is_active' => (int) ($plan['is_active'] ?? 0) === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        flash('success', 'Plan status updated.');
        redirect('admin/subscription-plans');
    }
}
