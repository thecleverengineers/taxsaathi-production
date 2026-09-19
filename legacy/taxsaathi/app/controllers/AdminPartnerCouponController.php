<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

final class AdminPartnerCouponController extends Controller
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
                if (can('partners.coupons.manage') || can('partners.manage') || can('orders.manage')) {
                    return;
                }
            } catch (\Throwable $e) {
            }
        }

        flash('error', 'Admin access required.');
        redirect('auth');
    }

    private function services(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title FROM services WHERE COALESCE(is_active, 1) = 1 ORDER BY title ASC'
        ) ?: [];
    }

    public function index(): void
    {
        $this->requireAdmin();

        $rows = $this->db()->fetchAll(
            'SELECT
                c.*,
                s.title AS service_title,
                (SELECT COUNT(*) FROM partner_coupon_redemptions r WHERE r.coupon_id = c.id) AS used_count
             FROM partner_coupons c
             LEFT JOIN services s ON s.id = c.service_id
             ORDER BY c.id DESC'
        ) ?: [];

        $this->view('admin/partner-coupons', [
            'title' => 'Partner Coupons – Tax Saathi',
            'rows' => $rows,
            'services' => $this->services(),
        ], 'layouts/dashboard');
    }

    public function store(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $code = strtoupper(trim((string) input('code')));
        if ($code === '') {
            flash('error', 'Coupon code is required.');
            redirect('admin/partner-coupons');
        }

        $discountType = strtolower(trim((string) input('discount_type', 'percent')));
        if (!in_array($discountType, ['percent', 'flat'], true)) {
            $discountType = 'percent';
        }

        $discountValue = max(0, (float) input('discount_value', 0));
        if ($discountType === 'percent') {
            $discountValue = min(100, $discountValue);
        }

        $this->db()->execute(
            'INSERT INTO partner_coupons
            (
                code, title, description, service_id, discount_type, discount_value,
                max_discount_amount, min_order_amount, usage_limit, partner_usage_limit,
                starts_at, expires_at, is_active, created_at, updated_at
            )
            VALUES
            (
                :code, :title, :description, :service_id, :discount_type, :discount_value,
                :max_discount_amount, :min_order_amount, :usage_limit, :partner_usage_limit,
                :starts_at, :expires_at, :is_active, :created_at, :updated_at
            )',
            [
                'code' => $code,
                'title' => trim((string) input('title')),
                'description' => trim((string) input('description')),
                'service_id' => (int) input('service_id', 0) > 0 ? (int) input('service_id') : null,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'max_discount_amount' => max(0, (float) input('max_discount_amount', 0)),
                'min_order_amount' => max(0, (float) input('min_order_amount', 0)),
                'usage_limit' => max(0, (int) input('usage_limit', 0)),
                'partner_usage_limit' => max(0, (int) input('partner_usage_limit', 1)),
                'starts_at' => trim((string) input('starts_at')) ?: null,
                'expires_at' => trim((string) input('expires_at')) ?: null,
                'is_active' => (int) input('is_active', 1) === 1 ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        flash('success', 'Partner coupon created.');
        redirect('admin/partner-coupons');
    }

    public function toggle(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $coupon = $this->db()->fetch('SELECT id, is_active FROM partner_coupons WHERE id = :id LIMIT 1', ['id' => (int) input('id')]);

        if (!$coupon) {
            flash('error', 'Coupon not found.');
            redirect('admin/partner-coupons');
        }

        $this->db()->execute(
            'UPDATE partner_coupons SET is_active = :is_active, updated_at = :updated_at WHERE id = :id',
            [
                'is_active' => (int) ($coupon['is_active'] ?? 0) === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) $coupon['id'],
            ]
        );

        flash('success', 'Coupon status updated.');
        redirect('admin/partner-coupons');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $id = (int) input('id');

        $used = $this->db()->fetch('SELECT COUNT(*) AS total FROM partner_coupon_redemptions WHERE coupon_id = :coupon_id', ['coupon_id' => $id]);

        if ((int) ($used['total'] ?? 0) > 0) {
            $this->db()->execute('UPDATE partner_coupons SET is_active = 0, updated_at = :updated_at WHERE id = :id', ['updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);
            flash('warning', 'Coupon already used, so it was deactivated instead of deleted.');
            redirect('admin/partner-coupons');
        }

        $this->db()->execute('DELETE FROM partner_coupons WHERE id = :id', ['id' => $id]);

        flash('success', 'Coupon deleted.');
        redirect('admin/partner-coupons');
    }
}
