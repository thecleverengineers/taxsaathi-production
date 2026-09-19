<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class AdvancedReportsController extends Controller
{
    private ?Database $database = null;

    private const ROLE_ADMIN = 1;
    private const ROLE_MANAGER = 2;
    private const ROLE_EXECUTIVE = 3;
    private const ROLE_PARTNER = 4;

    private const PAYMENT_STATUSES = [
        'pending' => 'Pending',
        'pending_review' => 'Pending Review',
        'verified' => 'Verified',
        'paid' => 'Paid',
        'partial' => 'Partial',
        'unpaid' => 'Unpaid',
        'failed' => 'Failed',
    ];

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
            foreach (['database', 'db'] as $key) {
                try {
                    $raw = config($key);
                    if (is_array($raw) && $raw !== []) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (Throwable $e) {
                    $config = [];
                }
            }
        }

        foreach ([
            dirname(__DIR__, 2) . '/config/database.php',
            dirname(__DIR__, 2) . '/config/config.php',
            dirname(__DIR__) . '/config/database.php',
            dirname(__DIR__) . '/config/config.php',
        ] as $file) {
            if ($config !== [] || !is_file($file)) {
                continue;
            }

            try {
                $raw = require $file;
                if (!is_array($raw)) {
                    continue;
                }

                if (isset($raw['database']) && is_array($raw['database'])) {
                    $config = $this->normalizeDatabaseConfig($raw['database']);
                    break;
                }

                if (isset($raw['db']) && is_array($raw['db'])) {
                    $config = $this->normalizeDatabaseConfig($raw['db']);
                    break;
                }

                if (isset($raw['connections']) || isset($raw['driver']) || isset($raw['host'])) {
                    $config = $this->normalizeDatabaseConfig($raw);
                    break;
                }
            } catch (Throwable $e) {
                $config = [];
            }
        }

        if ($config === []) {
            $env = static function (string $key, mixed $default = null): mixed {
                if (function_exists('env')) {
                    return env($key, $default);
                }

                $value = getenv($key);
                return $value !== false ? $value : $default;
            };

            $config = [
                'driver' => (string) $env('DB_DRIVER', 'mysql'),
                'host' => (string) $env('DB_HOST', '127.0.0.1'),
                'port' => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', $env('DB_NAME', 'taxsathi2')),
                'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
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
            'driver' => (string) ($config['driver'] ?? $config['type'] ?? 'mysql'),
            'host' => (string) ($config['host'] ?? $config['hostname'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? $config['dbname'] ?? $config['name'] ?? ''),
            'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? $config['user'] ?? ''),
            'password' => (string) ($config['password'] ?? $config['pass'] ?? ''),
        ];
    }

    private function currentUser(): array
    {
        if (function_exists('auth_user')) {
            $user = auth_user();
            if (is_array($user)) {
                return $user;
            }
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        return is_array($_SESSION['auth_user'] ?? null)
            ? $_SESSION['auth_user']
            : (is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : []);
    }

    private function currentUserId(): int
    {
        $user = $this->currentUser();

        return (int) (
            $user['id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? $_SESSION['user_id']
            ?? 0
        );
    }

    /**
     * STRICT RBAC SOURCE: user_roles table only.
     * This method never reads users.role_id or session role_id.
     */
    private function currentUserRoleIds(): array
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return [];
        }

        try {
            $rows = $this->db()->fetchAll(
                'SELECT ur.role_id
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                 ORDER BY ur.role_id ASC',
                ['user_id' => $userId]
            ) ?: [];
        } catch (Throwable $e) {
            error_log('Advanced report role lookup failed: ' . $e->getMessage());
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            if ($roleId > 0) {
                $ids[$roleId] = $roleId;
            }
        }

        return array_values($ids);
    }

    private function hasRole(int $roleId): bool
    {
        return in_array($roleId, $this->currentUserRoleIds(), true);
    }

    private function hasAnyRole(array $roleIds): bool
    {
        $current = $this->currentUserRoleIds();

        foreach ($roleIds as $roleId) {
            if (in_array((int) $roleId, $current, true)) {
                return true;
            }
        }

        return false;
    }

    private function isAdminOrManager(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    private function isExecutive(): bool
    {
        return $this->hasRole(self::ROLE_EXECUTIVE);
    }

    private function isPartner(): bool
    {
        return $this->hasRole(self::ROLE_PARTNER);
    }

    private function canFilterByPartner(): bool
    {
        return $this->isAdminOrManager() || $this->isExecutive();
    }

    private function canFilterByExecutive(): bool
    {
        return $this->isAdminOrManager();
    }

    /**
     * Executive choices are loaded strictly from user_roles.role_id = 3.
     * users.role_id is never read.
     */
    private function executiveFilterOptions(): array
    {
        try {
            return $this->db()->fetchAll(
                'SELECT DISTINCT
                    u.id,
                    COALESCE(NULLIF(u.name, ""), CONCAT("Executive #", u.id)) AS name,
                    COALESCE(u.email, "") AS email,
                    COALESCE(u.phone, "") AS phone
                 FROM users u
                 INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                   AND ur.role_id = :executive_role_id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE COALESCE(u.is_active, 1) = 1
                 ORDER BY name ASC, u.id ASC',
                ['executive_role_id' => self::ROLE_EXECUTIVE]
            ) ?: [];
        } catch (Throwable $e) {
            error_log('Advanced report executive filter lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    private function isExecutiveUserId(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT ur.user_id
                 FROM user_roles ur
                 WHERE ur.user_id = :user_id
                   AND ur.role_id = :executive_role_id
                 LIMIT 1',
                [
                    'user_id' => $userId,
                    'executive_role_id' => self::ROLE_EXECUTIVE,
                ]
            );

            return is_array($row) && $row !== [];
        } catch (Throwable $e) {
            error_log('Advanced report executive validation failed: ' . $e->getMessage());
            return false;
        }
    }

    private function selectedExecutiveLabel(int $executiveId): string
    {
        if ($executiveId === -1) {
            return 'Unassigned regular orders';
        }

        if ($executiveId <= 0) {
            return 'All accessible executives';
        }

        try {
            $row = $this->db()->fetch(
                'SELECT COALESCE(NULLIF(u.name, ""), CONCAT("Executive #", u.id)) AS name
                 FROM users u
                 INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                   AND ur.role_id = :executive_role_id
                 WHERE u.id = :executive_id
                 LIMIT 1',
                [
                    'executive_role_id' => self::ROLE_EXECUTIVE,
                    'executive_id' => $executiveId,
                ]
            );

            if (is_array($row) && trim((string) ($row['name'] ?? '')) !== '') {
                return trim((string) $row['name']);
            }
        } catch (Throwable $e) {
            error_log('Advanced report selected executive lookup failed: ' . $e->getMessage());
        }

        return 'Executive #' . $executiveId;
    }

    /**
     * Partner choices are loaded strictly from user_roles.role_id = 4.
     * users.role_id is never read.
     */
    private function partnerFilterOptions(): array
    {
        try {
            return $this->db()->fetchAll(
                'SELECT DISTINCT
                    u.id,
                    COALESCE(NULLIF(u.name, ""), CONCAT("Partner #", u.id)) AS name,
                    COALESCE(u.email, "") AS email,
                    COALESCE(u.phone, "") AS phone
                 FROM users u
                 INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                   AND ur.role_id = :partner_role_id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE COALESCE(u.is_active, 1) = 1
                 ORDER BY name ASC, u.id ASC',
                ['partner_role_id' => self::ROLE_PARTNER]
            ) ?: [];
        } catch (Throwable $e) {
            error_log('Advanced report partner filter lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    private function selectedPartnerLabel(int $partnerId): string
    {
        if ($partnerId <= 0) {
            return 'All accessible partners';
        }

        try {
            $row = $this->db()->fetch(
                'SELECT COALESCE(NULLIF(u.name, ""), CONCAT("Partner #", u.id)) AS name
                 FROM users u
                 INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                   AND ur.role_id = :partner_role_id
                 WHERE u.id = :partner_id
                 LIMIT 1',
                [
                    'partner_role_id' => self::ROLE_PARTNER,
                    'partner_id' => $partnerId,
                ]
            );

            if (is_array($row) && trim((string) ($row['name'] ?? '')) !== '') {
                return trim((string) $row['name']);
            }
        } catch (Throwable $e) {
            error_log('Advanced report selected partner lookup failed: ' . $e->getMessage());
        }

        return 'Partner #' . $partnerId;
    }

    private function requireReportsAccess(): void
    {
        if (function_exists('require_auth')) {
            require_auth();
        }

        if ($this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_EXECUTIVE, self::ROLE_PARTNER])) {
            return;
        }

        if (function_exists('flash')) {
            flash('error', 'Reports access required.');
        }

        if (function_exists('redirect')) {
            redirect('dashboard');
        }

        http_response_code(403);
        echo 'Reports access required.';
        exit;
    }

    private function scopeLabel(): string
    {
        if ($this->isAdminOrManager()) {
            return 'All order records';
        }

        $parts = [];

        if ($this->isExecutive()) {
            $parts[] = 'Assigned regular orders';
        }

        if ($this->isPartner()) {
            $parts[] = 'Own partner orders';
        }

        return $parts !== [] ? implode(' + ', $parts) : 'No report scope';
    }

    private function cleanDate(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        return $value;
    }

    private function requestArray(string $key): array
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? [];

        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $item = strtolower(trim((string) $item));
            if ($item !== '' && array_key_exists($item, self::PAYMENT_STATUSES)) {
                $clean[$item] = $item;
            }
        }

        return array_values($clean);
    }

    private function filtersFromRequest(): array
    {
        $source = strtolower(trim((string) ($_GET['source'] ?? 'all')));
        if (!in_array($source, ['all', 'orders', 'partner_orders'], true)) {
            $source = 'all';
        }

        $partnerId = max(0, (int) ($_GET['partner_id'] ?? 0));
        $executiveId = (int) ($_GET['executive_id'] ?? 0);

        // A partner-only account is permanently scoped to its own user id.
        // Admin/Manager and Executive may select a partner to narrow accessible rows.
        if ($this->isPartner() && !$this->isAdminOrManager() && !$this->isExecutive()) {
            $partnerId = $this->currentUserId();
        }

        // Admin/Manager may filter by any valid Executive or choose unassigned (-1).
        // Executive-only accounts are permanently locked to their own assigned records.
        // Partner-only accounts cannot apply an Executive filter.
        if ($this->isExecutive() && !$this->isAdminOrManager()) {
            $executiveId = $this->currentUserId();
        } elseif (!$this->isAdminOrManager()) {
            $executiveId = 0;
        } elseif ($executiveId > 0 && !$this->isExecutiveUserId($executiveId)) {
            $executiveId = 0;
        } elseif ($executiveId < -1) {
            $executiveId = 0;
        }

        return [
            'date_from' => $this->cleanDate((string) ($_GET['date_from'] ?? '')),
            'date_to' => $this->cleanDate((string) ($_GET['date_to'] ?? '')),
            'payment_statuses' => $this->requestArray('payment_status'),
            'source' => $source,
            'partner_id' => $partnerId,
            'executive_id' => $executiveId,
        ];
    }

    private function appendDateAndPaymentFilters(string &$sql, array &$params, string $alias, array $filters, string $prefix): void
    {
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $statuses = is_array($filters['payment_statuses'] ?? null) ? $filters['payment_statuses'] : [];

        if ($dateFrom !== '') {
            $sql .= ' AND ' . $alias . '.created_at >= :' . $prefix . '_date_from';
            $params[$prefix . '_date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '') {
            $sql .= ' AND ' . $alias . '.created_at <= :' . $prefix . '_date_to';
            $params[$prefix . '_date_to'] = $dateTo . ' 23:59:59';
        }

        if ($statuses !== []) {
            $placeholders = [];
            foreach (array_values($statuses) as $index => $status) {
                $key = $prefix . '_payment_status_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $status;
            }

            $sql .= ' AND LOWER(COALESCE(' . $alias . '.payment_status, "pending")) IN (' . implode(', ', $placeholders) . ')';
        }
    }

    private function regularOrderRows(array $filters): array
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return [];
        }

        if (!$this->isAdminOrManager() && !$this->isExecutive() && !$this->isPartner()) {
            return [];
        }

        if (($filters['source'] ?? 'all') === 'partner_orders') {
            return [];
        }

        $sql = '
            SELECT
                "Regular Order" AS report_source,
                o.id,
                o.order_no,
                o.created_at,
                o.updated_at,
                o.financial_year,
                COALESCE(s.title, "-") AS service_title,
                COALESCE(NULLIF(c.company_name, ""), NULLIF(c.name, ""), NULLIF(cu.name, ""), "-") AS client_name,
                COALESCE(NULLIF(c.email, ""), NULLIF(cu.email, ""), "") AS client_email,
                COALESCE(NULLIF(c.phone, ""), NULLIF(cu.phone, ""), "") AS client_phone,
                COALESCE(o.fee_amount, 0) AS gross_amount,
                0 AS discount_amount,
                COALESCE(o.fee_amount, 0) AS payable_amount,
                COALESCE(o.payment_method, "") AS payment_method,
                COALESCE(o.payment_status, "pending") AS payment_status,
                COALESCE(o.status, "pending_review") AS order_status,
                "" AS filing_status,
                COALESCE(o.payment_reference, "") AS payment_reference,
                COALESCE(pu.name, "") AS partner_name,
                COALESCE(au.name, "") AS assigned_user_name,
                COALESCE(o.notes, "") AS notes,
                COALESCE(o.admin_notes, "") AS admin_notes
            FROM orders o
            LEFT JOIN services s ON s.id = o.service_id
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN users cu ON cu.id = o.client_id
            LEFT JOIN users pu ON pu.id = o.partner_id
            LEFT JOIN users au ON au.id = o.assigned_user_id
            WHERE 1 = 1
        ';

        $params = [];
        $this->appendDateAndPaymentFilters($sql, $params, 'o', $filters, 'regular');

        $selectedPartnerId = max(0, (int) ($filters['partner_id'] ?? 0));
        if ($selectedPartnerId > 0) {
            $sql .= ' AND o.partner_id = :regular_filter_partner_id';
            $params['regular_filter_partner_id'] = $selectedPartnerId;
        }

        $selectedExecutiveId = (int) ($filters['executive_id'] ?? 0);
        if ($selectedExecutiveId === -1) {
            $sql .= ' AND COALESCE(o.assigned_user_id, 0) = 0';
        } elseif ($selectedExecutiveId > 0) {
            $sql .= ' AND o.assigned_user_id = :regular_filter_executive_id';
            $params['regular_filter_executive_id'] = $selectedExecutiveId;
        }

        if (!$this->isAdminOrManager()) {
            $scopeParts = [];

            if ($this->isExecutive()) {
                $scopeParts[] = 'o.assigned_user_id = :executive_user_id';
                $params['executive_user_id'] = $userId;
            }

            if ($this->isPartner()) {
                $scopeParts[] = 'o.partner_id = :partner_user_id';
                $params['partner_user_id'] = $userId;
            }

            if ($scopeParts === []) {
                return [];
            }

            $sql .= ' AND (' . implode(' OR ', $scopeParts) . ')';
        }

        $sql .= ' ORDER BY o.created_at DESC, o.id DESC';

        try {
            return $this->db()->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            error_log('Regular order report query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function partnerOrderRows(array $filters): array
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return [];
        }

        if (!$this->isAdminOrManager() && !$this->isPartner()) {
            return [];
        }

        if (($filters['source'] ?? 'all') === 'orders') {
            return [];
        }

        // partner_orders has no assigned_user_id. When an Executive filter is active,
        // only regular orders can match that filter.
        if ((int) ($filters['executive_id'] ?? 0) !== 0) {
            return [];
        }

        $sql = '
            SELECT
                "Partner Order" AS report_source,
                po.id,
                po.order_no,
                po.created_at,
                po.updated_at,
                po.financial_year,
                COALESCE(s.title, "-") AS service_title,
                COALESCE(u.name, "-") AS client_name,
                COALESCE(u.email, "") AS client_email,
                COALESCE(u.phone, "") AS client_phone,
                COALESCE(po.filing_fee, 0) AS gross_amount,
                COALESCE(po.coupon_discount_amount, 0) AS discount_amount,
                COALESCE(po.payable_amount, 0) AS payable_amount,
                COALESCE(po.payment_method, "upi") AS payment_method,
                COALESCE(po.payment_status, "waiting_for_payment") AS payment_status,
                COALESCE(po.order_status, "waiting_for_payment") AS order_status,
                COALESCE(po.filing_status, "pending_payment") AS filing_status,
                COALESCE(po.payment_reference, "") AS payment_reference,
                COALESCE(u.name, "") AS partner_name,
                "" AS assigned_user_name,
                COALESCE(po.notes, "") AS notes,
                COALESCE(po.admin_notes, "") AS admin_notes
            FROM partner_orders po
            LEFT JOIN services s ON s.id = po.service_id
            LEFT JOIN users u ON u.id = po.partner_id
            WHERE 1 = 1
        ';

        $params = [];
        $this->appendDateAndPaymentFilters($sql, $params, 'po', $filters, 'partner');

        $selectedPartnerId = max(0, (int) ($filters['partner_id'] ?? 0));

        if ($this->isAdminOrManager()) {
            if ($selectedPartnerId > 0) {
                $sql .= ' AND po.partner_id = :partner_filter_partner_id';
                $params['partner_filter_partner_id'] = $selectedPartnerId;
            }
        } else {
            // Partner records are always restricted to the logged-in partner.
            if ($selectedPartnerId > 0 && $selectedPartnerId !== $userId) {
                return [];
            }

            $sql .= ' AND po.partner_id = :partner_user_id';
            $params['partner_user_id'] = $userId;
        }

        $sql .= ' ORDER BY po.created_at DESC, po.id DESC';

        try {
            return $this->db()->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            error_log('Partner order report query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function reportRows(array $filters): array
    {
        $rows = array_merge(
            $this->regularOrderRows($filters),
            $this->partnerOrderRows($filters)
        );

        usort($rows, static function (array $a, array $b): int {
            $aTime = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

            if ($aTime === $bTime) {
                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }

            return $bTime <=> $aTime;
        });

        return $rows;
    }

    private function summary(array $rows): array
    {
        $summary = [
            'total_orders' => count($rows),
            'gross_amount' => 0.0,
            'discount_amount' => 0.0,
            'payable_amount' => 0.0,
            'by_payment_status' => [],
            'by_source' => [],
        ];

        foreach (self::PAYMENT_STATUSES as $key => $label) {
            $summary['by_payment_status'][$key] = [
                'label' => $label,
                'count' => 0,
                'amount' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $gross = (float) ($row['gross_amount'] ?? 0);
            $discount = (float) ($row['discount_amount'] ?? 0);
            $payable = (float) ($row['payable_amount'] ?? 0);
            $status = strtolower(trim((string) ($row['payment_status'] ?? 'pending')));
            $source = (string) ($row['report_source'] ?? 'Orders');

            $summary['gross_amount'] += $gross;
            $summary['discount_amount'] += $discount;
            $summary['payable_amount'] += $payable;

            if (!isset($summary['by_payment_status'][$status])) {
                $summary['by_payment_status'][$status] = [
                    'label' => $this->statusLabel($status),
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }

            $summary['by_payment_status'][$status]['count']++;
            $summary['by_payment_status'][$status]['amount'] += $payable;

            if (!isset($summary['by_source'][$source])) {
                $summary['by_source'][$source] = [
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }

            $summary['by_source'][$source]['count']++;
            $summary['by_source'][$source]['amount'] += $payable;
        }

        return $summary;
    }

    private function statusLabel(string $status): string
    {
        $status = strtolower(trim($status));
        return self::PAYMENT_STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    public function index(): void
    {
        $this->requireReportsAccess();

        $filters = $this->filtersFromRequest();
        $rows = $this->reportRows($filters);
        $summary = $this->summary($rows);

        $this->view('reports/advanced', [
            'title' => 'Advanced Reports – Tax Saathi',
            'filters' => $filters,
            'rows' => array_slice($rows, 0, 150),
            'summary' => $summary,
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'scopeLabel' => $this->scopeLabel(),
            'isAdminOrManager' => $this->isAdminOrManager(),
            'isExecutive' => $this->isExecutive(),
            'isPartner' => $this->isPartner(),
            'currentUserId' => $this->currentUserId(),
            'partners' => $this->partnerFilterOptions(),
            'canFilterByPartner' => $this->canFilterByPartner(),
            'selectedPartnerLabel' => $this->selectedPartnerLabel((int) ($filters['partner_id'] ?? 0)),
            'executives' => $this->executiveFilterOptions(),
            'canFilterByExecutive' => $this->canFilterByExecutive(),
            'selectedExecutiveLabel' => $this->selectedExecutiveLabel((int) ($filters['executive_id'] ?? 0)),
        ], 'layouts/dashboard');
    }

    public function export(): void
    {
        $this->requireReportsAccess();

        $filters = $this->filtersFromRequest();
        $rows = $this->reportRows($filters);
        $summary = $this->summary($rows);

        $filename = 'advanced-order-report-' . date('Ymd-His') . '.xls';

        if (ob_get_level() > 0) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: public');

        echo $this->excelXml($rows, $summary, $filters);
        exit;
    }

    private function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function excelCell(mixed $value, string $style = 'Cell', string $type = 'String'): string
    {
        if ($type === 'Number') {
            $value = is_numeric($value) ? (string) $value : '0';
        }

        return '<Cell ss:StyleID="' . $this->xml($style) . '"><Data ss:Type="' . $this->xml($type) . '">' . $this->xml($value) . '</Data></Cell>';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function dateLabel(string $date): string
    {
        $date = trim($date);
        return $date !== '' ? $date : 'All';
    }

    private function excelXml(array $rows, array $summary, array $filters): string
    {
        $generatedBy = $this->currentUser()['name'] ?? ('User #' . $this->currentUserId());
        $dateFrom = $this->dateLabel((string) ($filters['date_from'] ?? ''));
        $dateTo = $this->dateLabel((string) ($filters['date_to'] ?? ''));
        $statuses = is_array($filters['payment_statuses'] ?? null) && $filters['payment_statuses'] !== []
            ? implode(', ', array_map(fn (string $s): string => $this->statusLabel($s), $filters['payment_statuses']))
            : 'All payment statuses';
        $partnerFilterLabel = $this->selectedPartnerLabel((int) ($filters['partner_id'] ?? 0));
        $executiveFilterLabel = $this->selectedExecutiveLabel((int) ($filters['executive_id'] ?? 0));

        $columns = [
            'Source', 'Order No', 'Created Date', 'Financial Year', 'Service', 'Client', 'Partner',
            'Email', 'Phone', 'Gross Amount', 'Discount', 'Payable Amount', 'Payment Method',
            'Payment Status', 'Order Status', 'Filing Status', 'Payment Reference', 'Assigned User', 'Notes'
        ];

        $xml = '<?xml version="1.0"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
        $xml .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>Tax Saathi</Author><Title>Advanced Order Report</Title><Created>' . gmdate('Y-m-d\TH:i:s\Z') . '</Created></DocumentProperties>';
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="18" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="Subtitle"><Font ss:Bold="1" ss:Size="11" ss:Color="#334155"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>';
        $xml .= '<Style ss:ID="Cell"><Font ss:Color="#0F172A"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '<Style ss:ID="Money"><NumberFormat ss:Format="₹#,##0.00"/><Font ss:Color="#0F172A"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '<Style ss:ID="Date"><NumberFormat ss:Format="dd-mmm-yyyy"/><Font ss:Color="#0F172A"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '<Style ss:ID="Kpi"><Font ss:Bold="1" ss:Size="13" ss:Color="#0F172A"/><Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>';
        $xml .= '<Style ss:ID="Paid"><Font ss:Bold="1" ss:Color="#047857"/><Interior ss:Color="#ECFDF5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="Pending"><Font ss:Bold="1" ss:Color="#B45309"/><Interior ss:Color="#FFFBEB" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="Failed"><Font ss:Bold="1" ss:Color="#BE123C"/><Interior ss:Color="#FFF1F2" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
        $xml .= '</Styles>';

        $xml .= '<Worksheet ss:Name="Summary"><Table>';
        $xml .= '<Column ss:Width="180"/><Column ss:Width="160"/><Column ss:Width="160"/><Column ss:Width="160"/>';
        $xml .= '<Row ss:Height="32"><Cell ss:MergeAcross="3" ss:StyleID="Title"><Data ss:Type="String">Tax Saathi Advanced Order Report</Data></Cell></Row>';
        $xml .= '<Row><Cell ss:StyleID="Subtitle"><Data ss:Type="String">Generated By</Data></Cell>' . $this->excelCell($generatedBy, 'Cell') . '<Cell ss:StyleID="Subtitle"><Data ss:Type="String">Generated At</Data></Cell>' . $this->excelCell(date('d M Y, h:i A'), 'Cell') . '</Row>';
        $xml .= '<Row><Cell ss:StyleID="Subtitle"><Data ss:Type="String">Scope</Data></Cell>' . $this->excelCell($this->scopeLabel(), 'Cell') . '<Cell ss:StyleID="Subtitle"><Data ss:Type="String">Payment Filter</Data></Cell>' . $this->excelCell($statuses, 'Cell') . '</Row>';
        $xml .= '<Row><Cell ss:StyleID="Subtitle"><Data ss:Type="String">Date From</Data></Cell>' . $this->excelCell($dateFrom, 'Cell') . '<Cell ss:StyleID="Subtitle"><Data ss:Type="String">Date To</Data></Cell>' . $this->excelCell($dateTo, 'Cell') . '</Row>';
        $xml .= '<Row><Cell ss:StyleID="Subtitle"><Data ss:Type="String">Partner Filter</Data></Cell>' . $this->excelCell($partnerFilterLabel, 'Cell') . '<Cell ss:StyleID="Subtitle"><Data ss:Type="String">Source</Data></Cell>' . $this->excelCell((string) ($filters['source'] ?? 'all'), 'Cell') . '</Row>';
        $xml .= '<Row><Cell ss:StyleID="Subtitle"><Data ss:Type="String">Assigned Executive Filter</Data></Cell>' . $this->excelCell($executiveFilterLabel, 'Cell') . '<Cell ss:StyleID="Subtitle"><Data ss:Type="String">Assignment Scope</Data></Cell>' . $this->excelCell((int) ($filters['executive_id'] ?? 0) === -1 ? 'Unassigned regular orders only' : 'Assigned executive filter applied to regular orders', 'Cell') . '</Row>';
        $xml .= '<Row></Row>';
        $xml .= '<Row><Cell ss:StyleID="Kpi"><Data ss:Type="String">Total Orders</Data></Cell>' . $this->excelCell((int) ($summary['total_orders'] ?? 0), 'Kpi', 'Number') . '<Cell ss:StyleID="Kpi"><Data ss:Type="String">Payable Amount</Data></Cell>' . $this->excelCell($this->money((float) ($summary['payable_amount'] ?? 0)), 'Money', 'Number') . '</Row>';
        $xml .= '<Row><Cell ss:StyleID="Kpi"><Data ss:Type="String">Gross Amount</Data></Cell>' . $this->excelCell($this->money((float) ($summary['gross_amount'] ?? 0)), 'Money', 'Number') . '<Cell ss:StyleID="Kpi"><Data ss:Type="String">Discount Amount</Data></Cell>' . $this->excelCell($this->money((float) ($summary['discount_amount'] ?? 0)), 'Money', 'Number') . '</Row>';
        $xml .= '<Row></Row>';
        $xml .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Payment Status</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Count</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Payable Amount</Data></Cell></Row>';

        foreach (($summary['by_payment_status'] ?? []) as $status => $data) {
            $xml .= '<Row>';
            $xml .= $this->excelCell((string) ($data['label'] ?? $this->statusLabel((string) $status)), $this->paymentStyle((string) $status));
            $xml .= $this->excelCell((int) ($data['count'] ?? 0), 'Cell', 'Number');
            $xml .= $this->excelCell($this->money((float) ($data['amount'] ?? 0)), 'Money', 'Number');
            $xml .= '</Row>';
        }

        $xml .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions></Worksheet>';

        $xml .= '<Worksheet ss:Name="Order Records"><Table>';
        foreach ([120, 130, 100, 100, 180, 180, 170, 180, 120, 110, 100, 120, 110, 120, 120, 120, 160, 150, 260] as $width) {
            $xml .= '<Column ss:Width="' . $width . '"/>';
        }
        $xml .= '<Row ss:Height="30"><Cell ss:MergeAcross="18" ss:StyleID="Title"><Data ss:Type="String">Order Records</Data></Cell></Row>';
        $xml .= '<Row>';
        foreach ($columns as $column) {
            $xml .= $this->excelCell($column, 'Header');
        }
        $xml .= '</Row>';

        foreach ($rows as $row) {
            $createdAt = trim((string) ($row['created_at'] ?? ''));
            $dateValue = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt) ?: time()) : '';
            $paymentStatus = strtolower(trim((string) ($row['payment_status'] ?? 'pending')));

            $xml .= '<Row>';
            $xml .= $this->excelCell($row['report_source'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['order_no'] ?? '', 'Cell');
            $xml .= $this->excelCell($dateValue, 'Date');
            $xml .= $this->excelCell($row['financial_year'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['service_title'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['client_name'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['partner_name'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['client_email'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['client_phone'] ?? '', 'Cell');
            $xml .= $this->excelCell($this->money((float) ($row['gross_amount'] ?? 0)), 'Money', 'Number');
            $xml .= $this->excelCell($this->money((float) ($row['discount_amount'] ?? 0)), 'Money', 'Number');
            $xml .= $this->excelCell($this->money((float) ($row['payable_amount'] ?? 0)), 'Money', 'Number');
            $xml .= $this->excelCell($row['payment_method'] ?? '', 'Cell');
            $xml .= $this->excelCell($this->statusLabel($paymentStatus), $this->paymentStyle($paymentStatus));
            $xml .= $this->excelCell($this->statusLabel((string) ($row['order_status'] ?? '')), 'Cell');
            $xml .= $this->excelCell($this->statusLabel((string) ($row['filing_status'] ?? '')), 'Cell');
            $xml .= $this->excelCell($row['payment_reference'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['assigned_user_name'] ?? '', 'Cell');
            $xml .= $this->excelCell($row['notes'] ?? '', 'Cell');
            $xml .= '</Row>';
        }

        $xml .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane><ActivePane>2</ActivePane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions></Worksheet>';
        $xml .= '</Workbook>';

        return $xml;
    }

    private function paymentStyle(string $status): string
    {
        $status = strtolower(trim($status));

        if (in_array($status, ['paid', 'verified'], true)) {
            return 'Paid';
        }

        if (in_array($status, ['failed', 'unpaid'], true)) {
            return 'Failed';
        }

        return 'Pending';
    }
}