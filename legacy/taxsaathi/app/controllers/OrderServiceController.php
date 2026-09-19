<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use RuntimeException;
use Throwable;

class OrderServiceController extends Controller
{
    private const MAX_UPLOAD_BYTES = 10485760; // 10 MB

    private const ALLOWED_DOCUMENT_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx',
    ];

    private const ALLOWED_PAYMENT_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp',
    ];

    /**
     * Financial Year is intentionally stored as normal display text.
     * It is not treated as a MySQL YEAR/date value and is not whitelist-validated.
     */
    private const FINANCIAL_YEAR_TEXT_OPTIONS = [
        '1 Year (FY-2025-26)',
        '2 Years (FY-2024-25 & FY-2025-26)',
        '3 Years (FY-2023-24, FY-2024-25 & FY-2025-26)',
        '4 Years (FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)',
        '5 Years (FY-2021-22, FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)',
    ];

    private const PAYMENT_METHOD_LABELS = [
        'upi' => 'UPI',
        'bank_transfer' => 'Bank Transfer',
        'cash_on_delivery' => 'Cash on Delivery (Pay Later)',
    ];

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
                // Continue fallback below.
            }
        }

        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }

        throw new RuntimeException('PDO connection not found. Please update OrderServiceController::db().');
    }

    private function flashMessage(string $type, string $message): void
    {
        if (function_exists('flash')) {
            flash($type, $message);
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION['flash'][$type] = $message;
    }

    private function url(string $path): string
    {
        if (function_exists('base_url')) {
            return base_url($path);
        }

        return '/' . ltrim($path, '/');
    }

    private function paymentConfig(): array
    {
        $config = [];
        $configFile = dirname(__DIR__) . '/config/razorpay.php';

        if (is_file($configFile)) {
            try {
                $loaded = require $configFile;
                if (is_array($loaded)) {
                    $config = $loaded;
                }
            } catch (Throwable $e) {
                // Keep safe defaults when payment configuration is unavailable.
            }
        }

        $defaults = [
            'company_name' => 'Tax Saathi',
            'upi_id' => 'taxsaathi@upi',
            'upi_qr' => '',
            'bank_name' => '',
            'bank_ifsc' => '',
            'bank_account_number' => '',
        ];

        foreach ($config as $key => $value) {
            if (array_key_exists($key, $defaults) && trim((string) $value) !== '') {
                $defaults[$key] = trim((string) $value);
            }
        }

        return $defaults;
    }

    private function paymentAssetUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('~^(https?:)?//|^data:~i', $value)) {
            return $value;
        }

        return $this->url(ltrim($value, '/'));
    }

    private function go(string $url): void
    {
        if (function_exists('redirect')) {
            redirect($url);
            return;
        }

        header('Location: ' . $url);
        exit;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code($statusCode);
        header_remove('Location');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
        ");

        $stmt->execute([
            ':table' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");

        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function financialYearTextOptions(): array
    {
        return array_map(
            static fn (string $value): array => [
                'value' => $value,
                'label' => $value,
            ],
            self::FINANCIAL_YEAR_TEXT_OPTIONS
        );
    }

    private function normalizePaymentMethod(string $value): string
    {
        $value = strtolower(trim($value));

        // Read old records that used `cash` without changing their meaning.
        if ($value === 'cash') {
            $value = 'cash_on_delivery';
        }

        return isset(self::PAYMENT_METHOD_LABELS[$value]) ? $value : '';
    }

    private function paymentMethodLabel(string $value): string
    {
        $method = $this->normalizePaymentMethod($value);

        return self::PAYMENT_METHOD_LABELS[$method] ?? 'Payment';
    }

    /**
     * Keep orders.financial_year as a normal text column so long display labels
     * can be stored exactly as submitted.
     *
     * This runs before the order transaction because ALTER TABLE causes an
     * implicit commit in MySQL/MariaDB.
     */
    private function ensureFinancialYearTextColumn(): void
    {
        $pdo = $this->db();

        if ($pdo->inTransaction() || !$this->tableExists('orders') || !$this->columnExists('orders', 'financial_year')) {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'financial_year'
                LIMIT 1
            ");
            $stmt->execute();
            $column = $stmt->fetch(PDO::FETCH_ASSOC);

            $dataType = strtolower(trim((string) ($column['DATA_TYPE'] ?? '')));
            $length = (int) ($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
            $textTypes = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'];

            if (!in_array($dataType, $textTypes, true) || ($dataType === 'varchar' && $length < 255)) {
                $pdo->exec("ALTER TABLE orders MODIFY COLUMN financial_year VARCHAR(255) NULL");
            }
        } catch (Throwable $e) {
            error_log('orders.financial_year text conversion skipped: ' . $e->getMessage());
        }
    }

    private function currentSessionUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $possibleUserIds = [
            $_SESSION['user']['id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['client']['user_id'] ?? null,
            $_SESSION['admin_user']['id'] ?? null,
            $_SESSION['user_id'] ?? null,
            $_SESSION['auth_user_id'] ?? null,
            $_SESSION['id'] ?? null,
        ];

        foreach ($possibleUserIds as $value) {
            $userId = (int) $value;

            if ($userId > 0) {
                return $userId;
            }
        }

        return 0;
    }

    /**
     * Return true when the authenticated user is a partner.
     *
     * The order page must not trust a hidden form field for this decision.
     * We first honour an explicit session/user role value and then inspect
     * user_roles. The latter keeps this compatible with installations where
     * the role is stored in a separate RBAC table.
     */
    private function userIsPartner(int $userId, array $user = []): bool
    {
        $partnerValues = [
            $user['role'] ?? null,
            $user['user_type'] ?? null,
            $user['account_type'] ?? null,
            $user['user_role'] ?? null,
            $user['role_name'] ?? null,
            $user['role_slug'] ?? null,
            $user['type'] ?? null,
        ];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        foreach ([
            $_SESSION['user'] ?? [],
            $_SESSION['auth_user'] ?? [],
        ] as $sessionUser) {
            if (!is_array($sessionUser)) {
                continue;
            }

            foreach (['role', 'user_type', 'account_type', 'user_role', 'role_name', 'role_slug', 'type'] as $key) {
                $partnerValues[] = $sessionUser[$key] ?? null;
            }
        }

        foreach ($partnerValues as $value) {
            $normalized = strtolower(trim((string) $value));

            if ($normalized === 'partner' || str_contains($normalized, 'partner')) {
                return true;
            }
        }

        if ($userId <= 0 || !$this->tableExists('user_roles')) {
            return false;
        }

        try {
            /* Prefer the explicit RBAC permission when the application has it. */
            if (function_exists('can')) {
                try {
                    if ((bool) can('partners.orders.create_own')) {
                        return true;
                    }
                } catch (Throwable $e) {
                    // Continue with the database RBAC lookup below.
                }
            }

            if ($this->tableExists('role_permissions') && $this->tableExists('permissions')) {
                $rolePermissionColumns = $this->tableColumnsSafe('role_permissions');
                $permissionColumns = $this->tableColumnsSafe('permissions');
                $permissionNameColumn = null;

                foreach (['name', 'slug', 'key', 'code', 'permission'] as $candidate) {
                    if (in_array($candidate, $permissionColumns, true)) {
                        $permissionNameColumn = $candidate;
                        break;
                    }
                }

                if (
                    in_array('role_id', $rolePermissionColumns, true)
                    && in_array('permission_id', $rolePermissionColumns, true)
                    && in_array('role_id', $this->tableColumnsSafe('user_roles'), true)
                    && in_array('id', $permissionColumns, true)
                    && $permissionNameColumn !== null
                ) {
                    $permissionStmt = $this->db()->prepare(
                        'SELECT 1
                         FROM user_roles ur
                         INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
                         INNER JOIN permissions p ON p.id = rp.permission_id
                         WHERE ur.user_id = :user_id
                           AND p.`' . $permissionNameColumn . '` = :permission
                         LIMIT 1'
                    );
                    $permissionStmt->execute([
                        ':user_id' => $userId,
                        ':permission' => 'partners.orders.create_own',
                    ]);

                    if ($permissionStmt->fetchColumn() !== false) {
                        return true;
                    }
                }
            }

            $userRoleColumns = $this->tableColumnsSafe('user_roles');

            if (!in_array('user_id', $userRoleColumns, true)) {
                return false;
            }

            $stmt = $this->db()->prepare('SELECT * FROM user_roles WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId]);
            $userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($userRoles as $userRole) {
                foreach (['role', 'role_name', 'name', 'slug', 'role_slug', 'type', 'key'] as $key) {
                    $normalized = strtolower(trim((string) ($userRole[$key] ?? '')));

                    if ($normalized === 'partner' || str_contains($normalized, 'partner')) {
                        return true;
                    }
                }

                $roleId = (int) ($userRole['role_id'] ?? 0);

                if ($roleId <= 0 || !$this->tableExists('roles')) {
                    continue;
                }

                $roleColumns = $this->tableColumnsSafe('roles');

                if (!in_array('id', $roleColumns, true)) {
                    continue;
                }

                $roleStmt = $this->db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
                $roleStmt->execute([':id' => $roleId]);
                $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

                if (!is_array($role)) {
                    continue;
                }

                foreach (['permissions_json', 'permissions'] as $permissionKey) {
                    $rawPermissions = $role[$permissionKey] ?? null;

                    if (is_array($rawPermissions)) {
                        $permissions = $rawPermissions;
                    } else {
                        $decodedPermissions = json_decode((string) $rawPermissions, true);
                        $permissions = is_array($decodedPermissions) ? $decodedPermissions : [];
                    }

                    if (in_array('partners.orders.create_own', $permissions, true)) {
                        return true;
                    }
                }

                foreach (['role', 'name', 'slug', 'role_name', 'role_slug', 'type', 'key'] as $key) {
                    $normalized = strtolower(trim((string) ($role[$key] ?? '')));

                    if ($normalized === 'partner' || str_contains($normalized, 'partner')) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('partner role detection skipped: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Resolve the authenticated order actor.
     *
     * Partner orders remain in `orders`; `partner_id` stores users.id. A
     * normal client order has a NULL partner_id. Partner users are allowed to
     * reach the order page even when their client_id has not yet been linked;
     * store() will create/link that client profile before inserting the order.
     */
    private function currentOrderIdentity(): ?array
    {
        $sessionUserId = $this->currentSessionUserId();

        if ($sessionUserId <= 0) {
            return null;
        }

        $stmt = $this->db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $sessionUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user)) {
            return null;
        }

        $verifiedUserId = (int) ($user['id'] ?? 0);

        if ($verifiedUserId !== $sessionUserId) {
            return null;
        }

        $isPartner = $this->userIsPartner($verifiedUserId, $user);
        $clientId = (int) ($user['client_id'] ?? 0);

        if (!$isPartner && $clientId <= 0) {
            return null;
        }

        return [
            'user_id' => $verifiedUserId,
            'client_id' => $clientId,
            'partner_id' => $isPartner ? $verifiedUserId : null,
            'is_partner' => $isPartner,
            'user' => $user,
        ];
    }

    /**
     * Backward-compatible name used by the existing order/payment flow.
     */
    private function currentVerifiedUserClient(): ?array
    {
        return $this->currentOrderIdentity();
    }

    private function isLoggedInForOrder(): bool
    {
        return $this->currentOrderIdentity() !== null;
    }

    private function getServiceByIdOrSlug(int $serviceId = 0, string $slug = ''): ?array
    {
        $slug = trim($slug);

        if ($serviceId <= 0 && $slug === '') {
            return null;
        }

        $hasIsActive = $this->columnExists('services', 'is_active');
        $hasLegacyCategory = $this->columnExists('services', 'service_category');
        $hasCategoryId = $this->columnExists('services', 'service_category_id') && $this->tableExists('service_categories');

        $isActiveSelect = $hasIsActive ? 's.is_active AS is_active' : '1 AS is_active';
        $legacyCategorySelect = $hasLegacyCategory ? 's.service_category AS service_category' : "'' AS service_category";
        $categoryTitleSelect = $hasCategoryId ? 'sc.title AS category_title' : "'' AS category_title";
        $categoryJoin = $hasCategoryId ? 'LEFT JOIN service_categories sc ON sc.id = s.service_category_id' : '';

        if ($serviceId > 0) {
            $stmt = $this->db()->prepare("
                SELECT
                    s.id,
                    s.title,
                    s.slug,
                    s.description,
                    s.filing_fee,
                    s.turnaround_days,
                    s.icon,
                    {$isActiveSelect},
                    {$legacyCategorySelect},
                    {$categoryTitleSelect}
                FROM services s
                {$categoryJoin}
                WHERE s.id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $serviceId,
            ]);
        } else {
            $stmt = $this->db()->prepare("
                SELECT
                    s.id,
                    s.title,
                    s.slug,
                    s.description,
                    s.filing_fee,
                    s.turnaround_days,
                    s.icon,
                    {$isActiveSelect},
                    {$legacyCategorySelect},
                    {$categoryTitleSelect}
                FROM services s
                {$categoryJoin}
                WHERE s.slug = :slug
                LIMIT 1
            ");

            $stmt->execute([
                ':slug' => $slug,
            ]);
        }

        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($service)) {
            return null;
        }

        if ((int) ($service['id'] ?? 0) <= 0) {
            return null;
        }

        if ($serviceId > 0 && (int) $service['id'] !== $serviceId) {
            return null;
        }

        if ((int) ($service['is_active'] ?? 1) !== 1) {
            return null;
        }

        if (trim((string) ($service['slug'] ?? '')) === '') {
            return null;
        }

        return $service;
    }

    private function getRequirements(int $serviceId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                service_id,
                label,
                help_text,
                is_required,
                allow_multiple
            FROM service_requirements
            WHERE service_id = :service_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':service_id' => $serviceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function requiresFinancialYear(array $service): bool
    {
        // HARD LOCK: Financial Year is required only for these exact service slugs.
        // Do not check category/title here. Other Income Tax category services must remain non-mandatory.
        $serviceSlug = strtolower(trim((string) ($service['slug'] ?? $service['service_slug'] ?? '')));
        $serviceSlug = str_replace('_', '-', $serviceSlug);
        $serviceSlug = preg_replace('/[^a-z0-9-]+/', '-', $serviceSlug) ?: '';
        $serviceSlug = trim((string) preg_replace('/-+/', '-', $serviceSlug), '-');

        return isset([
            'itr-filing' => true,
            'itr-full-package' => true,
        ][$serviceSlug]);
    }

    private function requirementInputType(array $requirement): string
    {
        $label = strtolower(trim((string) ($requirement['label'] ?? '')));
        $label = preg_replace('/\s+/', ' ', $label) ?: $label;

        if (str_contains($label, 'email') || str_contains($label, 'e-mail')) {
            return 'email';
        }

        if (
            str_contains($label, 'mobile')
            || str_contains($label, 'phone')
            || str_contains($label, 'whatsapp')
            || str_contains($label, 'contact number')
        ) {
            return 'mobile';
        }

        return 'file';
    }

    private function requirementValueFieldName(int $requirementId): string
    {
        return 'requirement_value_' . $requirementId;
    }

    private function collectRequirementInputValues(array $requirements, string $customerMobile = '', string $customerEmail = ''): array
    {
        $values = [];

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0 || $this->requirementInputType($requirement) === 'file') {
                continue;
            }

            $label = trim((string) ($requirement['label'] ?? 'Field'));

            if ($label === '') {
                $label = 'Field';
            }

            $inputType = $this->requirementInputType($requirement);
            $value = trim((string) ($_POST[$this->requirementValueFieldName($requirementId)] ?? ''));

            if ($value === '') {
                $value = $this->customerRequirementValueFallback($inputType, $customerMobile, $customerEmail);
            }

            if ($value !== '') {
                $values[] = [
                    'id' => $requirementId,
                    'label' => $label,
                    'type' => $inputType,
                    'value' => $value,
                ];
            }
        }

        return $values;
    }

    private function appendRequirementInputValuesToNotes(string $notes, array $values): string
    {
        if ($values === []) {
            return $notes;
        }

        $lines = [];

        foreach ($values as $value) {
            $label = trim((string) ($value['label'] ?? 'Field'));
            $fieldValue = trim((string) ($value['value'] ?? ''));

            if ($fieldValue !== '') {
                $lines[] = $label . ': ' . $fieldValue;
            }
        }

        if ($lines === []) {
            return $notes;
        }

        $extra = 'Submitted fields:' . "\n" . implode("\n", $lines);

        if (trim($notes) === '') {
            return $extra;
        }

        return rtrim($notes) . "\n\n" . $extra;
    }

    private function customerRequirementValueFallback(string $inputType, string $customerMobile, string $customerEmail): string
    {
        if ($inputType === 'email') {
            return trim($customerEmail);
        }

        if ($inputType === 'mobile') {
            return trim($customerMobile);
        }

        return '';
    }

    private function appendCustomerContactToNotes(string $notes, string $customerMobile, string $customerEmail, string $customerNameAsPerPan = '', string $customerPanNumber = ''): string
    {
        $lines = [];

        if (trim($customerNameAsPerPan) !== '') {
            $lines[] = 'Name as per PAN: ' . trim($customerNameAsPerPan);
        }

        if (trim($customerPanNumber) !== '') {
            $lines[] = 'PAN Number: ' . strtoupper(trim($customerPanNumber));
        }

        if (trim($customerMobile) !== '') {
            $lines[] = 'Mobile Number: ' . trim($customerMobile);
        }

        if (trim($customerEmail) !== '') {
            $lines[] = 'Email Address: ' . trim($customerEmail);
        }

        if ($lines === []) {
            return $notes;
        }

        $extra = 'Customer contact details:' . "\n" . implode("\n", $lines);

        if (trim($notes) === '') {
            return $extra;
        }

        return rtrim($notes) . "\n\n" . $extra;
    }

    private function updateClientContactIfPossible(int $clientId, string $customerMobile, string $customerEmail): void
    {
        if ($clientId <= 0 || !$this->tableExists('clients')) {
            return;
        }

        $updates = [];
        $params = [':id' => $clientId];

        if (trim($customerMobile) !== '') {
            if ($this->columnExists('clients', 'mobile')) {
                $updates[] = 'mobile = :mobile';
                $params[':mobile'] = trim($customerMobile);
            }

            if ($this->columnExists('clients', 'phone')) {
                $updates[] = 'phone = :phone';
                $params[':phone'] = trim($customerMobile);
            }
        }

        if (trim($customerEmail) !== '' && $this->columnExists('clients', 'email')) {
            $updates[] = 'email = :email';
            $params[':email'] = trim($customerEmail);
        }

        if ($this->columnExists('clients', 'updated_at')) {
            $updates[] = 'updated_at = :updated_at';
            $params[':updated_at'] = $this->now();
        }

        if ($updates === []) {
            return;
        }

        $stmt = $this->db()->prepare('
            UPDATE clients
            SET ' . implode(', ', array_unique($updates)) . '
            WHERE id = :id
            LIMIT 1
        ');

        $stmt->execute($params);
    }

    private function tableColumnsSafe(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        try {
            $stmt = $this->db()->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $columns = [];

            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $columns[] = (string) $row['Field'];
                }
            }

            return $columns;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function ensureCustomerDetailsTable(): void
    {
        $pdo = $this->db();

        /*
         * IMPORTANT:
         * MySQL/MariaDB DDL statements such as CREATE TABLE / ALTER TABLE
         * cause an implicit COMMIT. Never run this method inside an active
         * order/payment transaction, otherwise the later PDO::commit() will
         * fail with: "There is no active transaction".
         */
        if ($pdo->inTransaction()) {
            error_log('customer_details schema sync skipped because a transaction is active.');
            return;
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS customer_details (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    order_id BIGINT UNSIGNED NULL,
                    client_id BIGINT UNSIGNED NULL,
                    user_id BIGINT UNSIGNED NULL,
                    service_id BIGINT UNSIGNED NULL,
                    name_as_per_pan VARCHAR(190) NULL,
                    pan_number VARCHAR(20) NULL,
                    mobile VARCHAR(30) NULL,
                    email VARCHAR(190) NULL,
                    submitted_by_user_id BIGINT UNSIGNED NULL,
                    submitted_by_name VARCHAR(190) NULL,
                    submitted_by_email VARCHAR(190) NULL,
                    submitted_by_phone VARCHAR(30) NULL,
                    source VARCHAR(50) NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_customer_details_order_id (order_id),
                    KEY idx_customer_details_client_id (client_id),
                    KEY idx_customer_details_pan_number (pan_number),
                    KEY idx_customer_details_submitted_by (submitted_by_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('customer_details create skipped: ' . $e->getMessage());
        }

        $columns = $this->tableColumnsSafe('customer_details');
        if ($columns === []) {
            return;
        }

        $requiredColumns = [
            'order_id' => 'BIGINT UNSIGNED NULL',
            'client_id' => 'BIGINT UNSIGNED NULL',
            'user_id' => 'BIGINT UNSIGNED NULL',
            'service_id' => 'BIGINT UNSIGNED NULL',
            'name_as_per_pan' => 'VARCHAR(190) NULL',
            'pan_number' => 'VARCHAR(20) NULL',
            'mobile' => 'VARCHAR(30) NULL',
            'email' => 'VARCHAR(190) NULL',
            'submitted_by_user_id' => 'BIGINT UNSIGNED NULL',
            'submitted_by_name' => 'VARCHAR(190) NULL',
            'submitted_by_email' => 'VARCHAR(190) NULL',
            'submitted_by_phone' => 'VARCHAR(30) NULL',
            'source' => 'VARCHAR(50) NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }

            try {
                $pdo->exec('ALTER TABLE customer_details ADD COLUMN `' . $column . '` ' . $definition);
                $columns[] = $column;
            } catch (Throwable $e) {
                error_log('customer_details alter skipped for ' . $column . ': ' . $e->getMessage());
            }
        }

        try {
            $pdo->exec('ALTER TABLE customer_details ADD UNIQUE KEY uq_customer_details_order_id (order_id)');
        } catch (Throwable $e) {
            // Key may already exist.
        }
    }

    private function currentSessionSubmitterDetails(int $userId): array
    {
        $details = [
            'id' => $userId,
            'name' => '',
            'email' => '',
            'phone' => '',
        ];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sessionUser = is_array($_SESSION['user'] ?? null)
            ? $_SESSION['user']
            : (is_array($_SESSION['auth_user'] ?? null) ? $_SESSION['auth_user'] : []);

        $details['name'] = trim((string) ($sessionUser['name'] ?? $sessionUser['full_name'] ?? ''));
        $details['email'] = trim((string) ($sessionUser['email'] ?? ''));
        $details['phone'] = trim((string) ($sessionUser['mobile'] ?? $sessionUser['phone'] ?? ''));

        if ($userId > 0 && $this->tableExists('users')) {
            try {
                $stmt = $this->db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (is_array($user)) {
                    $details['name'] = trim((string) ($user['name'] ?? $user['full_name'] ?? $details['name']));
                    $details['email'] = trim((string) ($user['email'] ?? $details['email']));
                    $details['phone'] = trim((string) ($user['mobile'] ?? $user['phone'] ?? $details['phone']));
                }
            } catch (Throwable $e) {
                error_log('submitter lookup skipped: ' . $e->getMessage());
            }
        }

        if ($details['name'] === '' && $userId > 0) {
            $details['name'] = 'User #' . $userId;
        }

        return $details;
    }

    private function saveCustomerDetailsForOrder(
        int $orderId,
        int $clientId,
        int $userId,
        int $serviceId,
        string $nameAsPerPan,
        string $panNumber,
        string $mobile,
        string $email,
        string $source
    ): void {
        if ($orderId <= 0) {
            return;
        }

        /*
         * The table/columns are synchronized by store() before beginTransaction().
         * Do not call ensureCustomerDetailsTable() while a transaction is active,
         * because its CREATE/ALTER statements can implicitly commit the order
         * transaction in MySQL/MariaDB.
         */
        $pdo = $this->db();

        if (!$pdo->inTransaction()) {
            $this->ensureCustomerDetailsTable();
        }

        $columns = $this->tableColumnsSafe('customer_details');

        if ($columns === []) {
            return;
        }

        $submitter = $this->currentSessionSubmitterDetails($userId);
        $now = $this->now();

        $data = [
            'order_id' => $orderId,
            'client_id' => $clientId,
            'user_id' => $userId,
            'service_id' => $serviceId,
            'name_as_per_pan' => trim($nameAsPerPan),
            'pan_number' => strtoupper(trim($panNumber)),
            'mobile' => trim($mobile),
            'email' => strtolower(trim($email)),
            'submitted_by_user_id' => (int) ($submitter['id'] ?? $userId),
            'submitted_by_name' => trim((string) ($submitter['name'] ?? '')),
            'submitted_by_email' => trim((string) ($submitter['email'] ?? '')),
            'submitted_by_phone' => trim((string) ($submitter['phone'] ?? '')),
            'source' => $source,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $clean = [];
        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $clean[$column] = $value;
            }
        }

        if ($clean === []) {
            return;
        }

        try {
            $existingId = null;

            if (in_array('order_id', $columns, true)) {
                $stmt = $this->db()->prepare('SELECT id FROM customer_details WHERE order_id = :order_id LIMIT 1');
                $stmt->execute([':order_id' => $orderId]);
                $found = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($found) && (int) ($found['id'] ?? 0) > 0) {
                    $existingId = (int) $found['id'];
                }
            }

            if ($existingId !== null && in_array('id', $columns, true)) {
                unset($clean['created_at']);
                $sets = [];
                $params = [':id' => $existingId];

                foreach ($clean as $column => $value) {
                    if ($column === 'id') {
                        continue;
                    }
                    $sets[] = '`' . $column . '` = :' . $column;
                    $params[':' . $column] = $value;
                }

                if ($sets !== []) {
                    $stmt = $this->db()->prepare('UPDATE customer_details SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1');
                    $stmt->execute($params);
                }

                return;
            }

            $insertColumns = array_keys($clean);
            $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
            $stmt = $this->db()->prepare('INSERT INTO customer_details (`' . implode('`, `', $insertColumns) . '`) VALUES (' . implode(', ', $placeholders) . ')');

            foreach ($clean as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }

            $stmt->execute();
        } catch (Throwable $e) {
            error_log('customer_details save skipped: ' . $e->getMessage());
        }
    }

    private function findClientOrder(int $orderId, int $clientId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                o.*,
                s.title AS service_title,
                s.slug AS service_slug,
                s.description AS service_description,
                s.filing_fee AS service_filing_fee,
                s.turnaround_days AS service_turnaround_days,
                s.icon AS service_icon
            FROM orders o
            INNER JOIN services s ON s.id = o.service_id
            WHERE o.id = :order_id
              AND o.client_id = :client_id
            LIMIT 1
        ");

        $stmt->execute([
            ':order_id' => $orderId,
            ':client_id' => $clientId,
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($order) ? $order : null;
    }

    /**
     * Load an order only when it belongs to the authenticated actor.
     * Partners are scoped by orders.partner_id; clients are scoped by
     * orders.client_id and cannot read partner-submitted orders accidentally.
     */
    private function findOrderForIdentity(int $orderId, array $identity): ?array
    {
        $where = 'o.id = :order_id';
        $params = [':order_id' => $orderId];

        if (!empty($identity['is_partner'])) {
            $where .= ' AND o.partner_id = :partner_id';
            $params[':partner_id'] = (int) ($identity['partner_id'] ?? $identity['user_id'] ?? 0);
        } else {
            $where .= ' AND o.client_id = :client_id AND (o.partner_id IS NULL OR o.partner_id = 0)';
            $params[':client_id'] = (int) ($identity['client_id'] ?? 0);
        }

        $stmt = $this->db()->prepare("
            SELECT
                o.*,
                s.title AS service_title,
                s.slug AS service_slug,
                s.description AS service_description,
                s.filing_fee AS service_filing_fee,
                s.turnaround_days AS service_turnaround_days,
                s.icon AS service_icon
            FROM orders o
            INNER JOIN services s ON s.id = o.service_id
            WHERE {$where}
            LIMIT 1
        ");

        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($order) ? $order : null;
    }

    private function generateOrderNo(int $orderId): string
    {
        return 'TSO-' . date('Y') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
    }

    private function uploadBaseDir(): string
    {
        if (function_exists('upload_path')) {
            return dirname(upload_path('__probe__'));
        }

        return dirname(__DIR__, 2) . '/storage/uploads';
    }

    private function ensureUploadDir(int $orderId): string
    {
        $dir = rtrim($this->uploadBaseDir(), '/\\') . '/orders/' . $orderId;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create upload directory.');
            }
        }

        return $dir;
    }

    private function cleanOriginalName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'upload';
        }

        return preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $name) ?: 'upload';
    }

    private function extensionFromName(string $name): string
    {
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    private function normalizeFiles(string $field): array
    {
        if (!isset($_FILES[$field])) {
            return [];
        }

        $file = $_FILES[$field];
        $files = [];

        if (is_array($file['name'] ?? null)) {
            $count = count($file['name']);

            for ($i = 0; $i < $count; $i++) {
                $error = (int) ($file['error'][$i] ?? UPLOAD_ERR_NO_FILE);

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $files[] = [
                    'name' => (string) ($file['name'][$i] ?? ''),
                    'type' => (string) ($file['type'][$i] ?? ''),
                    'tmp_name' => (string) ($file['tmp_name'][$i] ?? ''),
                    'error' => $error,
                    'size' => (int) ($file['size'][$i] ?? 0),
                ];
            }

            return $files;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [[
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'error' => $error,
            'size' => (int) ($file['size'] ?? 0),
        ]];
    }

    private function hasUploadedFile(string $field): bool
    {
        return $this->normalizeFiles($field) !== [];
    }

    private function validateUpload(array $file, array $allowedExtensions): void
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed. Please re-upload and try again.');
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('One uploaded file is larger than 10 MB.');
        }

        $extension = $this->extensionFromName((string) ($file['name'] ?? ''));

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Invalid file type uploaded.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded file.');
        }
    }

    private function storePhysicalFile(array $file, int $orderId, array $allowedExtensions): array
    {
        $this->validateUpload($file, $allowedExtensions);

        $uploadDir = $this->ensureUploadDir($orderId);

        $originalName = $this->cleanOriginalName((string) $file['name']);
        $extension = $this->extensionFromName($originalName);

        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }

        $mimeType = null;

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($targetPath);

            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        if ($mimeType === null) {
            $mimeType = (string) ($file['type'] ?? '');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => 'orders/' . $orderId . '/' . $fileName,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $file['size'],
        ];
    }

    private function insertOrderDocument(
        int $orderId,
        ?int $requirementId,
        string $source,
        string $label,
        array $storedFile,
        int $uploadedByUserId
    ): void {
        $stmt = $this->db()->prepare("
            INSERT INTO order_documents
                (
                    order_id,
                    requirement_id,
                    source,
                    label,
                    original_name,
                    stored_name,
                    mime_type,
                    size_bytes,
                    is_client_visible,
                    uploaded_by,
                    created_at
                )
            VALUES
                (
                    :order_id,
                    :requirement_id,
                    :source,
                    :label,
                    :original_name,
                    :stored_name,
                    :mime_type,
                    :size_bytes,
                    :is_client_visible,
                    :uploaded_by,
                    :created_at
                )
        ");

        $stmt->execute([
            ':order_id' => $orderId,
            ':requirement_id' => $requirementId,
            ':source' => $source,
            ':label' => $label,
            ':original_name' => $storedFile['original_name'],
            ':stored_name' => $storedFile['stored_name'],
            ':mime_type' => $storedFile['mime_type'],
            ':size_bytes' => $storedFile['size_bytes'],
            ':is_client_visible' => 0,
            ':uploaded_by' => $uploadedByUserId,
            ':created_at' => $this->now(),
        ]);
    }

    private function saveRequirementUploads(
        int $orderId,
        int $uploadedByUserId,
        array $requirements,
        string $source = 'client'
    ): void
    {
        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0 || $this->requirementInputType($requirement) !== 'file') {
                continue;
            }

            $field = 'requirement_' . $requirementId;
            $label = trim((string) ($requirement['label'] ?? 'Client Document'));

            if ($label === '') {
                $label = 'Client Document';
            }

            $files = $this->normalizeFiles($field);

            foreach ($files as $file) {
                $storedFile = $this->storePhysicalFile(
                    $file,
                    $orderId,
                    self::ALLOWED_DOCUMENT_EXTENSIONS
                );

                $this->insertOrderDocument(
                    $orderId,
                    $requirementId,
                    $source,
                    $label,
                    $storedFile,
                    $uploadedByUserId
                );
            }
        }
    }

   private function fetchUserForInlineAuth(string $identifier): ?array
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return null;
    }

    $conditions = [];
    $params = [];

    foreach (['mobile', 'phone', 'email'] as $column) {
        if ($this->columnExists('users', $column)) {
            $conditions[] = "{$column} = ?";
            $params[] = $identifier;
        }
    }

    if ($conditions === []) {
        return null;
    }

    $sql = "
        SELECT *
        FROM users
        WHERE " . implode(' OR ', $conditions) . "
        LIMIT 1
    ";

    $stmt = $this->db()->prepare($sql);
    $stmt->execute($params);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($user) ? $user : null;
}
    private function passwordIsValid(array $user, string $password): bool
    {
        $storedPassword = (string) ($user['password_hash'] ?? $user['password'] ?? '');

        if ($storedPassword === '') {
            return true;
        }

        $info = password_get_info($storedPassword);

        if (($info['algo'] ?? 0) !== 0) {
            return $password !== '' && password_verify($password, $storedPassword);
        }

        return $password !== '' && hash_equals($storedPassword, $password);
    }

    private function userIsActive(array $user): bool
    {
        if (array_key_exists('is_active', $user) && (int) $user['is_active'] !== 1) {
            return false;
        }

        $status = strtolower(trim((string) ($user['status'] ?? 'active')));

        if (in_array($status, ['inactive', 'blocked', 'suspended', 'disabled'], true)) {
            return false;
        }

        return true;
    }

    private function setOrderAuthSession(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $userId = (int) ($user['id'] ?? 0);

        $_SESSION['user_id'] = $userId;
        $_SESSION['auth_user_id'] = $userId;

        $_SESSION['user'] = [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? $user['full_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'mobile' => (string) ($user['mobile'] ?? $user['phone'] ?? ''),
            'client_id' => (int) ($user['client_id'] ?? 0),
            'role' => (string) ($user['role'] ?? 'client'),
        ];

        $_SESSION['auth_user'] = $_SESSION['user'];
    }

    private function insertDynamic(string $table, array $data): int
    {
        $clean = [];

        foreach ($data as $column => $value) {
            if ($this->columnExists($table, $column)) {
                $clean[$column] = $value;
            }
        }

        if ($clean === []) {
            throw new RuntimeException("No valid columns found for {$table}.");
        }

        $columns = array_keys($clean);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $stmt = $this->db()->prepare("
            INSERT INTO {$table}
                (" . implode(', ', $columns) . ")
            VALUES
                (" . implode(', ', $placeholders) . ")
        ");

        foreach ($clean as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }

        $stmt->execute();

        return (int) $this->db()->lastInsertId();
    }

    private function ensureClientForUser(array $user, string $name, string $mobile, string $email = ''): int
    {
        $userId = (int) ($user['id'] ?? 0);
        $existingClientId = (int) ($user['client_id'] ?? 0);

        if ($existingClientId > 0) {
            return $existingClientId;
        }

        if (!$this->tableExists('clients')) {
            throw new RuntimeException('clients table not found.');
        }

        $clientId = $this->insertDynamic('clients', [
            'user_id' => $userId,
            'name' => $name,
            'full_name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'phone' => $mobile,
            'status' => 'active',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        if ($clientId <= 0) {
            throw new RuntimeException('Unable to create client profile.');
        }

        if ($this->columnExists('users', 'client_id')) {
            $stmt = $this->db()->prepare("
                UPDATE users
                SET client_id = :client_id
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':client_id' => $clientId,
                ':id' => $userId,
            ]);
        }

        return $clientId;
    }

    private function createInlineClientUser(string $name, string $mobile, string $email = '', string $password = ''): array
    {
        $now = $this->now();
        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

        $clientId = 0;

        if ($this->tableExists('clients')) {
            try {
                $clientId = $this->insertDynamic('clients', [
                    'name' => $name,
                    'full_name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'phone' => $mobile,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (Throwable $e) {
                $clientId = 0;
            }
        }

        $userId = $this->insertDynamic('users', [
            'name' => $name,
            'full_name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'phone' => $mobile,
            'client_id' => $clientId > 0 ? $clientId : null,
            'role' => 'client',
            'user_type' => 'client',
            'status' => 'active',
            'is_active' => 1,
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($clientId > 0 && $this->tableExists('clients') && $this->columnExists('clients', 'user_id')) {
            $stmt = $this->db()->prepare("
                UPDATE clients
                SET user_id = :user_id
                WHERE id = :client_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':client_id' => $clientId,
            ]);
        }

        $stmt = $this->db()->prepare("
            SELECT *
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $userId,
        ]);

        $createdUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($createdUser)) {
            throw new RuntimeException('Unable to create user account.');
        }

        if ((int) ($createdUser['client_id'] ?? 0) <= 0) {
            $createdUser['client_id'] = $this->ensureClientForUser($createdUser, $name, $mobile, $email);
        }

        return $createdUser;
    }

    public function inlineLogin(): void
    {
        try {
            $identifier = trim((string) ($_POST['identifier'] ?? $_POST['mobile'] ?? $_POST['email'] ?? ''));
            $password = trim((string) ($_POST['password'] ?? ''));

            if ($identifier === '') {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Please enter mobile number or email.',
                ], 422);
            }

            $user = $this->fetchUserForInlineAuth($identifier);

            if (!$user) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Account not found. Please sign up to continue.',
                ], 404);
            }

            if (!$this->userIsActive($user)) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'This account is inactive. Please contact support.',
                ], 403);
            }

            if (!$this->passwordIsValid($user, $password)) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Invalid password.',
                ], 422);
            }

            $name = (string) ($user['name'] ?? $user['full_name'] ?? 'Client');
            $mobile = (string) ($user['mobile'] ?? $user['phone'] ?? $identifier);
            $email = (string) ($user['email'] ?? '');

            $clientId = $this->ensureClientForUser($user, $name, $mobile, $email);
            $user['client_id'] = $clientId;

            $this->setOrderAuthSession($user);

            $this->jsonResponse([
                'ok' => true,
                'message' => 'Login successful. Submitting your order now.',
                'user_id' => (int) $user['id'],
                'client_id' => $clientId,
            ]);
        } catch (Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function inlineSignup(): void
    {
        try {
            $name = trim((string) ($_POST['name'] ?? $_POST['full_name'] ?? ''));
            $mobile = trim((string) ($_POST['mobile'] ?? $_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = trim((string) ($_POST['password'] ?? ''));

            if ($name === '') {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Please enter your full name.',
                ], 422);
            }

            if ($mobile === '') {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Please enter your mobile number.',
                ], 422);
            }

            $existing = $this->fetchUserForInlineAuth($mobile);

            if (!$existing && $email !== '') {
                $existing = $this->fetchUserForInlineAuth($email);
            }

            if ($existing) {
                if (!$this->userIsActive($existing)) {
                    $this->jsonResponse([
                        'ok' => false,
                        'message' => 'Existing account is inactive. Please contact support.',
                    ], 403);
                }

                $clientId = $this->ensureClientForUser(
                    $existing,
                    (string) ($existing['name'] ?? $existing['full_name'] ?? $name),
                    (string) ($existing['mobile'] ?? $existing['phone'] ?? $mobile),
                    (string) ($existing['email'] ?? $email)
                );

                $existing['client_id'] = $clientId;
                $this->setOrderAuthSession($existing);

                $this->jsonResponse([
                    'ok' => true,
                    'message' => 'Existing account found. Logged in successfully.',
                    'user_id' => (int) $existing['id'],
                    'client_id' => $clientId,
                ]);
            }

            $user = $this->createInlineClientUser($name, $mobile, $email, $password);
            $this->setOrderAuthSession($user);

            $this->jsonResponse([
                'ok' => true,
                'message' => 'Account created. Submitting your order now.',
                'user_id' => (int) $user['id'],
                'client_id' => (int) ($user['client_id'] ?? 0),
            ]);
        } catch (Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'Signup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function orderForm(): void
    {
        $serviceId = (int) ($_GET['service_id'] ?? $_GET['id'] ?? 0);
        $serviceSlug = trim((string) ($_GET['slug'] ?? $_GET['service_slug'] ?? $_GET['service'] ?? ''));

        if ($serviceId <= 0 && $serviceSlug === '') {
            $this->flashMessage('error', 'Please select a service first.');
            $this->go($this->url('services'));
            return;
        }

        $service = $this->getServiceByIdOrSlug($serviceId, $serviceSlug);

        if (!$service) {
            $this->flashMessage('error', 'Service not found or inactive.');
            $this->go($this->url('services'));
            return;
        }

        $requirements = $this->getRequirements((int) $service['id']);
        $orderIdentity = $this->currentOrderIdentity();

        $this->view('public/order-service', [
            'step' => 'order',
            'service' => $service,
            'serviceSlug' => (string) $service['slug'],
            'showFinancialYear' => $this->requiresFinancialYear($service),
            'financialYears' => $this->financialYearTextOptions(),
            'selectedFinancialYear' => self::FINANCIAL_YEAR_TEXT_OPTIONS[0],
            'requirements' => $requirements,
            'isLoggedInForOrder' => $orderIdentity !== null,
            'isPartnerOrder' => !empty($orderIdentity['is_partner']),
        ]);
    }

    public function store(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->go($this->url('services'));
            return;
        }

        $inlineAuthAction = trim((string) ($_POST['_inline_auth'] ?? ''));

        if ($inlineAuthAction !== '') {
            if ($inlineAuthAction === 'login') {
                $this->inlineLogin();
                return;
            }

            if ($inlineAuthAction === 'signup') {
                $this->inlineSignup();
                return;
            }

            $this->jsonResponse([
                'ok' => false,
                'message' => 'Invalid inline auth action.',
            ], 422);
        }

        $identity = $this->currentOrderIdentity();

        if (!$identity) {
            $this->flashMessage('error', 'Please login or sign up on this page before submitting your order.');
            $this->go($_SERVER['HTTP_REFERER'] ?? $this->url('services'));
            return;
        }

        $userId = (int) $identity['user_id'];
        $clientId = (int) $identity['client_id'];
        $isPartnerOrder = !empty($identity['is_partner']);
        $partnerId = $isPartnerOrder ? (int) ($identity['partner_id'] ?? $userId) : null;

        /*
         * `orders.client_id` is required by the existing schema. A partner
         * account may not have a client profile yet, so link one lazily using
         * the authenticated partner's own account. The actual customer/PAN
         * details remain in customer_details and are not written over the
         * partner's profile.
         */
        if ($isPartnerOrder && $clientId <= 0) {
            $submitter = $this->currentSessionSubmitterDetails($userId);
            $clientId = $this->ensureClientForUser(
                $identity['user'] ?? ['id' => $userId],
                (string) ($submitter['name'] ?? 'Partner'),
                (string) ($submitter['phone'] ?? ''),
                (string) ($submitter['email'] ?? '')
            );
        }

        if ($clientId <= 0) {
            $this->flashMessage('error', 'Your account is not linked to a client profile yet. Please contact the administrator.');
            $this->go($this->url('auth'));
            return;
        }

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $serviceSlug = trim((string) ($_POST['service_slug'] ?? ''));
        // Financial Year is plain text. Decode HTML entities and store the submitted label unchanged.
        $financialYear = html_entity_decode(
            trim((string) ($_POST['financial_year'] ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $customerNameAsPerPan = trim((string) ($_POST['customer_name_as_per_pan'] ?? $_POST['name_as_per_pan'] ?? ''));
        $customerPanNumber = strtoupper(trim((string) ($_POST['customer_pan_number'] ?? $_POST['pan_number'] ?? '')));
        $customerMobile = trim((string) ($_POST['customer_mobile'] ?? $_POST['mobile'] ?? $_POST['phone'] ?? ''));
        $customerEmail = trim((string) ($_POST['customer_email'] ?? $_POST['email'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $postedPaymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
        $paymentMethod = $this->normalizePaymentMethod($postedPaymentMethod !== '' ? $postedPaymentMethod : 'upi');

        $service = $this->getServiceByIdOrSlug($serviceId, $serviceSlug);

        if (!$service) {
            $this->flashMessage('error', 'Invalid service selected.');
            $this->go($this->url('services'));
            return;
        }

        if ($paymentMethod === '') {
            $this->flashMessage('error', 'Please select a valid payment method: UPI, Bank Transfer, or Cash on Delivery (Pay Later).');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if ($customerNameAsPerPan === '') {
            $this->flashMessage('error', 'Please enter Name as per PAN.');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if ($customerPanNumber === '') {
            $this->flashMessage('error', 'Please enter PAN number.');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $customerPanNumber)) {
            $this->flashMessage('error', 'Please enter a valid PAN number. Example: ABCDE1234F');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if ($customerMobile === '') {
            $this->flashMessage('error', 'Please enter mobile number.');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if ($customerEmail === '') {
            $this->flashMessage('error', 'Please enter email address.');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessage('error', 'Please enter a valid email address.');
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        $requiresFinancialYear = $this->requiresFinancialYear($service);

        /*
         * ZERO FORMAT VALIDATION:
         * - For ITR services, save the normal text exactly as submitted.
         * - If an older/cached form submits nothing, use the current one-year text.
         * - For every other service, keep financial_year empty.
         */
        if ($requiresFinancialYear) {
            if ($financialYear === '') {
                $financialYear = self::FINANCIAL_YEAR_TEXT_OPTIONS[0];
            }
        } else {
            $financialYear = '';
        }

        $requirements = $this->getRequirements((int) $service['id']);
        $missingDocuments = [];
        $missingFields = [];
        $invalidFields = [];

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);
            $isRequired = (int) ($requirement['is_required'] ?? 0) === 1;
            $label = trim((string) ($requirement['label'] ?? 'Required field'));
            $inputType = $this->requirementInputType($requirement);

            if ($label === '') {
                $label = $inputType === 'file' ? 'Required document' : 'Required field';
            }

            if ($requirementId <= 0 || !$isRequired) {
                continue;
            }

            if ($inputType === 'file') {
                $field = 'requirement_' . $requirementId;

                if (!$this->hasUploadedFile($field)) {
                    $missingDocuments[] = $label;
                }

                continue;
            }

            $value = trim((string) ($_POST[$this->requirementValueFieldName($requirementId)] ?? ''));

            if ($value === '') {
                $value = $this->customerRequirementValueFallback($inputType, $customerMobile, $customerEmail);
            }

            if ($value === '') {
                $missingFields[] = $label;
                continue;
            }

            if ($inputType === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $invalidFields[] = $label;
            }
        }

        if ($missingFields !== []) {
            $this->flashMessage('error', 'Please enter required field: ' . implode(', ', $missingFields));
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if ($invalidFields !== []) {
            $this->flashMessage('error', 'Please enter a valid value for: ' . implode(', ', $invalidFields));
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        if ($missingDocuments !== []) {
            $this->flashMessage('error', 'Please upload required document: ' . implode(', ', $missingDocuments));
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
            return;
        }

        $notes = $this->appendCustomerContactToNotes($notes, $customerMobile, $customerEmail, $customerNameAsPerPan, $customerPanNumber);

        $notes = $this->appendRequirementInputValuesToNotes(
            $notes,
            $this->collectRequirementInputValues($requirements, $customerMobile, $customerEmail)
        );

        if (!$isPartnerOrder) {
            $this->updateClientContactIfPossible($clientId, $customerMobile, $customerEmail);
        }

        $this->ensureCustomerDetailsTable();
        $this->ensureFinancialYearTextColumn();

        $pdo = $this->db();

        try {
            $pdo->beginTransaction();

            $now = $this->now();
            $temporaryOrderNo = 'TMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

            $stmt = $pdo->prepare("
                INSERT INTO orders
                    (
                        order_no,
                        client_id,
                        service_id,
                        financial_year,
                        fee_amount,
                        status,
                        payment_method,
                        payment_status,
                        payment_reference,
                        notes,
                        partner_id,
                        created_at,
                        updated_at
                    )
                VALUES
                    (
                        :order_no,
                        :client_id,
                        :service_id,
                        :financial_year,
                        :fee_amount,
                        :status,
                        :payment_method,
                        :payment_status,
                        :payment_reference,
                        :notes,
                        :partner_id,
                        :created_at,
                        :updated_at
                    )
            ");

            $stmt->execute([
                ':order_no' => $temporaryOrderNo,
                ':client_id' => $clientId,
                ':service_id' => (int) $service['id'],
                ':financial_year' => $financialYear,
                ':fee_amount' => (float) ($service['filing_fee'] ?? 0),
                ':status' => 'submitted',
                ':payment_method' => $paymentMethod,
                ':payment_status' => 'pending',
                ':payment_reference' => null,
                ':notes' => $notes !== '' ? $notes : null,
                ':partner_id' => $partnerId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $orderNo = $this->generateOrderNo($orderId);

            $updateOrderNo = $pdo->prepare("
                UPDATE orders
                SET order_no = :order_no,
                    updated_at = :updated_at
                WHERE id = :id
                LIMIT 1
            ");

            $updateOrderNo->execute([
                ':order_no' => $orderNo,
                ':updated_at' => $now,
                ':id' => $orderId,
            ]);

            $this->saveRequirementUploads(
                $orderId,
                $userId,
                $requirements,
                'client'
            );

            $this->saveCustomerDetailsForOrder(
                $orderId,
                $clientId,
                $userId,
                (int) $service['id'],
                $customerNameAsPerPan,
                $customerPanNumber,
                $customerMobile,
                $customerEmail,
                $isPartnerOrder ? 'partner_order' : 'public_order'
            );

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }

            $_SESSION['last_service_order_id'] = $orderId;

            $paymentLabel = $this->paymentMethodLabel($paymentMethod);
            $this->flashMessage(
                'success',
                $isPartnerOrder
                    ? 'Partner order submitted successfully. Continue with your selected ' . $paymentLabel . ' method.'
                    : 'Order submitted successfully. Continue with your selected ' . $paymentLabel . ' method.'
            );
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->flashMessage('error', 'Unable to submit order: ' . $e->getMessage());
            $this->go($this->url('service-order?service_id=' . (int) $service['id']));
        }
    }

    public function payment(): void
    {
        $identity = $this->currentOrderIdentity();

        if (!$identity) {
            $this->flashMessage('error', 'Please login with a valid client or partner account to continue payment.');
            $this->go($this->url('auth'));
            return;
        }

        $clientId = (int) $identity['client_id'];
        $isPartnerOrder = !empty($identity['is_partner']);
        $ordersHomeUrl = $isPartnerOrder ? 'partner/orders' : 'client/orders';

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $orderId = (int) ($_GET['order_id'] ?? 0);

        if ($orderId <= 0) {
            $orderId = (int) ($_GET['id'] ?? $_SESSION['last_service_order_id'] ?? 0);
        }

        if ($orderId <= 0) {
            $this->flashMessage('error', 'Order not found.');
            $this->go($this->url($ordersHomeUrl));
            return;
        }

        $order = $this->findOrderForIdentity($orderId, $identity);

        if (!$order) {
            $this->flashMessage('error', 'Order not found or access denied.');
            $this->go($this->url($ordersHomeUrl));
            return;
        }

        $service = [
            'id' => $order['service_id'],
            'title' => $order['service_title'],
            'slug' => $order['service_slug'],
            'description' => $order['service_description'],
            'filing_fee' => ($order['payable_amount'] ?? null) !== null
                ? $order['payable_amount']
                : $order['fee_amount'],
            'turnaround_days' => $order['service_turnaround_days'],
            'icon' => $order['service_icon'],
        ];

        $paymentConfig = $this->paymentConfig();

        $this->view('public/order-service', [
            'step' => 'payment',
            'order' => $order,
            'service' => $service,
            'showFinancialYear' => $this->requiresFinancialYear($service),
            'requirements' => [],
            'paymentUpiId' => (string) ($paymentConfig['upi_id'] ?? 'taxsaathi@upi'),
            'paymentReceiverName' => (string) ($paymentConfig['company_name'] ?? 'Tax Saathi'),
            'paymentQrImage' => $this->paymentAssetUrl((string) ($paymentConfig['upi_qr'] ?? '')),
            'paymentBankName' => (string) ($paymentConfig['bank_name'] ?? ''),
            'paymentBankIfsc' => (string) ($paymentConfig['bank_ifsc'] ?? ''),
            'paymentBankAccountNumber' => (string) ($paymentConfig['bank_account_number'] ?? ''),
            'isLoggedInForOrder' => true,
            'isPartnerOrder' => $isPartnerOrder,
        ]);
    }

    public function submitPayment(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->go($this->url('services'));
            return;
        }

        $identity = $this->currentOrderIdentity();

        if (!$identity) {
            $this->flashMessage('error', 'Please login with a valid client or partner account to complete the payment step.');
            $this->go($this->url('auth'));
            return;
        }

        $userId = (int) $identity['user_id'];
        $clientId = (int) $identity['client_id'];
        $isPartnerOrder = !empty($identity['is_partner']);
        $partnerId = $isPartnerOrder ? (int) ($identity['partner_id'] ?? $userId) : null;
        $ordersHomeUrl = $isPartnerOrder ? 'partner/orders' : 'client/orders';

        $orderId = (int) ($_POST['order_id'] ?? 0);

        if ($orderId <= 0) {
            $orderId = (int) ($_POST['id'] ?? 0);
        }

        $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));

        if ($transactionId === '') {
            $transactionId = trim((string) ($_POST['payment_reference'] ?? ''));
        }

        if ($orderId <= 0) {
            $this->flashMessage('error', 'Invalid order.');
            $this->go($this->url($ordersHomeUrl));
            return;
        }

        $order = $this->findOrderForIdentity($orderId, $identity);

        if (!$order) {
            $this->flashMessage('error', 'Order not found or access denied.');
            $this->go($this->url($ordersHomeUrl));
            return;
        }

        // The method saved with the order is authoritative. The posted value
        // is only used for older orders that do not yet have a valid method.
        $storedPaymentMethod = $this->normalizePaymentMethod((string) ($order['payment_method'] ?? ''));
        $postedPaymentMethod = $this->normalizePaymentMethod((string) ($_POST['payment_method'] ?? ''));
        $paymentMethod = $storedPaymentMethod !== ''
            ? $storedPaymentMethod
            : ($postedPaymentMethod !== '' ? $postedPaymentMethod : 'upi');
        $paymentLabel = $this->paymentMethodLabel($paymentMethod);
        $requiresPaymentProof = in_array($paymentMethod, ['upi', 'bank_transfer'], true);

        if ($requiresPaymentProof && $transactionId === '') {
            $this->flashMessage(
                'error',
                $paymentMethod === 'bank_transfer'
                    ? 'Please enter the bank transfer UTR or reference number.'
                    : 'Please enter the UPI transaction ID.'
            );
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
            return;
        }

        $paymentProofField = $this->hasUploadedFile('payment_screenshot')
            ? 'payment_screenshot'
            : 'payment_proof';

        if ($requiresPaymentProof && !$this->hasUploadedFile($paymentProofField)) {
            $this->flashMessage('error', 'Please upload the payment screenshot or receipt.');
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
            return;
        }

        $this->ensureCustomerDetailsTable();

        $pdo = $this->db();

        try {
            $pdo->beginTransaction();

            if ($requiresPaymentProof) {
                $files = $this->normalizeFiles($paymentProofField);
                $paymentFile = $files[0] ?? null;

                if (!$paymentFile) {
                    throw new RuntimeException('Payment screenshot or receipt file not found.');
                }

                $storedFile = $this->storePhysicalFile(
                    $paymentFile,
                    $orderId,
                    self::ALLOWED_PAYMENT_EXTENSIONS
                );

                $this->insertOrderDocument(
                    $orderId,
                    null,
                    'payment_proof',
                    $paymentLabel . ' Payment Proof',
                    $storedFile,
                    $userId
                );
            }

            $where = 'id = :id';
            $params = [
                ':payment_method' => $paymentMethod,
                ':payment_status' => $requiresPaymentProof ? 'pending_review' : 'pending',
                ':payment_reference' => $requiresPaymentProof
                    ? $transactionId
                    : ($transactionId !== '' ? $transactionId : 'COD'),
                ':updated_at' => $this->now(),
                ':id' => $orderId,
            ];

            if ($isPartnerOrder) {
                $where .= ' AND partner_id = :partner_id';
                $params[':partner_id'] = $partnerId;
            } else {
                $where .= ' AND client_id = :client_id AND (partner_id IS NULL OR partner_id = 0)';
                $params[':client_id'] = $clientId;
            }

            $stmt = $pdo->prepare("
                UPDATE orders
                SET payment_method = :payment_method,
                    payment_status = :payment_status,
                    payment_reference = :payment_reference,
                    updated_at = :updated_at
                WHERE {$where}
                LIMIT 1
            ");

            $stmt->execute($params);

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            $this->flashMessage(
                'success',
                $requiresPaymentProof
                    ? $paymentLabel . ' payment proof submitted successfully. Payment is now pending admin verification.'
                    : 'Cash on Delivery request submitted successfully. It is now pending admin confirmation.'
            );
            $this->go($this->url(
                $isPartnerOrder
                    ? 'partner/orders/show?id=' . $orderId
                    : 'client/orders/view?id=' . $orderId
            ));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->flashMessage('error', 'Unable to submit the payment update: ' . $e->getMessage());
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
        }
    }
}
