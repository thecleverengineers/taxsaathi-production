<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Admin partner-wise order directory.
 *
 * Orders are not copied into another table. This controller reads the normal
 * orders table and groups only rows where orders.partner_id is populated.
 */
class AdminPartnerWiseOrderController extends Controller
{
    private function db(): PDO
    {
        if (function_exists('app')) {
            try {
                $db = app('db');

                if ($db instanceof PDO) {
                    return $db;
                }

                foreach (['pdo', 'getPdo', 'connection', 'getConnection'] as $method) {
                    if (is_object($db) && method_exists($db, $method)) {
                        $pdo = $db->{$method}();

                        if ($pdo instanceof PDO) {
                            return $pdo;
                        }
                    }
                }

                if (is_object($db) && isset($db->pdo) && $db->pdo instanceof PDO) {
                    return $db->pdo;
                }
            } catch (Throwable $e) {
                // Continue to the global PDO fallback.
            }
        }

        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }

        throw new RuntimeException('PDO connection not found.');
    }

    private function url(string $path): string
    {
        return function_exists('base_url') ? base_url($path) : '/' . ltrim($path, '/');
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableColumns(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $stmt = $this->db()->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['Field'] ?? ''),
            $rows
        )));
    }

    private function canManagePartnerOrders(): bool
    {
        if (function_exists('can')) {
            try {
                return (bool) can('partner_orders.manage');
            } catch (Throwable $e) {
                return false;
            }
        }

        /* The application may already protect the admin route by middleware. */
        return true;
    }

    private function guard(): bool
    {
        if ($this->canManagePartnerOrders()) {
            return true;
        }

        http_response_code(403);
        echo 'Forbidden';
        return false;
    }

    private function partnerNameExpression(array $userColumns): string
    {
        $parts = [];

        foreach (['name', 'full_name', 'email'] as $column) {
            if (in_array($column, $userColumns, true)) {
                $parts[] = "NULLIF(u.`{$column}`, '')";
            }
        }

        $parts[] = "CONCAT('Partner #', o.partner_id)";

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function partnerGroupColumns(array $userColumns): string
    {
        $columns = ['o.partner_id'];

        foreach (['name', 'full_name', 'email'] as $column) {
            if (in_array($column, $userColumns, true)) {
                $columns[] = "u.`{$column}`";
            }
        }

        return implode(', ', $columns);
    }

    public function index(): void
    {
        if (!$this->guard()) {
            return;
        }

        $userColumns = $this->tableColumns('users');
        $partnerName = $this->partnerNameExpression($userColumns);
        $groupColumns = $this->partnerGroupColumns($userColumns);
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = [
            'o.partner_id IS NOT NULL',
            'o.partner_id > 0',
        ];
        $params = [];

        if ($search !== '') {
            $searchParts = ['CAST(o.partner_id AS CHAR) LIKE :search'];

            foreach (['name', 'full_name', 'email', 'mobile', 'phone'] as $column) {
                if (in_array($column, $userColumns, true)) {
                    $searchParts[] = "u.`{$column}` LIKE :search";
                }
            }

            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $params[':search'] = '%' . $search . '%';
        }

        $stmt = $this->db()->prepare(
            'SELECT
                o.partner_id,
                ' . $partnerName . ' AS partner_name,
                COUNT(*) AS total_orders,
                SUM(CASE WHEN o.status = \'submitted\' THEN 1 ELSE 0 END) AS submitted_orders,
                SUM(CASE WHEN o.status IN (\'in_progress\', \'work_in_progress\') THEN 1 ELSE 0 END) AS active_orders,
                SUM(CASE WHEN o.status IN (\'completed\', \'delivered\') THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN o.payment_status IN (\'pending\', \'pending_review\') THEN 1 ELSE 0 END) AS payment_pending_orders,
                MAX(o.created_at) AS last_order_at
             FROM orders o
             LEFT JOIN users u ON u.id = o.partner_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY ' . $groupColumns . '
             ORDER BY last_order_at DESC, partner_name ASC'
        );
        $stmt->execute($params);

        $this->view('admin/partner-wise-orders', [
            'title' => 'Partner-wise Orders – Tax Saathi',
            'partners' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'search' => $search,
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        if (!$this->guard()) {
            return;
        }

        $partnerId = (int) ($_GET['partner_id'] ?? 0);

        if ($partnerId <= 0) {
            http_response_code(422);
            echo 'Invalid partner.';
            return;
        }

        $userColumns = $this->tableColumns('users');
        $partnerName = $this->partnerNameExpression($userColumns);
        $customerJoin = $this->tableExists('customer_details')
            ? 'LEFT JOIN customer_details cd ON cd.order_id = o.id'
            : '';
        $customerColumns = $this->tableExists('customer_details')
            ? ', cd.name_as_per_pan AS customer_name,
                  cd.pan_number,
                  cd.mobile AS customer_mobile,
                  cd.email AS customer_email'
            : ', NULL AS customer_name,
                  NULL AS pan_number,
                  NULL AS customer_mobile,
                  NULL AS customer_email';

        $partnerStmt = $this->db()->prepare(
            'SELECT o.partner_id, ' . $partnerName . ' AS partner_name,
                    COUNT(*) AS total_orders,
                    SUM(CASE WHEN o.status IN (\'completed\', \'delivered\') THEN 1 ELSE 0 END) AS completed_orders,
                    SUM(CASE WHEN o.payment_status IN (\'pending\', \'pending_review\') THEN 1 ELSE 0 END) AS payment_pending_orders
             FROM orders o
             LEFT JOIN users u ON u.id = o.partner_id
             WHERE o.partner_id = :partner_id
             GROUP BY ' . $this->partnerGroupColumns($userColumns)
        );
        $partnerStmt->execute([':partner_id' => $partnerId]);
        $partner = $partnerStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($partner)) {
            http_response_code(404);
            echo 'Partner orders not found.';
            return;
        }

        $ordersStmt = $this->db()->prepare(
            'SELECT
                o.id,
                o.order_no,
                o.partner_id,
                ' . $partnerName . ' AS partner_name,
                s.title AS service_title,
                o.fee_amount,
                o.status,
                o.payment_status,
                o.payment_method,
                o.payment_reference,
                o.created_at,
                o.updated_at,
                o.completed_at' . $customerColumns . '
             FROM orders o
             LEFT JOIN users u ON u.id = o.partner_id
             LEFT JOIN services s ON s.id = o.service_id
             ' . $customerJoin . '
             WHERE o.partner_id = :partner_id
             ORDER BY o.created_at DESC, o.id DESC'
        );
        $ordersStmt->execute([':partner_id' => $partnerId]);

        $this->view('admin/partner-wise-orders-show', [
            'title' => 'Partner Order Details – Tax Saathi',
            'partner' => $partner,
            'orders' => $ordersStmt->fetchAll(PDO::FETCH_ASSOC),
        ], 'layouts/dashboard');
    }
}