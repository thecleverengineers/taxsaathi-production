<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

final class AdminPartnerManagementController extends Controller
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

    private function currentUser(): array
    {
        return function_exists('auth_user') ? (auth_user() ?: []) : ($_SESSION['user'] ?? $_SESSION['auth_user'] ?? []);
    }

    private function currentUserId(): int
    {
        $user = $this->currentUser();

        return (int) (
            $user['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? 0
        );
    }


    private function requireAdmin(): void
    {
        $user = $this->currentUser();
        $roleIds = array_map('intval', (array) ($user['role_ids'] ?? []));

        if (array_intersect([1, 2], $roleIds) !== []) {
            return;
        }

        if (function_exists('can')) {
            try {
                if (can('partners.manage') || can('orders.manage')) {
                    return;
                }
            } catch (\Throwable $e) {
            }
        }

        flash('error', 'Admin access required.');
        redirect('auth');
    }

    private function partner(int $id): ?array
    {
        $row = $this->db()->fetch(
            'SELECT u.id, u.name, u.email, u.phone, u.role_id,
                    COALESCE(u.is_active, 1) AS is_active, u.created_at, u.updated_at
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = 4
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) && $row !== [] ? $row : null;
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
                COUNT(po.id) AS total_orders,
                COALESCE(SUM(po.filing_fee), 0) AS gross_filing_fee,
                COALESCE(SUM(po.coupon_discount_amount), 0) AS total_discount,
                COALESCE(SUM(po.payable_amount), 0) AS total_payable,
                SUM(CASE WHEN po.payment_status = "pending_review" THEN 1 ELSE 0 END) AS pending_payments,
                SUM(CASE WHEN po.filing_status = "completed" THEN 1 ELSE 0 END) AS completed_filings
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = 4
             LEFT JOIN partner_orders po ON po.partner_id = u.id
             GROUP BY u.id, u.name, u.email, u.phone, u.is_active, u.created_at
             ORDER BY u.id DESC'
        ) ?: [];

        $this->view('admin/partners', [
            'title' => 'Manage Partners – Tax Saathi',
            'rows' => $rows,
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        $this->requireAdmin();

        $partnerId = (int) input('id');
        $partner = $this->partner($partnerId);

        if (!$partner) {
            flash('error', 'Partner not found.');
            redirect('admin/partners');
        }

        $stats = $this->db()->fetch(
            'SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(filing_fee), 0) AS gross_filing_fee,
                COALESCE(SUM(coupon_discount_amount), 0) AS total_discount,
                COALESCE(SUM(payable_amount), 0) AS total_payable,
                SUM(CASE WHEN payment_status = "waiting_for_payment" THEN 1 ELSE 0 END) AS waiting_for_payment,
                SUM(CASE WHEN payment_status = "pending_review" THEN 1 ELSE 0 END) AS pending_review,
                SUM(CASE WHEN filing_status = "completed" THEN 1 ELSE 0 END) AS completed_filings
             FROM partner_orders
             WHERE partner_id = :partner_id',
            ['partner_id' => $partnerId]
        ) ?: [];

        $orders = $this->db()->fetchAll(
            'SELECT po.*, s.title AS service_title
             FROM partner_orders po
             INNER JOIN services s ON s.id = po.service_id
             WHERE po.partner_id = :partner_id
             ORDER BY po.id DESC',
            ['partner_id' => $partnerId]
        ) ?: [];

        $redemptions = $this->db()->fetchAll(
            'SELECT r.*, po.order_no
             FROM partner_coupon_redemptions r
             LEFT JOIN partner_orders po ON po.id = r.partner_order_id
             WHERE r.partner_id = :partner_id
             ORDER BY r.id DESC',
            ['partner_id' => $partnerId]
        ) ?: [];

        $this->view('admin/partner-show', [
            'title' => 'Partner Profile – Tax Saathi',
            'partner' => $partner,
            'stats' => $stats,
            'orders' => $orders,
            'redemptions' => $redemptions,
        ], 'layouts/dashboard');
    }

    public function update(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $partnerId = (int) input('id');

        if (!$this->partner($partnerId)) {
            flash('error', 'Partner not found.');
            redirect('admin/partners');
        }

        $this->db()->execute(
            'UPDATE users
             SET name = :name,
                 email = :email,
                 phone = :phone,
                 is_active = :is_active,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'name' => trim((string) input('name')),
                'email' => trim((string) input('email')),
                'phone' => trim((string) input('phone')),
                'is_active' => (int) input('is_active', 1) === 1 ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $partnerId,
            ]
        );

        flash('success', 'Partner profile updated.');
        redirect('admin/partners/show?id=' . $partnerId);
    }

    public function makePartner(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $userId = (int) input('user_id');

        $user = $this->db()->fetch(
            'SELECT id FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        if (!$user) {
            flash('error', 'User not found.');
            redirect('admin/partners');
        }

        $this->db()->execute(
            'UPDATE users
             SET role_id = 4,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $userId,
            ]
        );

        $this->db()->execute(
            'INSERT INTO user_roles (user_id, role_id, created_at, updated_at)
             VALUES (:user_id, 4, NOW(), NOW())
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
            ['user_id' => $userId]
        );

        flash('success', 'User converted to partner.');
        redirect('admin/partners/show?id=' . $userId);
    }
}
