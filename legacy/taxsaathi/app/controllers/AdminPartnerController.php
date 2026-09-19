<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

final class AdminPartnerController extends Controller
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
        /*
         * Your app/config/config.php stores DB settings under:
         * return [
         *   'db' => [
         *      'driver' => 'mysql',
         *      'host' => '127.0.0.1',
         *      'port' => 3306,
         *      'database' => 'taxsathi2',
         *      'username' => 'taxsathi2',
         *      'password' => 'taxsathi2',
         *      'charset' => 'utf8mb4',
         *   ],
         * ];
         *
         * The previous version was not reading the "db" key, so the password
         * became empty and MySQL returned: using password: NO.
         */

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
                if (can('partners.subscription.manage') || can('partners.manage') || can('orders.manage')) {
                    return;
                }
            } catch (\Throwable $e) {
            }
        }

        flash('error', 'Admin access required.');
        redirect('auth');
    }

    public function index(): void
    {
        $this->requireAdmin();

        $rows = $this->db()->fetchAll(
            'SELECT
                u.id,
                u.name,
                u.email,
                COALESCE(u.phone, "") AS phone,
                COALESCE(u.is_active, 1) AS is_active,
                u.created_at,
                COALESCE(ps.plan_name, "No Plan") AS plan_name,
                COALESCE(ps.status, "inactive") AS subscription_status,
                ps.starts_at,
                ps.ends_at,
                COALESCE(ps.orders_limit, 0) AS orders_limit,
                COALESCE(ps.clients_limit, 0) AS clients_limit,
                COALESCE(ps.amount, 0) AS amount,
                (
                    SELECT COUNT(*)
                    FROM orders o
                    WHERE o.partner_id = u.id
                    AND (ps.starts_at IS NULL OR o.created_at >= ps.starts_at)
                    AND (ps.ends_at IS NULL OR o.created_at <= ps.ends_at)
                ) AS orders_used,
                (
                    SELECT COUNT(*)
                    FROM clients c
                    WHERE c.partner_id = u.id
                ) AS clients_count,
                (
                    SELECT COALESCE(SUM(p.amount), 0)
                    FROM payments p
                    WHERE p.partner_id = u.id
                ) AS revenue_total
             FROM users u
             LEFT JOIN partner_subscriptions ps ON ps.id = (
                SELECT ps2.id
                FROM partner_subscriptions ps2
                WHERE ps2.partner_id = u.id
                ORDER BY ps2.id DESC
                LIMIT 1
             )
             INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = 4
             ORDER BY u.id DESC'
        ) ?: [];

        $this->view('admin/partners', [
            'title' => 'Subscribed Partners – Tax Saathi',
            'rows' => $rows,
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        $this->index();
    }

    public function storeSubscription(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $partnerId = (int) input('partner_id');

        $partner = $this->db()->fetch(
            'SELECT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = 4
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $partnerId]
        );

        if (!$partner) {
            flash('error', 'Partner not found.');
            redirect('admin/partners');
        }

        $status = trim((string) input('status', 'active'));
        if (!in_array($status, ['active', 'trial', 'inactive', 'expired', 'cancelled'], true)) {
            $status = 'active';
        }

        $startsAt = trim((string) input('starts_at', date('Y-m-d')));
        $endsAt = trim((string) input('ends_at', date('Y-m-d', strtotime('+30 days'))));

        $this->db()->execute(
            'INSERT INTO partner_subscriptions
            (
                partner_id,
                plan_name,
                status,
                starts_at,
                ends_at,
                orders_limit,
                clients_limit,
                amount,
                payment_method,
                payment_reference,
                created_at,
                updated_at
            )
            VALUES
            (
                :partner_id,
                :plan_name,
                :status,
                :starts_at,
                :ends_at,
                :orders_limit,
                :clients_limit,
                :amount,
                :payment_method,
                :payment_reference,
                :created_at,
                :updated_at
            )',
            [
                'partner_id' => $partnerId,
                'plan_name' => trim((string) input('plan_name', 'Partner Basic')),
                'status' => $status,
                'starts_at' => $startsAt !== '' ? $startsAt . ' 00:00:00' : null,
                'ends_at' => $endsAt !== '' ? $endsAt . ' 23:59:59' : null,
                'orders_limit' => max(0, (int) input('orders_limit', 25)),
                'clients_limit' => max(0, (int) input('clients_limit', 0)),
                'amount' => (float) input('amount', 0),
                'payment_method' => trim((string) input('payment_method', 'manual')),
                'payment_reference' => trim((string) input('payment_reference')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        flash('success', 'Partner subscription saved successfully.');
        redirect('admin/partners');
    }

    public function store(): void
    {
        $this->storeSubscription();
    }

    public function deactivateSubscription(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $partnerId = (int) input('partner_id');

        $this->db()->execute(
            'UPDATE partner_subscriptions
             SET status = "inactive",
                 updated_at = :updated_at
             WHERE partner_id = :partner_id
             ORDER BY id DESC
             LIMIT 1',
            [
                'partner_id' => $partnerId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        flash('success', 'Partner subscription deactivated.');
        redirect('admin/partners');
    }

    public function deactivate(): void
    {
        $this->deactivateSubscription();
    }
}
