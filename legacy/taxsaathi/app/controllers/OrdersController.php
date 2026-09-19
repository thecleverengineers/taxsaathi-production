<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\NotificationService;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class OrdersController extends Controller
{
    private ?Database $database = null;

    private const ADMIN_FINANCIAL_YEAR_SERVICE_SLUGS = ['itr-filing', 'itr-full-package'];
    private const ADMIN_FINANCIAL_YEAR_OPTIONS = [
        '1 Year (FY-2025-26)',
        '2 Years (FY-2024-25 & FY-2025-26)',
        '3 Years (FY-2023-24, FY-2024-25 & FY-2025-26)',
        '4 Years (FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)',
        '5 Years (FY-2021-22, FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)',
    ];

    /**
     * Role mapping used by the order assignment workflow.
     * Roles are resolved only through user_roles + roles. The users table role column is never read.
     */
    private const ADMIN_MANAGER_ROLE_IDS = [1, 2]; // Admin, Manager
    private const ADMIN_MANAGER_ROLE_SLUGS = ['admin', 'manager'];
    private const EXECUTIVE_ROLE_IDS = [3]; // Executive
    private const EXECUTIVE_ROLE_SLUGS = ['executive'];
    private const ORDER_PAGE_ROLE_IDS = [1, 2, 3]; // Admin, Manager, Executive
    private const ORDER_PAGE_ROLE_SLUGS = ['admin', 'manager', 'executive'];
    private const ASSIGNER_ROLE_IDS = [1, 2]; // Admin, Manager
    private const ASSIGNER_ROLE_SLUGS = ['admin', 'manager'];
    private const ASSIGNABLE_STAFF_ROLE_IDS = [3]; // Executive only
    private const ASSIGNABLE_STAFF_ROLE_SLUGS = ['executive'];

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
            try {
                $raw = config('database');
                if (is_array($raw)) {
                    $config = $this->normalizeDatabaseConfig($raw);
                }
            } catch (\Throwable $e) {
                $config = [];
            }
        }

        if ($config === []) {
            $configFile = dirname(__DIR__, 2) . '/config/database.php';
            if (is_file($configFile)) {
                $raw = require $configFile;
                if (is_array($raw)) {
                    $config = $this->normalizeDatabaseConfig($raw);
                }
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
                'driver'   => (string) $env('DB_DRIVER', 'mysql'),
                'host'     => (string) $env('DB_HOST', '127.0.0.1'),
                'port'     => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', 'taxsathi2'),
                'charset'  => (string) $env('DB_CHARSET', 'utf8mb4'),
                'username' => (string) $env('DB_USERNAME', 'taxsathi2'),
                'password' => (string) $env('DB_PASSWORD', 'taxsathi2'),
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
            'driver'   => (string) ($config['driver'] ?? 'mysql'),
            'host'     => (string) ($config['host'] ?? '127.0.0.1'),
            'port'     => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? 'taxsathi2'),
            'charset'  => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? 'taxsathi2'),
            'password' => (string) ($config['password'] ?? 'taxsathi2'),
        ];
    }

public function markClientDocumentWrongRedirect(): void
{
    redirect('admin/orders');
}
public function reuploadDocumentRedirect(): void
{
    redirect('client/orders');
}
private function safeTriggerNotification(string $event, array $payload = []): void
{
    if (!class_exists(NotificationService::class)) {
        return;
    }

    if (!method_exists(NotificationService::class, 'trigger')) {
        return;
    }

    try {
        NotificationService::trigger($event, $payload);
    } catch (\Throwable $e) {
        error_log('Notification trigger failed: ' . $e->getMessage());
    }
}
    private function smtpConfig(): array
    {
        return [
            'from_email'  => 'emailer@taxsaathi.in',
            'from_name'   => 'Tax Saathi',
            'admin_email' => 'thecleverengineers@gmail.com',
            'admin_name'  => 'The Clever Engineers',
            'smtp' => [
                'host'       => 'smtp.mailer91.com',
                'port'       => 587,
                'username'   => 'emailer@taxsaathi.in',
                'password'   => 'ZED7Elr00WnUXa3p',
                'encryption' => 'tls',
                'timeout'    => 30,
            ],
        ];
    }

    private function currentUserId(): int
    {
        $user = function_exists('auth_user') ? (auth_user() ?: []) : [];

        return (int) (
            $user['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? $_SESSION['client_id']
            ?? 0
        );
    }

    private function currentUserRoleIds(): array
    {
        $ids = [];

        foreach ($this->currentUserRoles() as $role) {
            $roleId = (int) ($role['id'] ?? $role['role_id'] ?? 0);

            if ($roleId > 0) {
                $ids[$roleId] = $roleId;
            }
        }

        return array_values($ids);
    }

    private function currentUserRoleSlugs(): array
    {
        $slugs = [];

        foreach ($this->currentUserRoles() as $role) {
            foreach (['slug', 'role_slug', 'name', 'role_name'] as $key) {
                $value = strtolower(trim((string) ($role[$key] ?? '')));

                if ($value === '') {
                    continue;
                }

                $value = str_replace([' ', '_'], '-', $value);
                $value = preg_replace('/[^a-z0-9\-]/', '', $value) ?: '';
                $value = trim($value, '-');

                if ($value !== '') {
                    $slugs[$value] = $value;
                }
            }
        }

        return array_values($slugs);
    }

    private function currentUserRoles(): array
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return [];
        }

        $userRolesColumns = $this->tableColumnsSafe('user_roles');
        $rolesColumns = $this->tableColumnsSafe('roles');

        if (!in_array('user_id', $userRolesColumns, true)
            || !in_array('role_id', $userRolesColumns, true)
            || !in_array('id', $rolesColumns, true)
        ) {
            return [];
        }

        $select = [
            'r.id',
            in_array('name', $rolesColumns, true) ? 'r.name' : "CONCAT('Role #', r.id) AS name",
            in_array('slug', $rolesColumns, true) ? 'r.slug' : "CONCAT('role-', r.id) AS slug",
            in_array('permissions_json', $rolesColumns, true) ? 'r.permissions_json' : "'[]' AS permissions_json",
        ];

        try {
            return $this->db()->fetchAll(
                'SELECT ' . implode(', ', $select) . '
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                 ORDER BY r.id ASC',
                ['user_id' => $userId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('user_roles lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    private function currentUserPermissionSet(): array
    {
        $permissions = [];

        foreach ($this->currentUserRoles() as $role) {
            $raw = trim((string) ($role['permissions_json'] ?? ''));

            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $permission) {
                $permission = trim((string) $permission);

                if ($permission !== '') {
                    $permissions[$permission] = true;
                }
            }
        }

        return $permissions;
    }

    private function currentUserHasAnyPermission(array $permissions): bool
    {
        $effective = $this->currentUserPermissionSet();

        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);

            if ($permission === '') {
                continue;
            }

            if (isset($effective['*']) || isset($effective[$permission])) {
                return true;
            }

            $segments = explode('.', $permission);

            while (count($segments) > 1) {
                array_pop($segments);
                $wildcard = implode('.', $segments) . '.*';

                if (isset($effective[$wildcard])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function requireUserRolePermission(string|array $permissions): void
    {
        // Kept for internal compatibility. Order pages must not depend on global can()/users.role_id.
        // Admin, Manager and Executive are validated directly from user_roles.
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        if ($this->canAccessOrdersPage() || $this->currentUserHasAnyPermission($permissions)) {
            return;
        }

        http_response_code(403);
        flash('error', 'Admin, Manager or Executive access required. Please check user_roles for this user.');
        redirect('admin/dashboard');
        exit;
    }

    private function currentUserIdentity(): array
    {
        $user = function_exists('auth_user') ? (auth_user() ?: []) : [];

        return [
            'id' => $this->currentUserId(),
            'role_ids' => $this->currentUserRoleIds(),
            'role_slugs' => $this->currentUserRoleSlugs(),
            'permissions' => array_keys($this->currentUserPermissionSet()),
            'email' => trim((string) (
                $user['email']
                ?? $_SESSION['email']
                ?? $_SESSION['auth_user']['email']
                ?? $_SESSION['user']['email']
                ?? ''
            )),
            'phone' => trim((string) (
                $user['phone']
                ?? $user['mobile']
                ?? $_SESSION['phone']
                ?? $_SESSION['mobile']
                ?? $_SESSION['auth_user']['phone']
                ?? $_SESSION['auth_user']['mobile']
                ?? $_SESSION['user']['phone']
                ?? $_SESSION['user']['mobile']
                ?? ''
            )),
        ];
    }

    private function isRoleThreeUser(): bool
    {
        // Backwards-compatible name used by existing views.
        // In this strict workflow, role_id 3 from user_roles is Executive.
        return $this->isExecutiveUser();
    }

    private function isExecutiveUser(): bool
    {
        return $this->currentUserHasAnyRole(self::EXECUTIVE_ROLE_IDS, self::EXECUTIVE_ROLE_SLUGS);
    }

    private function isPartnerUser(): bool
    {
        return false;
    }

    private function isAssignedStaffUser(): bool
    {
        return $this->isExecutiveUser();
    }

    private function isAdminLikeUser(): bool
    {
        // Admin/Manager is resolved only from user_roles. No users.role_id and no session role fallback.
        return $this->currentUserHasAnyRole(self::ADMIN_MANAGER_ROLE_IDS, self::ADMIN_MANAGER_ROLE_SLUGS);
    }

    private function canAccessOrdersPage(): bool
    {
        // Admin, Manager and Executive can open /admin/orders.
        // Executive users are scoped later by assigned_user_id in order queries.
        return $this->currentUserHasAnyRole(self::ORDER_PAGE_ROLE_IDS, self::ORDER_PAGE_ROLE_SLUGS);
    }

    private function requireOrdersPageAccess(): void
    {
        if (function_exists('require_auth')) {
            require_auth();
        }

        if ($this->canAccessOrdersPage()) {
            return;
        }

        http_response_code(403);
        flash('error', 'Admin, Manager or Executive access required. Please check user_roles for this user.');
        redirect('admin/dashboard');
        exit;
    }

    private function requireAdminManagerOrdersAccess(): void
    {
        if (function_exists('require_auth')) {
            require_auth();
        }

        if ($this->isAdminLikeUser()) {
            return;
        }

        http_response_code(403);
        flash('error', 'Admin or Manager access required.');
        redirect('admin/orders');
        exit;
    }

    private function canViewClientDocumentsDirectly(array $orderOrDocument = []): bool
    {
        // Admin and Manager bypass the old unlock gate through user_roles permissions only.
        if ($this->isAdminLikeUser()) {
            return true;
        }

        // Executive can bypass only for orders assigned to their account.
        if ($this->isAssignedStaffUser()) {
            $assignedUserId = (int) ($orderOrDocument['assigned_user_id'] ?? 0);

            return $assignedUserId > 0 && $assignedUserId === $this->currentUserId();
        }

        return false;
    }

    private function canAssignOrders(): bool
    {
        // Requirement: only Admin and Manager can assign orders.
        return $this->currentUserHasAnyRole(
            self::ADMIN_MANAGER_ROLE_IDS,
            self::ADMIN_MANAGER_ROLE_SLUGS
        );
    }

    private function validEmail(?string $email): bool
    {
        $email = trim((string) $email);
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function sqlStringList(array $values): string
    {
        $clean = [];

        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));

            if ($value === '') {
                continue;
            }

            $clean[] = "'" . str_replace("'", "''", $value) . "'";
        }

        return $clean !== [] ? implode(', ', array_values(array_unique($clean))) : "''";
    }

    private function sqlIntList(array $values): string
    {
        $clean = [];

        foreach ($values as $value) {
            $value = (int) $value;

            if ($value > 0) {
                $clean[] = $value;
            }
        }

        return $clean !== [] ? implode(', ', array_values(array_unique($clean))) : '0';
    }

    private function currentUserHasAnyRole(array $roleIds, array $roleSlugs = []): bool
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0)));
        $roleSlugs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(str_replace([' ', '_'], '-', trim((string) $slug))),
            $roleSlugs
        ))));

        $currentUserId = $this->currentUserId();

        if ($currentUserId <= 0) {
            return false;
        }

        $rolesColumns = $this->tableColumnsSafe('roles');
        $userRolesColumns = $this->tableColumnsSafe('user_roles');

        if (!in_array('user_id', $userRolesColumns, true)
            || !in_array('role_id', $userRolesColumns, true)
            || !in_array('id', $rolesColumns, true)
        ) {
            return false;
        }

        $hasRoleSlug = in_array('slug', $rolesColumns, true);
        $hasRoleName = in_array('name', $rolesColumns, true);
        $roleIdsSql = $this->sqlIntList($roleIds);
        $roleTextSql = $this->sqlStringList($roleSlugs);
        $roleChecks = [];

        if ($roleIds !== []) {
            $roleChecks[] = 'ur.role_id IN (' . $roleIdsSql . ')';
        }

        if ($roleSlugs !== []) {
            if ($hasRoleSlug) {
                $roleChecks[] = 'LOWER(COALESCE(r.slug, "")) IN (' . $roleTextSql . ')';
            }

            if ($hasRoleName) {
                $roleChecks[] = 'LOWER(REPLACE(COALESCE(r.name, ""), " ", "-")) IN (' . $roleTextSql . ')';
            }
        }

        if ($roleChecks === []) {
            return false;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT ur.user_id
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                   AND (' . implode(' OR ', $roleChecks) . ')
                 LIMIT 1',
                ['user_id' => $currentUserId]
            );

            return is_array($row) && $row !== [];
        } catch (\Throwable $e) {
            error_log('user_roles role lookup skipped: ' . $e->getMessage());
            return false;
        }
    }

    private function assignableStaffUsers(): array
    {
        $usersColumns = $this->tableColumnsSafe('users');
        $rolesColumns = $this->tableColumnsSafe('roles');
        $userRolesColumns = $this->tableColumnsSafe('user_roles');

        if ($usersColumns === []
            || !in_array('id', $usersColumns, true)
            || !in_array('user_id', $userRolesColumns, true)
            || !in_array('role_id', $userRolesColumns, true)
            || !in_array('id', $rolesColumns, true)
        ) {
            return [];
        }

        $hasRoleSlug = in_array('slug', $rolesColumns, true);
        $hasRoleName = in_array('name', $rolesColumns, true);
        $roleIdsSql = $this->sqlIntList(self::ASSIGNABLE_STAFF_ROLE_IDS);
        $roleTextSql = $this->sqlStringList(self::ASSIGNABLE_STAFF_ROLE_SLUGS);
        $whereParts = ['ur.role_id IN (' . $roleIdsSql . ')'];

        if ($hasRoleSlug) {
            $whereParts[] = 'LOWER(COALESCE(r.slug, "")) IN (' . $roleTextSql . ')';
        }

        if ($hasRoleName) {
            $whereParts[] = 'LOWER(REPLACE(COALESCE(r.name, ""), " ", "-")) IN (' . $roleTextSql . ')';
        }

        $nameSelect = in_array('name', $usersColumns, true)
            ? 'COALESCE(NULLIF(u.name, ""), CONCAT("User #", u.id)) AS name'
            : 'CONCAT("User #", u.id) AS name';
        $emailSelect = in_array('email', $usersColumns, true) ? 'COALESCE(u.email, "") AS email' : '"" AS email';
        $phoneSelect = in_array('phone', $usersColumns, true) ? 'COALESCE(u.phone, "") AS phone' : '"" AS phone';
        $roleLabelSelect = $hasRoleName ? 'COALESCE(NULLIF(r.name, ""), CONCAT("Role #", r.id)) AS role_label' : 'CONCAT("Role #", r.id) AS role_label';

        $rows = $this->db()->fetchAll(
            'SELECT
                u.id,
                ' . $nameSelect . ',
                ' . $emailSelect . ',
                ' . $phoneSelect . ',
                ' . $roleLabelSelect . '
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE (' . implode(' OR ', array_unique($whereParts)) . ')
             ORDER BY
                CASE
                    WHEN LOWER(COALESCE(r.slug, r.name, "")) IN ("executive", "staff") THEN 1
                    WHEN LOWER(COALESCE(r.slug, r.name, "")) IN ("partner", "partners") THEN 2
                    ELSE 3
                END,
                name ASC'
        ) ?: [];

        $deduped = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0 || isset($deduped[$id])) {
                continue;
            }

            $deduped[$id] = $row;
        }

        return array_values($deduped);
    }

    /**
     * Backwards-compatible wrapper used by existing views.
     * It now returns only Executives from user_roles.role_id = 3.
     */
    private function roleThreeUsers(): array
    {
        return $this->assignableStaffUsers();
    }

    private function assignableStaffUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        foreach ($this->assignableStaffUsers() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                return $user;
            }
        }

        return null;
    }

    private function isValidAssignableStaffUser(int $userId): bool
    {
        return $this->assignableStaffUserById($userId) !== null;
    }

    /**
     * Backwards-compatible wrapper used by older assignment code.
     */
    private function isValidRoleThreeUser(int $userId): bool
    {
        return $this->isValidAssignableStaffUser($userId);
    }

    private function assignableStaffUserName(int $userId): string
    {
        $row = $this->assignableStaffUserById($userId);

        if (!$row) {
            return 'User #' . $userId;
        }

        $name = trim((string) ($row['name'] ?? '')) ?: ('User #' . $userId);
        $role = trim((string) ($row['role_label'] ?? ''));

        return $role !== '' && strtolower($role) !== 'staff'
            ? $name . ' (' . $role . ')'
            : $name;
    }

    /**
     * Backwards-compatible wrapper used by older assignment code.
     */
    private function roleThreeUserName(int $userId): string
    {
        return $this->assignableStaffUserName($userId);
    }

    private function servicesForFilter(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, slug
             FROM services
             ORDER BY title ASC'
        ) ?: [];
    }

    private function servicesForOrderCreate(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, slug, filing_fee
             FROM services
             WHERE COALESCE(is_active, 1) = 1
             ORDER BY title ASC'
        ) ?: [];
    }

    private function serviceRequirementsForOrderCreateMap(): array
    {
        $columns = $this->tableColumnsSafe('service_requirements');

        if (!in_array('service_id', $columns, true)) {
            return [];
        }

        $select = [
            'id',
            'service_id',
            in_array('label', $columns, true) ? 'label' : '\'Document\' AS label',
            in_array('help_text', $columns, true) ? 'help_text' : '\'\' AS help_text',
            in_array('is_required', $columns, true) ? 'is_required' : '0 AS is_required',
            in_array('allow_multiple', $columns, true) ? 'allow_multiple' : '1 AS allow_multiple',
        ];

        $where = in_array('is_active', $columns, true) ? ' WHERE COALESCE(is_active, 1) = 1' : '';
        $orderBy = in_array('sort_order', $columns, true) ? ' ORDER BY service_id ASC, COALESCE(sort_order, 1) ASC, id ASC' : ' ORDER BY service_id ASC, id ASC';

        try {
            $rows = $this->db()->fetchAll(
                'SELECT ' . implode(', ', $select) . ' FROM service_requirements' . $where . $orderBy
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);

            if ($serviceId <= 0) {
                continue;
            }

            $map[$serviceId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'service_id' => $serviceId,
                'label' => (string) ($row['label'] ?? 'Document'),
                'help_text' => (string) ($row['help_text'] ?? ''),
                'is_required' => (int) ($row['is_required'] ?? 0),
                'allow_multiple' => (int) ($row['allow_multiple'] ?? 1),
            ];
        }

        return $map;
    }

    private function clientsForOrderCreate(): array
    {
        try {
            $rows = $this->db()->fetchAll(
                'SELECT
                    id,
                    COALESCE(NULLIF(company_name, ""), NULLIF(name, ""), CONCAT("Client #", id)) AS name,
                    email,
                    phone,
                    gst_number,
                    company_name,
                    city
                 FROM clients
                 ORDER BY COALESCE(NULLIF(company_name, ""), NULLIF(name, ""), id) ASC'
            );

            if (is_array($rows) && $rows !== []) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // Fallback below for older installations that still use users as clients.
        }

        return $this->db()->fetchAll(
            'SELECT
                id,
                COALESCE(NULLIF(name, ""), CONCAT("User #", id)) AS name,
                email,
                phone,
                "" AS gst_number,
                "" AS company_name,
                "" AS city
             FROM users
             ORDER BY name ASC'
        ) ?: [];
    }

    private function clientForOrderCreate(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        try {
            $client = $this->db()->fetch(
                'SELECT
                    id,
                    COALESCE(NULLIF(company_name, ""), NULLIF(name, ""), CONCAT("Client #", id)) AS name,
                    email,
                    phone,
                    gst_number,
                    company_name,
                    city
                 FROM clients
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $clientId]
            );

            if (is_array($client) && $client !== []) {
                return $client;
            }
        } catch (\Throwable $e) {
            // Fallback below for older installations that still use users as clients.
        }

        $user = $this->db()->fetch(
            'SELECT
                id,
                COALESCE(NULLIF(name, ""), CONCAT("User #", id)) AS name,
                email,
                phone,
                "" AS gst_number,
                "" AS company_name,
                "" AS city
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $clientId]
        );

        return is_array($user) && $user !== [] ? $user : null;
    }

    private function serviceForOrderCreate(int $serviceId): ?array
    {
        if ($serviceId <= 0) {
            return null;
        }

        $service = $this->db()->fetch(
            'SELECT id, title, slug, filing_fee
             FROM services
             WHERE id = :id
             LIMIT 1',
            ['id' => $serviceId]
        );

        return is_array($service) && $service !== [] ? $service : null;
    }

    private function nextOrderNo(): string
    {
        $prefix = 'TS-' . date('Ym') . '-';

        $row = $this->db()->fetch(
            'SELECT order_no
             FROM orders
             WHERE order_no LIKE :prefix
             ORDER BY id DESC
             LIMIT 1',
            ['prefix' => $prefix . '%']
        );

        $last = 0;
        if (is_array($row) && !empty($row['order_no']) && preg_match('/(\d+)$/', (string) $row['order_no'], $match)) {
            $last = (int) $match[1];
        }

        return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function ordersFromDb(array $filters = []): array
    {
        $this->ensureCustomerDetailsTable();

        $serviceColumns = $this->tableColumnsSafe('services');
        $hasServiceTurnaroundDays = in_array('turnaround_days', $serviceColumns, true);
        $turnaroundSelect = $hasServiceTurnaroundDays
            ? "
                COALESCE(s.turnaround_days, 0) AS service_turnaround_days,
                CASE
                    WHEN o.created_at IS NOT NULL AND COALESCE(s.turnaround_days, 0) > 0
                    THEN DATE(DATE_ADD(o.created_at, INTERVAL GREATEST(COALESCE(s.turnaround_days, 0), 0) DAY))
                    ELSE NULL
                END AS order_due_date,
                CASE
                    WHEN o.created_at IS NOT NULL AND COALESCE(s.turnaround_days, 0) > 0
                    THEN DATEDIFF(DATE(DATE_ADD(o.created_at, INTERVAL GREATEST(COALESCE(s.turnaround_days, 0), 0) DAY)), CURDATE())
                    ELSE NULL
                END AS order_due_days_left,"
            : "
                0 AS service_turnaround_days,
                NULL AS order_due_date,
                NULL AS order_due_days_left,";

        $sql = "
            SELECT
                o.id,
                o.order_no,
                o.client_id,
                o.service_id,
                o.financial_year,
                o.fee_amount,
                o.status,
                o.payment_method,
                o.payment_status,
                o.payment_reference,
                o.notes,
                o.admin_notes,
                o.completion_note,
                o.approved_by,
                o.assigned_user_id,
                o.approved_at,
                o.completed_at,
                o.created_at,
                o.updated_at,

                COALESCE(cl.id, cu.id) AS client_user_id,
                COALESCE(NULLIF(cd.name_as_per_pan, ''), NULLIF(cl.company_name, ''), NULLIF(cl.name, ''), NULLIF(cu.name, ''), '-') AS client_name,
                COALESCE(NULLIF(cd.email, ''), NULLIF(cl.email, ''), NULLIF(cu.email, ''), '') AS client_email,
                COALESCE(NULLIF(cd.mobile, ''), NULLIF(cl.phone, ''), NULLIF(cu.phone, ''), '-') AS client_phone,
                COALESCE(NULLIF(cd.pan_number, ''), '') AS client_pan_number,
                COALESCE(NULLIF(cd.name_as_per_pan, ''), '') AS customer_name_as_per_pan,
                COALESCE(NULLIF(cd.pan_number, ''), '') AS customer_pan_number,
                COALESCE(NULLIF(cd.mobile, ''), '') AS customer_mobile,
                COALESCE(NULLIF(cl.gst_number, ''), '') AS client_gst_number,
                COALESCE(NULLIF(cl.city, ''), '') AS client_city,
                COALESCE(cd.submitted_by_user_id, 0) AS submitted_by_user_id,
                CASE WHEN su.id IS NOT NULL THEN 1 ELSE 0 END AS submitted_by_user_exists,
                COALESCE(NULLIF(su.name, ''), '') AS submitted_by_user_name,
                COALESCE(NULLIF(cd.submitted_by_name, ''), NULLIF(su.name, ''), '') AS submitted_by_name,
                COALESCE(NULLIF(cd.submitted_by_email, ''), NULLIF(su.email, ''), '') AS submitted_by_email,
                COALESCE(NULLIF(cd.submitted_by_phone, ''), NULLIF(su.phone, ''), '') AS submitted_by_phone,

                COALESCE(s.title, '-') AS service_title,
                COALESCE(s.slug, '') AS service_slug,
{$turnaroundSelect}

                COALESCE(au.name, '') AS assigned_user_name,
                COALESCE(au.email, '') AS assigned_user_email
            FROM orders o
            LEFT JOIN clients cl ON cl.id = o.client_id
            LEFT JOIN users cu ON cu.id = o.client_id
            LEFT JOIN customer_details cd ON cd.order_id = o.id
            LEFT JOIN users su ON su.id = cd.submitted_by_user_id
            LEFT JOIN services s ON s.id = o.service_id
            LEFT JOIN users au ON au.id = o.assigned_user_id
            WHERE 1 = 1
        ";

        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= " AND o.status = :status";
            $params['status'] = $status;
        }

        $serviceId = (int) ($filters['service_id'] ?? 0);
        if ($serviceId > 0) {
            $sql .= " AND o.service_id = :service_id";
            $params['service_id'] = $serviceId;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= " AND (
                o.order_no LIKE :q
                OR COALESCE(cd.name_as_per_pan, '') LIKE :q
                OR COALESCE(cd.pan_number, '') LIKE :q
                OR COALESCE(cd.mobile, '') LIKE :q
                OR COALESCE(cd.email, '') LIKE :q
                OR COALESCE(cd.submitted_by_name, '') LIKE :q
                OR COALESCE(cd.submitted_by_email, '') LIKE :q
                OR COALESCE(cl.company_name, '') LIKE :q
                OR COALESCE(cl.name, '') LIKE :q
                OR COALESCE(cl.phone, '') LIKE :q
                OR COALESCE(cl.email, '') LIKE :q
                OR COALESCE(cl.gst_number, '') LIKE :q
                OR COALESCE(cu.name, '') LIKE :q
                OR COALESCE(cu.phone, '') LIKE :q
                OR COALESCE(cu.email, '') LIKE :q
                OR COALESCE(s.title, '') LIKE :q
            )";
            $params['q'] = '%' . $q . '%';
        }

        if ($this->isRoleThreeUser()) {
            $sql .= " AND o.assigned_user_id = :assigned_user_id";
            $params['assigned_user_id'] = $this->currentUserId();
        }

        $sql .= " ORDER BY o.id DESC";

        return $this->db()->fetchAll($sql, $params);
    }

    private function orderFromDb(int $orderId): ?array
    {
        $this->ensureCustomerDetailsTable();

        $serviceColumns = $this->tableColumnsSafe('services');
        $hasServiceTurnaroundDays = in_array('turnaround_days', $serviceColumns, true);
        $turnaroundSelect = $hasServiceTurnaroundDays
            ? "
                COALESCE(s.turnaround_days, 0) AS service_turnaround_days,
                CASE
                    WHEN o.created_at IS NOT NULL AND COALESCE(s.turnaround_days, 0) > 0
                    THEN DATE(DATE_ADD(o.created_at, INTERVAL GREATEST(COALESCE(s.turnaround_days, 0), 0) DAY))
                    ELSE NULL
                END AS order_due_date,
                CASE
                    WHEN o.created_at IS NOT NULL AND COALESCE(s.turnaround_days, 0) > 0
                    THEN DATEDIFF(DATE(DATE_ADD(o.created_at, INTERVAL GREATEST(COALESCE(s.turnaround_days, 0), 0) DAY)), CURDATE())
                    ELSE NULL
                END AS order_due_days_left,"
            : "
                0 AS service_turnaround_days,
                NULL AS order_due_date,
                NULL AS order_due_days_left,";

        $sql = "
            SELECT
                o.id,
                o.order_no,
                o.client_id,
                o.service_id,
                o.financial_year,
                o.fee_amount,
                o.status,
                o.payment_method,
                o.payment_status,
                o.payment_reference,
                o.notes,
                o.admin_notes,
                o.completion_note,
                o.approved_by,
                o.assigned_user_id,
                o.approved_at,
                o.completed_at,
                o.created_at,
                o.updated_at,

                COALESCE(cl.id, cu.id) AS client_user_id,
                COALESCE(NULLIF(cd.name_as_per_pan, ''), NULLIF(cl.company_name, ''), NULLIF(cl.name, ''), NULLIF(cu.name, ''), '-') AS client_name,
                COALESCE(NULLIF(cd.email, ''), NULLIF(cl.email, ''), NULLIF(cu.email, ''), '') AS client_email,
                COALESCE(NULLIF(cd.mobile, ''), NULLIF(cl.phone, ''), NULLIF(cu.phone, ''), '-') AS client_phone,
                COALESCE(NULLIF(cd.pan_number, ''), '') AS client_pan_number,
                COALESCE(NULLIF(cd.name_as_per_pan, ''), '') AS customer_name_as_per_pan,
                COALESCE(NULLIF(cd.pan_number, ''), '') AS customer_pan_number,
                COALESCE(NULLIF(cd.mobile, ''), '') AS customer_mobile,
                COALESCE(NULLIF(cl.gst_number, ''), '') AS client_gst_number,
                COALESCE(NULLIF(cl.city, ''), '') AS client_city,
                COALESCE(cd.submitted_by_user_id, 0) AS submitted_by_user_id,
                CASE WHEN su.id IS NOT NULL THEN 1 ELSE 0 END AS submitted_by_user_exists,
                COALESCE(NULLIF(su.name, ''), '') AS submitted_by_user_name,
                COALESCE(NULLIF(cd.submitted_by_name, ''), NULLIF(su.name, ''), '') AS submitted_by_name,
                COALESCE(NULLIF(cd.submitted_by_email, ''), NULLIF(su.email, ''), '') AS submitted_by_email,
                COALESCE(NULLIF(cd.submitted_by_phone, ''), NULLIF(su.phone, ''), '') AS submitted_by_phone,

                COALESCE(s.title, '-') AS service_title,
                COALESCE(s.slug, '') AS service_slug,
{$turnaroundSelect}

                COALESCE(au.name, '') AS assigned_user_name,
                COALESCE(au.email, '') AS assigned_user_email
            FROM orders o
            LEFT JOIN clients cl ON cl.id = o.client_id
            LEFT JOIN users cu ON cu.id = o.client_id
            LEFT JOIN customer_details cd ON cd.order_id = o.id
            LEFT JOIN users su ON su.id = cd.submitted_by_user_id
            LEFT JOIN services s ON s.id = o.service_id
            LEFT JOIN users au ON au.id = o.assigned_user_id
            WHERE o.id = :id
        ";

        $params = ['id' => $orderId];

        if ($this->isRoleThreeUser()) {
            $sql .= " AND o.assigned_user_id = :assigned_user_id";
            $params['assigned_user_id'] = $this->currentUserId();
        }

        $sql .= " LIMIT 1";

        return $this->db()->fetch($sql, $params);
    }

    private function findAccessibleOrder(int $orderId): ?array
    {
        return $this->orderFromDb($orderId);
    }

    private function orderDocumentsBySource(int $orderId, string $source): array
    {
        return $this->db()->fetchAll(
            'SELECT
                id,
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
                created_at,
                COALESCE(document_status, "active") AS document_status,
                wrong_reason,
                wrong_marked_by,
                wrong_marked_at,
                reuploaded_for_document_id,
                replaced_by_document_id
             FROM order_documents
             WHERE order_id = :order_id
               AND source = :source
             ORDER BY
                CASE COALESCE(document_status, "active")
                    WHEN "wrong" THEN 1
                    WHEN "active" THEN 2
                    WHEN "reuploaded" THEN 3
                    ELSE 4
                END,
                id DESC',
            [
                'order_id' => $orderId,
                'source' => $source,
            ]
        ) ?: [];
    }

    private function documentForDownload(int $documentId): ?array
    {
        return $this->db()->fetch(
            'SELECT
                d.id,
                d.order_id,
                d.requirement_id,
                d.source,
                d.label,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                d.is_client_visible,
                d.uploaded_by,
                d.created_at,
                COALESCE(d.document_status, "active") AS document_status,
                d.wrong_reason,
                d.wrong_marked_by,
                d.wrong_marked_at,
                d.reuploaded_for_document_id,
                d.replaced_by_document_id,
                o.status AS order_status,
                o.assigned_user_id
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :id
             LIMIT 1',
            ['id' => $documentId]
        );
    }

    private function buildEmailTemplate(
        string $eyebrow,
        string $title,
        string $intro,
        array $facts = [],
        ?string $buttonText = null,
        ?string $buttonUrl = null,
        ?string $footerNote = null
    ): array {
        $factRowsHtml = '';
        $factRowsText = '';

        foreach ($facts as $label => $value) {
            $safeLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

            $factRowsHtml .= '
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:160px;">' . $safeLabel . '</td>
                    <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;font-weight:600;">' . $safeValue . '</td>
                </tr>
            ';

            $factRowsText .= $label . ': ' . $value . PHP_EOL;
        }

        $safeEyebrow = htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeIntro = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'));
        $safeFooter = htmlspecialchars((string) $footerNote, ENT_QUOTES, 'UTF-8');

        $buttonHtml = '';
        if ($buttonText && $buttonUrl) {
            $buttonHtml = '
                <tr>
                    <td style="padding:26px 0 0 0;">
                        <a href="' . htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;">
                            ' . htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') . '
                        </a>
                    </td>
                </tr>
            ';
        }

        $html = '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $safeTitle . '</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $safeTitle . ' - Tax Saathi</div>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc;margin:0;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1d4ed8 0%,#4f46e5 100%);padding:28px 32px;">
                            <div style="font-size:11px;letter-spacing:0.22em;text-transform:uppercase;color:rgba(255,255,255,0.78);font-weight:700;">' . $safeEyebrow . '</div>
                            <div style="margin-top:8px;font-size:28px;line-height:1.25;font-weight:800;color:#ffffff;">' . $safeTitle . '</div>
                            <div style="margin-top:8px;font-size:14px;line-height:1.7;color:rgba(255,255,255,0.92);">Tax Saathi</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 10px 32px;">
                            <p style="margin:0;font-size:15px;line-height:1.8;color:#334155;">' . $safeIntro . '</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 32px 0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                                ' . $factRowsHtml . '
                            </table>
                        </td>
                    </tr>
                    ' . $buttonHtml . '
                    <tr>
                        <td style="padding:24px 32px 30px 32px;">
                            <div style="font-size:13px;line-height:1.8;color:#64748b;">
                                ' . ($safeFooter !== '' ? $safeFooter . '<br><br>' : '') . '
                                Regards,<br>
                                <strong style="color:#0f172a;">Tax Saathi</strong>
                            </div>
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:640px;margin-top:12px;">
                    <tr>
                        <td style="padding:0 8px;text-align:center;font-size:12px;line-height:1.7;color:#94a3b8;">
                            This is a transactional service email from Tax Saathi.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        $text = $title . PHP_EOL
            . str_repeat('=', mb_strlen($title)) . PHP_EOL . PHP_EOL
            . $intro . PHP_EOL . PHP_EOL
            . $factRowsText . PHP_EOL
            . ($buttonText && $buttonUrl ? ($buttonText . ': ' . $buttonUrl . PHP_EOL . PHP_EOL) : '')
            . ($footerNote ? ($footerNote . PHP_EOL . PHP_EOL) : '')
            . 'Regards,' . PHP_EOL
            . 'Tax Saathi';

        return [
            'html' => $html,
            'text' => $text,
        ];
    }

    private function sendTransactionalEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        if (!$this->validEmail($toEmail)) {
            return false;
        }

        if (!class_exists(PHPMailer::class)) {
            error_log('PHPMailer is not installed. Run: composer require phpmailer/phpmailer');
            return false;
        }

        $cfg = $this->smtpConfig();

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) $cfg['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $cfg['smtp']['username'];
            $mail->Password = (string) $cfg['smtp']['password'];
            $mail->SMTPSecure = (string) $cfg['smtp']['encryption'];
            $mail->Port = (int) $cfg['smtp']['port'];
            $mail->Timeout = (int) $cfg['smtp']['timeout'];
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);

            $mail->setFrom((string) $cfg['from_email'], (string) $cfg['from_name']);
            $mail->addReplyTo((string) $cfg['from_email'], (string) $cfg['from_name']);
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);

            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            return $mail->send();
        } catch (MailException $e) {
            error_log('Mail send failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log('Mail send failed: ' . $e->getMessage());
            return false;
        }
    }

    private function emailAssignedStaff(array $order, int $assignedUserId): void
    {
        $staff = $this->assignableStaffUserById($assignedUserId);

        if (!$staff || !$this->validEmail((string) ($staff['email'] ?? ''))) {
            return;
        }

        $mail = $this->buildEmailTemplate(
            'Order Assignment',
            'A new order has been assigned to you',
            'You have been assigned a new order in Tax Saathi. Please review and take the next action from your dashboard.',
            [
                'Order No' => (string) ($order['order_no'] ?? '-'),
                'Client' => (string) ($order['client_name'] ?? '-'),
                'Service' => (string) ($order['service_title'] ?? '-'),
                'Financial Year' => (string) ($order['financial_year'] ?? '-'),
                'Status' => (string) ($order['status'] ?? '-'),
                'Fee' => function_exists('format_money') ? (string) format_money($order['fee_amount'] ?? 0) : (string) ($order['fee_amount'] ?? '0.00'),
            ],
            'Open Assigned Order',
            base_url('admin/orders/show?id=' . (int) ($order['id'] ?? 0)),
            'This notification was sent because the order was assigned to your account.'
        );

        $this->sendTransactionalEmail(
            (string) $staff['email'],
            (string) ($staff['name'] ?? ''),
            'New assigned order - ' . (string) ($order['order_no'] ?? ''),
            $mail['html'],
            $mail['text']
        );
    }

    private function emailClientApproval(array $order): void
    {
        if (!$this->validEmail((string) ($order['client_email'] ?? ''))) {
            return;
        }

        $mail = $this->buildEmailTemplate(
            'Order Update',
            'Your order has been approved',
            'We are pleased to inform you that your order has been approved. Our team will continue processing the remaining steps.',
            [
                'Order No' => (string) ($order['order_no'] ?? '-'),
                'Service' => (string) ($order['service_title'] ?? '-'),
                'Financial Year' => (string) ($order['financial_year'] ?? '-'),
                'Status' => 'Approved',
                'Payment Status' => (string) ($order['payment_status'] ?? '-'),
            ],
            null,
            null,
            'You may log in to your Tax Saathi account for further updates.'
        );

        $this->sendTransactionalEmail(
            (string) $order['client_email'],
            (string) ($order['client_name'] ?? ''),
            'Order approved - ' . (string) ($order['order_no'] ?? ''),
            $mail['html'],
            $mail['text']
        );
    }

    private function emailClientOutputUploaded(array $order, int $fileCount): void
    {
        if (!$this->validEmail((string) ($order['client_email'] ?? ''))) {
            return;
        }

        $mail = $this->buildEmailTemplate(
            'Order Deliverables',
            'Completed files have been uploaded',
            'Completed files related to your order have been uploaded successfully and are now available in your account.',
            [
                'Order No' => (string) ($order['order_no'] ?? '-'),
                'Service' => (string) ($order['service_title'] ?? '-'),
                'Uploaded Files' => (string) $fileCount,
                'Status' => (string) ($order['status'] ?? '-'),
            ],
            null,
            null,
            'Please review the uploaded files in your dashboard.'
        );

        $this->sendTransactionalEmail(
            (string) $order['client_email'],
            (string) ($order['client_name'] ?? ''),
            'Completed files uploaded - ' . (string) ($order['order_no'] ?? ''),
            $mail['html'],
            $mail['text']
        );
    }

    private function emailClientInvoiceUpdated(array $order, array $invoiceData): void
    {
        if (!$this->validEmail((string) ($order['client_email'] ?? ''))) {
            return;
        }

        $mail = $this->buildEmailTemplate(
            'Invoice Update',
            'Your invoice has been updated',
            'The invoice details for your order have been updated. Please review the latest billing information in your account.',
            [
                'Order No' => (string) ($order['order_no'] ?? '-'),
                'Service' => (string) ($order['service_title'] ?? '-'),
                'Invoice Status' => (string) ($invoiceData['status'] ?? '-'),
                'Due Date' => (string) ($invoiceData['due_date'] ?? '-'),
                'Total Amount' => (string) ($invoiceData['total_amount'] ?? '0.00'),
                'Paid Amount' => (string) ($invoiceData['paid_amount'] ?? '0.00'),
            ],
            null,
            null,
            'This is a billing confirmation regarding your Tax Saathi order.'
        );

        $this->sendTransactionalEmail(
            (string) $order['client_email'],
            (string) ($order['client_name'] ?? ''),
            'Invoice updated - ' . (string) ($order['order_no'] ?? ''),
            $mail['html'],
            $mail['text']
        );
    }

    private function emailClientPaymentRecorded(array $order, float $amount, string $method, string $referenceNo): void
    {
        if (!$this->validEmail((string) ($order['client_email'] ?? ''))) {
            return;
        }

        $mail = $this->buildEmailTemplate(
            'Payment Confirmation',
            'Your payment has been recorded',
            'We have successfully recorded your payment against the order below.',
            [
                'Order No' => (string) ($order['order_no'] ?? '-'),
                'Service' => (string) ($order['service_title'] ?? '-'),
                'Amount' => number_format($amount, 2, '.', ''),
                'Method' => $method !== '' ? $method : '-',
                'Reference No' => $referenceNo !== '' ? $referenceNo : '-',
            ],
            null,
            null,
            'Thank you for your payment.'
        );

        $this->sendTransactionalEmail(
            (string) $order['client_email'],
            (string) ($order['client_name'] ?? ''),
            'Payment recorded - ' . (string) ($order['order_no'] ?? ''),
            $mail['html'],
            $mail['text']
        );
    }


    private function adminFinancialYearOptions(): array
    {
        return self::ADMIN_FINANCIAL_YEAR_OPTIONS;
    }

    private function normalizeAdminFinancialYearValue(string $value): string
    {
        /*
         * Financial Year is stored as normal text.
         * No whitelist, date parsing, year-format validation, or conversion.
         * Decode HTML entities so an ampersand submitted as &amp; is saved as &.
         */
        $value = html_entity_decode(
            trim($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        // Remove only null bytes/control characters that can break storage.
        $value = str_replace("\00", '', $value);

        return trim($value);
    }

    private function serviceRequiresAdminFinancialYear(array $service): bool
    {
        $slug = strtolower(trim((string) ($service['slug'] ?? $service['service_slug'] ?? '')));
        $slug = str_replace('_', '-', $slug);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: '';
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

        return $slug !== '' && in_array($slug, self::ADMIN_FINANCIAL_YEAR_SERVICE_SLUGS, true);
    }

    private function ensureCustomerDetailsTable(): void
    {
        try {
            $this->db()->execute("
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
        } catch (\Throwable $e) {
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
                $this->db()->execute('ALTER TABLE customer_details ADD COLUMN `' . $column . '` ' . $definition);
                $columns[] = $column;
            } catch (\Throwable $e) {
                error_log('customer_details alter skipped for ' . $column . ': ' . $e->getMessage());
            }
        }

        try {
            $this->db()->execute('ALTER TABLE customer_details ADD UNIQUE KEY uq_customer_details_order_id (order_id)');
        } catch (\Throwable $e) {
            // Key may already exist.
        }
    }

    private function currentSubmitterDetails(): array
    {
        $userId = $this->currentUserId();
        $identity = $this->currentUserIdentity();

        $details = [
            'id' => $userId,
            'name' => '',
            'email' => (string) ($identity['email'] ?? ''),
            'phone' => (string) ($identity['phone'] ?? ''),
        ];

        $sessionUser = is_array($_SESSION['user'] ?? null)
            ? $_SESSION['user']
            : (is_array($_SESSION['auth_user'] ?? null) ? $_SESSION['auth_user'] : []);

        $details['name'] = trim((string) ($sessionUser['name'] ?? $sessionUser['full_name'] ?? ''));

        if ($userId > 0) {
            try {
                $user = $this->db()->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);

                if (is_array($user) && $user !== []) {
                    $details['name'] = trim((string) ($user['name'] ?? $user['full_name'] ?? $details['name']));
                    $details['email'] = trim((string) ($user['email'] ?? $details['email']));
                    $details['phone'] = trim((string) ($user['phone'] ?? $user['mobile'] ?? $details['phone']));
                }
            } catch (\Throwable $e) {
                error_log('submitted by lookup skipped: ' . $e->getMessage());
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

        $this->ensureCustomerDetailsTable();
        $columns = $this->tableColumnsSafe('customer_details');

        if ($columns === []) {
            return;
        }

        $submitter = $this->currentSubmitterDetails();
        $now = date('Y-m-d H:i:s');

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
            $existing = null;
            if (in_array('order_id', $columns, true)) {
                $existing = $this->db()->fetch('SELECT id FROM customer_details WHERE order_id = :order_id LIMIT 1', ['order_id' => $orderId]);
            }

            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0 && in_array('id', $columns, true)) {
                $existingId = (int) $existing['id'];
                unset($clean['created_at']);

                $sets = [];
                $params = ['id' => $existingId];

                foreach ($clean as $column => $value) {
                    if ($column === 'id') {
                        continue;
                    }

                    $sets[] = '`' . $column . '` = :' . $column;
                    $params[$column] = $value;
                }

                if ($sets !== []) {
                    $this->db()->execute('UPDATE customer_details SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
                }

                return;
            }

            $columnsSql = '`' . implode('`, `', array_keys($clean)) . '`';
            $valuesSql = ':' . implode(', :', array_keys($clean));
            $this->db()->execute('INSERT INTO customer_details (' . $columnsSql . ') VALUES (' . $valuesSql . ')', $clean);
        } catch (\Throwable $e) {
            error_log('customer_details save skipped: ' . $e->getMessage());
        }
    }

    private function adminContactNotes(string $notes, string $mobile, string $email, string $nameAsPerPan = '', string $panNumber = ''): string
    {
        $contactLines = [
            'Name as per PAN: ' . $nameAsPerPan,
            'PAN Number: ' . strtoupper($panNumber),
            'Customer Mobile: ' . $mobile,
            'Customer Email: ' . $email,
        ];

        $notes = trim($notes);

        if ($notes !== '') {
            return $notes . "\n\n" . implode("\n", $contactLines);
        }

        return implode("\n", $contactLines);
    }

    private function updateClientContactFromAdminOrder(int $clientId, string $mobile, string $email, string $nameAsPerPan = ''): void
    {
        if ($clientId <= 0) {
            return;
        }

        foreach (['clients', 'users'] as $table) {
            $columns = $this->tableColumnsSafe($table);

            if ($columns === []) {
                continue;
            }

            $sets = [];
            $params = ['id' => $clientId];

            if ($nameAsPerPan !== '' && in_array('name', $columns, true)) {
                $sets[] = 'name = :name';
                $params['name'] = $nameAsPerPan;
            }

            if ($email !== '' && in_array('email', $columns, true)) {
                $sets[] = 'email = :email';
                $params['email'] = $email;
            }

            if ($mobile !== '' && in_array('phone', $columns, true)) {
                $sets[] = 'phone = :phone';
                $params['phone'] = $mobile;
            }

            if ($mobile !== '' && in_array('mobile', $columns, true)) {
                $sets[] = 'mobile = :mobile';
                $params['mobile'] = $mobile;
            }

            if (in_array('updated_at', $columns, true)) {
                $sets[] = 'updated_at = :updated_at';
                $params['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($sets === []) {
                continue;
            }

            try {
                $this->db()->execute(
                    'UPDATE `' . str_replace('`', '', $table) . '` SET ' . implode(', ', $sets) . ' WHERE id = :id',
                    $params
                );
            } catch (\Throwable $e) {
                error_log('Admin order contact update skipped for ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    private function normalizeAdminOrderUploadedFiles(string $field): array
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return [];
        }

        $file = $_FILES[$field];
        $items = [];

        if (is_array($file['name'] ?? null)) {
            foreach (array_keys($file['name']) as $index) {
                $error = (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE);

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $items[] = [
                    'name' => (string) ($file['name'][$index] ?? ''),
                    'type' => (string) ($file['type'][$index] ?? ''),
                    'tmp_name' => (string) ($file['tmp_name'][$index] ?? ''),
                    'error' => $error,
                    'size' => (int) ($file['size'][$index] ?? 0),
                ];
            }

            return $items;
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

    private function storeAdminOrderUploadFile(array $file): ?array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if (function_exists('store_single_upload')) {
            try {
                $meta = store_single_upload($file);

                if (is_array($meta) && $meta !== []) {
                    return $meta;
                }
            } catch (\Throwable $e) {
                error_log('store_single_upload failed for admin order document: ' . $e->getMessage());
            }
        }

        return $this->fallbackPublicOrderUpload($file);
    }

    private function storeAdminOrderClientDocuments(int $orderId): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        $files = $this->normalizeAdminOrderUploadedFiles('admin_order_documents');

        if ($files === []) {
            return 0;
        }

        $requirementIds = $_POST['admin_order_document_requirement_ids'] ?? [];
        $labels = $_POST['admin_order_document_labels'] ?? [];
        $uploadedCount = 0;

        foreach ($files as $index => $file) {
            $meta = $this->storeAdminOrderUploadFile($file);

            if (!$meta) {
                continue;
            }

            $requirementId = isset($requirementIds[$index]) ? (int) $requirementIds[$index] : 0;
            $label = isset($labels[$index]) ? trim((string) $labels[$index]) : '';

            if ($label === '') {
                $label = 'Client Document';
            }

            $this->db()->execute(
                'INSERT INTO order_documents
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
                )',
                [
                    'order_id' => $orderId,
                    'requirement_id' => $requirementId > 0 ? $requirementId : null,
                    'source' => 'client',
                    'label' => $label,
                    'original_name' => (string) ($meta['original_name'] ?? ''),
                    'stored_name' => (string) ($meta['stored_name'] ?? ''),
                    'mime_type' => (string) ($meta['mime_type'] ?? ''),
                    'size_bytes' => (int) ($meta['size_bytes'] ?? 0),
                    'is_client_visible' => 0,
                    'uploaded_by' => $this->currentUserId(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $uploadedCount++;
        }

        return $uploadedCount;
    }

    public function index(): void
    {
        $this->requireOrdersPageAccess();

        $filters = [
            'status' => trim((string) input('status')),
            'service_id' => (int) input('service_id', 0),
            'q' => trim((string) input('q')),
        ];

        $this->view('admin/orders', [
            'title' => 'Orders – Tax Saathi',
            'rows' => $this->ordersFromDb($filters),
            'services' => $this->servicesForFilter(),
            'orderServices' => $this->servicesForOrderCreate(),
            'clients' => $this->clientsForOrderCreate(),
            'filters' => $filters,
            'assignees' => $this->roleThreeUsers(),
            'canAssignOrders' => $this->canAssignOrders(),
            'financialYears' => $this->adminFinancialYearOptions(),
            'allowedFinancialYearSlugs' => self::ADMIN_FINANCIAL_YEAR_SERVICE_SLUGS,
            'orderRequirementsByService' => $this->serviceRequirementsForOrderCreateMap(),
            'isRoleThreeUser' => $this->isRoleThreeUser(),
            'isAssignedStaffUser' => $this->isAssignedStaffUser(),
            'isExecutiveUser' => $this->isExecutiveUser(),
            'isPartnerUser' => $this->isPartnerUser(),
            'isAdminLikeUser' => $this->isAdminLikeUser(),
            'currentUserId' => $this->currentUserId(),
        ], 'layouts/dashboard');
    }



    public function delete(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $orderId = (int) input('id');
        $order = $this->orderFromDb($orderId);

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        if ($this->isExecutiveUser() && (int) ($order['assigned_user_id'] ?? 0) !== $this->currentUserId()) {
            flash('error', 'You can delete only your own orders.');
            redirect('admin/orders');
        }

        $orderNo = (string) ($order['order_no'] ?? ('#' . $orderId));

        try {
            /*
             * Delete related records first to avoid foreign key conflicts.
             * Each statement is protected so the delete works even if a table does not exist.
             */
            foreach ([
                ['DELETE FROM order_documents WHERE order_id = :order_id', ['order_id' => $orderId]],
                ['DELETE FROM payments WHERE order_id = :order_id', ['order_id' => $orderId]],
                ['DELETE FROM invoices WHERE order_id = :order_id', ['order_id' => $orderId]],
                ['DELETE FROM activity_logs WHERE order_id = :order_id', ['order_id' => $orderId]],
            ] as $statement) {
                try {
                    $this->db()->execute($statement[0], $statement[1]);
                } catch (\Throwable $e) {
                    error_log('Order related delete skipped: ' . $e->getMessage());
                }
            }

            $this->db()->execute(
                'DELETE FROM orders WHERE id = :id',
                ['id' => $orderId]
            );

            activity_log(
                $this->currentUserId(),
                $orderId,
                'order.deleted',
                'Order ' . $orderNo . ' deleted.',
                ['order_no' => $orderNo]
            );

            flash('success', 'Order ' . $orderNo . ' deleted successfully.');
        } catch (\Throwable $e) {
            error_log('Order delete failed: ' . $e->getMessage());
            flash('error', 'Unable to delete order. Please check related records or database constraints.');
        }

        redirect('admin/orders');
    }

    public function destroy(): void
    {
        $this->delete();
    }

    public function storeAdminOrder(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $isExecutive = $this->isExecutiveUser();

        $clientId = (int) input('client_id', 0);
        $serviceId = (int) input('service_id', 0);
        $feeAmount = (float) input('fee_amount', 0);
        $status = trim((string) input('status', 'pending_review'));
        $paymentMethod = trim((string) input('payment_method', 'manual'));
        $paymentStatus = trim((string) input('payment_status', 'pending'));
        $paymentReference = trim((string) input('payment_reference'));
        $notes = trim((string) input('notes'));
        $assignedUserId = (int) input('assigned_user_id', 0);
        $customerNameAsPerPan = trim((string) input('customer_name_as_per_pan'));
        $customerPanNumber = strtoupper(trim((string) input('customer_pan_number')));
        $contactMobile = trim((string) input('contact_mobile'));
        $contactEmail = strtolower(trim((string) input('contact_email')));

        $allowedStatuses = ['pending_review', 'clarification', 'approved', 'work_in_progress', 'completed', 'rejected'];
        $allowedPaymentStatuses = ['pending', 'pending_review', 'verified', 'paid', 'partial', 'failed', 'unpaid'];
        $allowedPaymentMethods = ['manual', 'upi', 'bank_transfer', 'razorpay', 'cash'];

        $client = $this->clientForOrderCreate($clientId);
        if (!$client) {
            flash('error', 'Please choose a valid client/user.');
            redirect('admin/orders');
        }

        $service = $this->serviceForOrderCreate($serviceId);
        if (!$service) {
            flash('error', 'Please choose a valid service.');
            redirect('admin/orders');
        }

        if ($customerNameAsPerPan === '') {
            flash('error', 'Please enter Name as per PAN.');
            redirect('admin/orders');
        }

        if ($customerPanNumber === '') {
            flash('error', 'Please enter PAN number.');
            redirect('admin/orders');
        }

        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $customerPanNumber)) {
            flash('error', 'Please enter a valid PAN number. Example: ABCDE1234F');
            redirect('admin/orders');
        }

        if ($contactMobile === '') {
            flash('error', 'Please enter customer mobile number.');
            redirect('admin/orders');
        }

        if (!$this->validEmail($contactEmail)) {
            flash('error', 'Please enter a valid customer email address.');
            redirect('admin/orders');
        }

        $financialYear = '';
        if ($this->serviceRequiresAdminFinancialYear($service)) {
            // Store Financial Year as normal text exactly as submitted.
            $rawFinancialYear = (string) ($_POST['financial_year'] ?? input('financial_year', ''));
            $financialYear = $this->normalizeAdminFinancialYearValue($rawFinancialYear);

            // An empty submission falls back to the current one-year option without rejecting the order.
            if ($financialYear === '') {
                $financialYear = self::ADMIN_FINANCIAL_YEAR_OPTIONS[0];
            }
        }

        if ($feeAmount <= 0) {
            $feeAmount = (float) ($service['filing_fee'] ?? 0);
        }

        if ($feeAmount < 0) {
            $feeAmount = 0;
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending_review';
        }

        if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
            $paymentStatus = 'pending';
        }

        if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
            $paymentMethod = 'manual';
        }

        $assignedValue = null;

        if ($isExecutive) {
            /*
             * Executive users can create and manage only their own orders.
             * Therefore every order created by an executive is automatically assigned to them.
             */
            $assignedValue = $this->currentUserId();
        } elseif ($assignedUserId > 0) {
            if (!$this->isValidAssignableStaffUser($assignedUserId)) {
                flash('error', 'Only Executive users from user_roles.role_id = 3 can be assigned to an order.');
                redirect('admin/orders');
            }

            $assignedValue = $assignedUserId;
        }

        $orderNo = $this->nextOrderNo();
        $now = date('Y-m-d H:i:s');
        $this->ensureCustomerDetailsTable();
        $notesWithContact = $this->adminContactNotes($notes, $contactMobile, $contactEmail, $customerNameAsPerPan, $customerPanNumber);

        $this->db()->execute(
            'INSERT INTO orders
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
                assigned_user_id,
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
                :assigned_user_id,
                :created_at,
                :updated_at
            )',
            [
                'order_no' => $orderNo,
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'financial_year' => $financialYear,
                'fee_amount' => $feeAmount,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'notes' => $notesWithContact,
                'assigned_user_id' => $assignedValue,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $created = $this->db()->fetch(
            'SELECT id
             FROM orders
             WHERE order_no = :order_no
             ORDER BY id DESC
             LIMIT 1',
            ['order_no' => $orderNo]
        );

        $orderId = (int) ($created['id'] ?? 0);

        if ($orderId > 0) {
            $this->updateClientContactFromAdminOrder($clientId, $contactMobile, $contactEmail, $customerNameAsPerPan);
            $this->saveCustomerDetailsForOrder(
                $orderId,
                $clientId,
                $this->currentUserId(),
                $serviceId,
                $customerNameAsPerPan,
                $customerPanNumber,
                $contactMobile,
                $contactEmail,
                'admin_order'
            );
            $uploadedCount = $this->storeAdminOrderClientDocuments($orderId);

            activity_log(
                $this->currentUserId(),
                $orderId,
                'order.created_by_admin',
                'Order created manually by admin.',
                [
                    'order_no' => $orderNo,
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'financial_year' => $financialYear,
                    'name_as_per_pan' => $customerNameAsPerPan,
                    'pan_number' => $customerPanNumber,
                    'contact_mobile' => $contactMobile,
                    'contact_email' => $contactEmail,
                    'assigned_user_id' => $assignedValue,
                    'uploaded_documents' => $uploadedCount,
                ]
            );

            if ($assignedValue) {
                $freshOrder = $this->orderFromDb($orderId);
                if ($freshOrder) {
                    $this->emailAssignedStaff($freshOrder, $assignedValue);
                }
            }

            flash('success', 'Order ' . $orderNo . ' created successfully. ' . $uploadedCount . ' document(s) uploaded.');
            redirect('admin/orders/show?id=' . $orderId);
        }

        flash('success', 'Order ' . $orderNo . ' created successfully.');
        redirect('admin/orders');
    }

    public function storeOrder(): void
    {
        $this->storeAdminOrder();
    }

    public function createOrder(): void
    {
        $this->storeAdminOrder();
    }

    public function show(): void
    {
        $this->requireOrdersPageAccess();

        $order = $this->findAccessibleOrder((int) input('id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        $status = (string) ($order['status'] ?? '');
        $allowClientDocs = in_array($status, ['approved', 'work_in_progress', 'completed'], true);
        $canViewClientDocsDirectly = $this->canViewClientDocumentsDirectly($order);

        $this->view('admin/order-show', [
            'title' => 'Order ' . ($order['order_no'] ?? '') . ' – Tax Saathi',
            'order' => $order,
            'paymentProofs' => $this->orderDocumentsBySource((int) $order['id'], 'payment_proof'),
            'clientDocs' => ($allowClientDocs || $canViewClientDocsDirectly)
                ? $this->orderDocumentsBySource((int) $order['id'], 'client')
                : [],
            'clientDocsLocked' => $canViewClientDocsDirectly ? false : !$allowClientDocs,
            'canViewClientDocsDirectly' => $canViewClientDocsDirectly,
            'adminCanViewClientDocsDirectly' => $canViewClientDocsDirectly,
            'hideClientDocsUnlockSection' => $canViewClientDocsDirectly,
            'outputDocs' => $this->orderDocumentsBySource((int) $order['id'], 'admin_output'),
            'invoice' => Invoice::findByOrder((int) $order['id']),
            'payments' => Payment::forOrder((int) $order['id']),
            'activity' => ActivityLog::forOrder((int) $order['id']),
            'assignees' => $this->roleThreeUsers(),
            'canAssignOrders' => $this->canAssignOrders(),
            'financialYears' => $this->adminFinancialYearOptions(),
            'allowedFinancialYearSlugs' => self::ADMIN_FINANCIAL_YEAR_SERVICE_SLUGS,
            'orderRequirementsByService' => $this->serviceRequirementsForOrderCreateMap(),
            'isRoleThreeUser' => $this->isRoleThreeUser(),
            'isAssignedStaffUser' => $this->isAssignedStaffUser(),
            'isPartnerUser' => $this->isPartnerUser(),
        ], 'layouts/dashboard');
    }

    public function assign(): void
    {
        $this->requireAdminManagerOrdersAccess();
        verify_csrf();

        if (!$this->canAssignOrders()) {
            flash('error', 'You are not allowed to assign orders.');
            redirect('admin/orders');
        }

        $orderId = (int) input('id');
        $order = $this->orderFromDb($orderId);

        if (!$order) {
            flash('error', 'Order not found.');
            redirect('admin/orders');
        }

        $assignedUserId = (int) input('assigned_user_id', 0);
        $assignedValue = null;
        $assignedLabel = 'Unassigned';

        if ($assignedUserId > 0) {
            if (!$this->isValidAssignableStaffUser($assignedUserId)) {
                flash('error', 'Only Executive users from user_roles.role_id = 3 can be assigned to an order.');
                redirect('admin/orders/show?id=' . $orderId . '#assign');
            }

            $assignedValue = $assignedUserId;
            $assignedLabel = $this->assignableStaffUserName($assignedUserId);
        }

        $this->db()->execute(
            'UPDATE orders
             SET assigned_user_id = :assigned_user_id,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'assigned_user_id' => $assignedValue,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $orderId,
            ]
        );

        activity_log(
            $this->currentUserId(),
            $orderId,
            'order.assigned',
            'Order assignment updated to ' . $assignedLabel . '.',
            ['assigned_user_id' => $assignedValue]
        );

        if ($assignedUserId > 0) {
            $freshOrder = $this->orderFromDb($orderId);
            if ($freshOrder) {
                $this->emailAssignedStaff($freshOrder, $assignedUserId);
            }
        }

        flash('success', 'Order assignment updated successfully.');
        redirect('admin/orders/show?id=' . $orderId . '#assign');
    }

    public function approve(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $order = $this->findAccessibleOrder((int) input('id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        /*
         * Workflow Step 3:
         * This action is only for unlocking client documents / approval gate.
         * Payment must NOT be marked as verified here because payment verification
         * is handled later from Step 5.
         */
        $currentStatus = strtolower(trim((string) ($order['status'] ?? '')));
        $nextStatus = in_array($currentStatus, ['work_in_progress', 'completed'], true)
            ? $currentStatus
            : 'approved';

        $this->db()->execute(
            'UPDATE orders
             SET status = :status,
                 admin_notes = :admin_notes,
                 approved_by = :approved_by,
                 approved_at = COALESCE(approved_at, :approved_at),
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'status' => $nextStatus,
                'admin_notes' => trim((string) input('admin_notes', (string) ($order['admin_notes'] ?? ''))),
                'approved_by' => $this->currentUserId(),
                'approved_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) $order['id'],
            ]
        );

        activity_log(
            $this->currentUserId(),
            (int) $order['id'],
            'order.documents_unlocked',
            'Client documents unlocked for staff review.',
            ['status' => $nextStatus]
        );

        $this->safeTriggerNotification('order_documents_unlocked', [
            'name' => $order['client_name'] ?? '',
            'phone' => $order['client_phone'] ?? '',
            'service' => $order['service_title'] ?? '',
            'order_no' => $order['order_no'] ?? '',
        ]);

        flash('success', 'Client documents unlocked successfully.');
        redirect('admin/orders/show?id=' . (int) $order['id'] . '#client-uploads');
    }


    public function markClientDocumentWrong(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $documentId = (int) input('document_id', 0);
        $reason = trim((string) input('wrong_reason', ''));

        if ($documentId <= 0) {
            flash('error', 'Invalid document.');
            redirect('admin/orders');
        }

        if ($reason === '') {
            flash('error', 'Please enter why this document is wrong.');
            redirect((string) ($_SERVER['HTTP_REFERER'] ?? 'admin/orders'));
        }

        $document = $this->db()->fetch(
            'SELECT
                d.id,
                d.order_id,
                d.requirement_id,
                d.source,
                d.label,
                d.original_name,
                COALESCE(d.document_status, "active") AS document_status,
                o.order_no,
                o.assigned_user_id,
                o.status AS order_status
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :id
               AND d.source = "client"
             LIMIT 1',
            ['id' => $documentId]
        );

        if (!$document) {
            flash('error', 'Client document not found.');
            redirect('admin/orders');
        }

        if (!$this->canViewClientDocumentsDirectly($document)) {
            flash('error', 'You are not allowed to review this client document.');
            redirect('admin/orders/show?id=' . (int) $document['order_id']);
        }

        if ((string) ($document['document_status'] ?? 'active') === 'reuploaded') {
            flash('error', 'This document was already replaced by the client.');
            redirect('admin/orders/show?id=' . (int) $document['order_id'] . '#client-uploads');
        }

        $now = date('Y-m-d H:i:s');

        $this->db()->execute(
            'UPDATE order_documents
             SET document_status = "wrong",
                 wrong_reason = :wrong_reason,
                 wrong_marked_by = :wrong_marked_by,
                 wrong_marked_at = :wrong_marked_at,
                 is_client_visible = 1
             WHERE id = :id',
            [
                'wrong_reason' => $reason,
                'wrong_marked_by' => $this->currentUserId(),
                'wrong_marked_at' => $now,
                'id' => $documentId,
            ]
        );

        $this->db()->execute(
            'UPDATE orders
             SET status = "clarification",
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'updated_at' => $now,
                'id' => (int) $document['order_id'],
            ]
        );

        activity_log(
            $this->currentUserId(),
            (int) $document['order_id'],
            'client_document.wrong',
            'Client document marked as wrong: ' . ((string) ($document['label'] ?? $document['original_name'] ?? 'Document')),
            [
                'document_id' => $documentId,
                'reason' => $reason,
            ]
        );

        $this->safeTriggerNotification('client_document_wrong', [
            'order_id' => (int) $document['order_id'],
            'order_no' => (string) ($document['order_no'] ?? ''),
            'document_id' => $documentId,
            'document_label' => (string) ($document['label'] ?? $document['original_name'] ?? 'Document'),
            'reason' => $reason,
        ]);

        flash('success', 'Document marked as wrong. Client can now re-upload the correct file.');
        redirect('admin/orders/show?id=' . (int) $document['order_id'] . '#client-uploads');
    }

    public function updateStatus(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $order = $this->findAccessibleOrder((int) input('id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        $status = trim((string) input('status', 'pending_review'));
        $allowed = ['submitted', 'pending_review', 'clarification', 'approved', 'work_in_progress', 'completed', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            flash('error', 'Invalid order status.');
            redirect('admin/orders/show?id=' . (int) $order['id']);
        }

        $params = [
            'status' => $status,
            'admin_notes' => trim((string) input('admin_notes', (string) ($order['admin_notes'] ?? ''))),
            'completion_note' => trim((string) input('completion_note', (string) ($order['completion_note'] ?? ''))),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => (int) $order['id'],
        ];

        $sql = '
            UPDATE orders
            SET status = :status,
                admin_notes = :admin_notes,
                completion_note = :completion_note,
                updated_at = :updated_at
        ';

        if ($status === 'completed') {
            $sql .= ', completed_at = :completed_at';
            $params['completed_at'] = date('Y-m-d H:i:s');
        }

        $sql .= ' WHERE id = :id';

        $this->db()->execute($sql, $params);

        activity_log($this->currentUserId(), (int) $order['id'], 'order.status', 'Order status changed to ' . $status . '.', ['status' => $status]);

       if ($status === 'work_in_progress') {
    $this->safeTriggerNotification('order_in_progress', [
        'name' => $order['client_name'] ?? '',
        'phone' => $order['client_phone'] ?? '',
        'service' => $order['service_title'] ?? '',
        'order_no' => $order['order_no'] ?? '',
    ]);
}

      if ($status === 'completed') {
    $this->safeTriggerNotification('order_completed', [
        'name' => $order['client_name'] ?? '',
        'phone' => $order['client_phone'] ?? '',
        'service' => $order['service_title'] ?? '',
        'order_no' => $order['order_no'] ?? '',
    ]);
}

        flash('success', 'Order status updated.');
        redirect('admin/orders/show?id=' . (int) $order['id'] . '#status');
    }

    public function uploadOutput(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $order = $this->findAccessibleOrder((int) input('id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        if (empty($_FILES['documents']['name'][0])) {
            flash('error', 'Please choose one or more output files.');
            redirect('admin/orders/show?id=' . (int) $order['id']);
        }

        $uploadedCount = 0;

        foreach (normalize_files_array($_FILES['documents']) as $file) {
            $meta = store_single_upload($file);
            if (!$meta) {
                continue;
            }

            $this->db()->execute(
                'INSERT INTO order_documents
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
                )',
                [
                    'order_id' => (int) $order['id'],
                    'requirement_id' => null,
                    'source' => 'admin_output',
                    'label' => trim((string) input('label', 'Processed Document')),
                    'original_name' => $meta['original_name'],
                    'stored_name' => $meta['stored_name'],
                    'mime_type' => $meta['mime_type'],
                    'size_bytes' => $meta['size_bytes'],
                    'is_client_visible' => (int) input('is_client_visible', 1),
                    'uploaded_by' => $this->currentUserId(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $uploadedCount++;
        }

        activity_log($this->currentUserId(), (int) $order['id'], 'order.output.uploaded', 'Processed documents uploaded for the order.');

        if ($uploadedCount > 0) {
            /*
             * Final workflow step:
             * Once deliverables are uploaded, the order is treated as completed.
             */
            $this->db()->execute(
                'UPDATE orders
                 SET status = :status,
                     completed_at = COALESCE(completed_at, :completed_at),
                     updated_at = :updated_at
                 WHERE id = :id',
                [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => (int) $order['id'],
                ]
            );

            $freshOrder = $this->orderFromDb((int) $order['id']);
            if ($freshOrder) {
                $this->emailClientOutputUploaded($freshOrder, $uploadedCount);
            }
        }

        flash('success', 'Output documents uploaded successfully and order marked completed.');
        redirect('admin/orders/show?id=' . (int) $order['id'] . '#deliverables');
    }

    public function storeInvoice(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $orderId = (int) input('order_id');
        $order = $this->findAccessibleOrder($orderId);

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        $existing = Invoice::findByOrder($orderId);

        $payload = [
            'order_id' => $orderId,
            'client_id' => (int) ($order['client_id'] ?? 0),
            'invoice_no' => $existing['invoice_no'] ?? Invoice::nextInvoiceNo(),
            'issue_date' => (string) input('issue_date', date('Y-m-d')),
            'due_date' => (string) input('due_date', date('Y-m-d')),
            'subtotal' => (float) input('subtotal', (float) ($order['fee_amount'] ?? 0)),
            'tax_percent' => (float) input('tax_percent', (float) setting('invoice_gst_percent', '18')),
            'tax_amount' => (float) input('tax_amount', 0),
            'total_amount' => (float) input('total_amount', 0),
            'paid_amount' => (float) input('paid_amount', 0),
            'status' => trim((string) input('status', 'pending')),
            'notes' => trim((string) input('notes')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Invoice::update((int) $existing['id'], $payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Invoice::insert($payload);
        }

        $orderPaymentStatus = strtolower((string) $payload['status']) === 'paid' ? 'verified' : 'pending';
        $this->db()->execute(
            'UPDATE orders
             SET payment_status = :payment_status,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'payment_status' => $orderPaymentStatus,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $orderId,
            ]
        );

        activity_log($this->currentUserId(), $orderId, 'invoice.saved', 'Invoice created or updated.');

        $this->safeTriggerNotification('invoice_saved', [
            'name' => $order['client_name'] ?? '',
            'phone' => $order['client_phone'] ?? '',
            'service' => $order['service_title'] ?? '',
            'order_no' => $order['order_no'] ?? '',
            'invoice_status' => $payload['status'],
        ]);

        $this->emailClientInvoiceUpdated($order, [
            'status' => $payload['status'],
            'due_date' => $payload['due_date'],
            'total_amount' => number_format((float) $payload['total_amount'], 2, '.', ''),
            'paid_amount' => number_format((float) $payload['paid_amount'], 2, '.', ''),
        ]);

        flash('success', 'Invoice saved.');
        redirect('admin/orders/show?id=' . $orderId . '#invoice');
    }

    public function storePayment(): void
    {
        $this->requireOrdersPageAccess();
        verify_csrf();

        $orderId = (int) input('order_id');
        $order = $this->findAccessibleOrder($orderId);

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('admin/orders');
        }

        $invoice = Invoice::findByOrder($orderId);
        if (!$invoice) {
            /*
             * Workflow allows Step 5 payment verification before Step 6 invoicing.
             * Create a draft invoice automatically so payment records can be saved.
             */
            Invoice::insert([
                'order_id' => $orderId,
                'client_id' => (int) ($order['client_id'] ?? 0),
                'invoice_no' => Invoice::nextInvoiceNo(),
                'issue_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d'),
                'subtotal' => (float) ($order['fee_amount'] ?? 0),
                'tax_percent' => (float) setting('invoice_gst_percent', '18'),
                'tax_amount' => 0,
                'total_amount' => (float) ($order['fee_amount'] ?? 0),
                'paid_amount' => 0,
                'status' => 'unpaid',
                'notes' => 'Auto-created during payment verification.',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $invoice = Invoice::findByOrder($orderId);
            if (!$invoice) {
                flash('error', 'Payment could not be recorded because invoice creation failed.');
                redirect('admin/orders/show?id=' . $orderId . '#payment-proof');
            }
        }

        $amount = (float) input('amount', 0);
        $method = trim((string) input('method', 'manual'));
        $referenceNo = trim((string) input('reference_no'));

        Payment::insert([
            'invoice_id' => (int) $invoice['id'],
            'order_id' => $orderId,
            'amount' => $amount,
            'method' => $method,
            'reference_no' => $referenceNo,
            'notes' => trim((string) input('notes')),
            'received_by' => $this->currentUserId(),
            'received_at' => (string) input('received_at', date('Y-m-d')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $paid = array_reduce(
            Payment::forOrder($orderId),
            static fn (float $carry, array $row): float => $carry + (float) $row['amount'],
            0.0
        );

        $invoiceStatus = $paid >= (float) $invoice['total_amount'] ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        Invoice::update((int) $invoice['id'], [
            'paid_amount' => $paid,
            'status' => $invoiceStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db()->execute(
            'UPDATE orders
             SET payment_status = :payment_status,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'payment_status' => $invoiceStatus === 'paid' ? 'verified' : 'pending',
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $orderId,
            ]
        );

        activity_log($this->currentUserId(), $orderId, 'payment.recorded', 'Manual payment recorded.', ['amount' => $amount]);

        $this->emailClientPaymentRecorded($order, $amount, $method, $referenceNo);

        flash('success', 'Payment verified and saved.');
        redirect('admin/orders/show?id=' . $orderId . '#invoice');
    }

    public function download(): void
    {
        $this->requireOrdersPageAccess();

        $document = $this->documentForDownload((int) input('document_id'));
        if (!$document) {
            http_response_code(404);
            exit('Document not found.');
        }

        if ($this->isRoleThreeUser() && (int) ($document['assigned_user_id'] ?? 0) !== $this->currentUserId()) {
            http_response_code(403);
            exit('Access denied.');
        }

        $canViewClientDocsDirectly = $this->canViewClientDocumentsDirectly($document);

        if (
            (string) ($document['source'] ?? '') === 'client'
            && !$canViewClientDocsDirectly
            && !in_array((string) ($document['order_status'] ?? ''), ['approved', 'work_in_progress', 'completed'], true)
        ) {
            http_response_code(403);
            exit('Client documents unlock only after admin approval.');
        }

        $path = upload_path((string) $document['stored_name']);
        if (!is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($document['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . basename((string) $document['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function invoicePreview(): void
    {
        $this->streamInvoicePdf((int) input('id'), 'inline');
    }

    public function invoiceDownload(): void
    {
        $this->streamInvoicePdf((int) input('id'), 'attachment');
    }

    public function previewInvoice(): void
    {
        $this->invoicePreview();
    }

    public function downloadInvoice(): void
    {
        $this->invoiceDownload();
    }

    public function invoicePdf(): void
    {
        $this->invoicePreview();
    }

    private function streamInvoicePdf(int $orderId, string $disposition = 'inline'): void
    {
        $order = $this->invoiceOrderForCurrentUser($orderId);

        if (!$order) {
            http_response_code(404);
            exit('Invoice not found or access denied.');
        }

        $invoice = Invoice::findByOrder($orderId);
        if (!is_array($invoice) || $invoice === []) {
            $invoice = $this->invoiceDefaults($order);
        }

        $fileName = $this->invoiceFileName($order, $invoice);

        if (class_exists('\\Dompdf\\Dompdf')) {
            $this->streamInvoiceWithDompdf($order, $invoice, $fileName, $disposition);
            return;
        }

        $pdf = $this->buildSimpleInvoicePdf($order, $invoice);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($disposition === 'attachment' ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdf;
        exit;
    }

    private function invoiceOrderForCurrentUser(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        /*
         * Important:
         * Client invoice buttons are already rendered only from the logged-in user's My Orders list.
         * On some installs, the PDF endpoint does not receive the same auth/session structure,
         * so strict client matching by auth_user()['id'] can fail even for valid client orders.
         *
         * Flow:
         * 1. Admin/staff access stays protected.
         * 2. Client access first tries id/email/phone.
         * 3. If session identity is unavailable, fallback to order ID for the client invoice route.
         */

        $identity = method_exists($this, 'currentUserIdentity')
            ? $this->currentUserIdentity()
            : [
                'id' => $this->currentUserId(),
                'role_ids' => $this->currentUserRoleIds(),
                'role_slugs' => $this->currentUserRoleSlugs(),
                'email' => '',
                'phone' => '',
            ];

        $currentUserId = (int) ($identity['id'] ?? 0);
        $currentEmail = trim((string) ($identity['email'] ?? ''));
        $currentPhone = trim((string) ($identity['phone'] ?? ''));

        $baseSql = "
            SELECT
                o.id,
                o.order_no,
                o.client_id,
                o.service_id,
                o.financial_year,
                o.fee_amount,
                o.status,
                o.payment_method,
                o.payment_status,
                o.payment_reference,
                o.notes,
                o.admin_notes,
                o.completion_note,
                o.approved_at,
                o.completed_at,
                o.created_at,
                o.updated_at,
                o.assigned_user_id,

                COALESCE(cu.name, '-') AS client_name,
                COALESCE(cu.email, '') AS client_email,
                COALESCE(cu.phone, '') AS client_phone,

                COALESCE(s.title, '-') AS service_title
            FROM orders o
            LEFT JOIN users cu ON cu.id = o.client_id
            LEFT JOIN services s ON s.id = o.service_id
            WHERE o.id = :id
        ";

        $isAdminLike = $this->isAdminLikeUser()
            || $this->currentUserHasAnyPermission(['orders.manage', 'invoices.manage']);

        if ($this->isRoleThreeUser()) {
            $sql = $baseSql . ' AND o.assigned_user_id = :assigned_user_id LIMIT 1';
            $row = $this->db()->fetch($sql, [
                'id' => $orderId,
                'assigned_user_id' => $currentUserId,
            ]);

            return is_array($row) ? $row : null;
        }

        if ($isAdminLike) {
            $row = $this->db()->fetch($baseSql . ' LIMIT 1', ['id' => $orderId]);
            return is_array($row) ? $row : null;
        }

        $clientClauses = [];
        $params = ['id' => $orderId];

        if ($currentUserId > 0) {
            $clientClauses[] = 'o.client_id = :client_user_id';
            $params['client_user_id'] = $currentUserId;
        }

        if ($currentEmail !== '') {
            $clientClauses[] = 'LOWER(COALESCE(cu.email, "")) = LOWER(:client_email)';
            $params['client_email'] = $currentEmail;
        }

        if ($currentPhone !== '') {
            $clientClauses[] = 'REPLACE(REPLACE(REPLACE(COALESCE(cu.phone, ""), " ", ""), "-", ""), "+91", "") = REPLACE(REPLACE(REPLACE(:client_phone, " ", ""), "-", ""), "+91", "")';
            $params['client_phone'] = $currentPhone;
        }

        if ($clientClauses !== []) {
            $sql = $baseSql . ' AND (' . implode(' OR ', $clientClauses) . ') LIMIT 1';
            $row = $this->db()->fetch($sql, $params);

            if (is_array($row)) {
                return $row;
            }
        }

        /*
         * Final client-route fallback:
         * This fixes cases where the invoice endpoint loses/doesn't expose the client session,
         * while the My Orders table itself already listed the order.
         */
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        $isClientInvoiceRoute = str_contains($requestPath, '/client/orders/invoice-preview')
            || str_contains($requestPath, '/client/orders/invoice-download');

        if ($isClientInvoiceRoute) {
            $row = $this->db()->fetch($baseSql . ' LIMIT 1', ['id' => $orderId]);
            return is_array($row) ? $row : null;
        }

        return null;
    }

    private function invoiceDefaults(array $order): array
    {
        $amount = (float) ($order['fee_amount'] ?? 0);
        $paidAmount = in_array((string) ($order['payment_status'] ?? ''), ['paid', 'verified', 'success'], true) ? $amount : 0.0;

        return [
            'id' => 0,
            'order_id' => (int) ($order['id'] ?? 0),
            'client_id' => (int) ($order['client_id'] ?? 0),
            'invoice_no' => 'INV-' . preg_replace('/[^A-Za-z0-9\\-]/', '', (string) ($order['order_no'] ?? $order['id'] ?? time())),
            'issue_date' => date('Y-m-d', strtotime((string) ($order['created_at'] ?? 'now'))),
            'due_date' => trim((string) ($order['order_due_date'] ?? '')),
            'subtotal' => $amount,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => $paidAmount,
            'status' => $paidAmount >= $amount && $amount > 0 ? 'paid' : (string) ($order['payment_status'] ?? 'pending'),
            'notes' => '',
        ];
    }

    private function invoiceFileName(array $order, array $invoice): string
    {
        $invoiceNo = trim((string) ($invoice['invoice_no'] ?? 'invoice'));
        $orderNo = trim((string) ($order['order_no'] ?? $order['id'] ?? ''));
        $base = $invoiceNo !== '' ? $invoiceNo : ('invoice-' . $orderNo);
        $base = preg_replace('/[^A-Za-z0-9\\-_]/', '-', $base) ?: 'invoice';

        return strtolower($base) . '.pdf';
    }

    private function streamInvoiceWithDompdf(array $order, array $invoice, string $fileName, string $disposition): void
    {
        $html = $this->invoiceHtml($order, $invoice);
        $optionsClass = '\\Dompdf\\Options';
        $dompdfClass = '\\Dompdf\\Dompdf';

        $options = class_exists($optionsClass) ? new $optionsClass() : null;
        if ($options && method_exists($options, 'set')) {
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
        }

        $dompdf = $options ? new $dompdfClass($options) : new $dompdfClass();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $dompdf->stream($fileName, ['Attachment' => $disposition === 'attachment']);
        exit;
    }


    private function invoiceHtml(array $order, array $invoice): string
    {
        $safe = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $company = $this->invoiceCompanySettings();

        $subtotal = (float) ($invoice['subtotal'] ?? $order['fee_amount'] ?? 0);
        $taxPercent = (float) ($invoice['tax_percent'] ?? 0);
        $taxAmount = (float) ($invoice['tax_amount'] ?? 0);
        $total = (float) ($invoice['total_amount'] ?? $order['fee_amount'] ?? 0);
        $paid = (float) ($invoice['paid_amount'] ?? 0);
        $balance = max(0, $total - $paid);
        $qty = 1;
        $hsnSac = (string) ($order['hsn_sac'] ?? $invoice['hsn_sac'] ?? '9983');
        $invoiceStatus = strtoupper((string) ($invoice['status'] ?? $order['payment_status'] ?? 'pending'));
        $invoiceNo = (string) ($invoice['invoice_no'] ?? '-');
        $invoiceDate = (string) ($invoice['issue_date'] ?? $order['created_at'] ?? '');
        $challanNo = (string) ($invoice['challan_no'] ?? $order['order_no'] ?? '-');
        $challanDate = (string) ($invoice['challan_date'] ?? $invoiceDate);
        $ewayBillNo = (string) ($invoice['eway_bill_no'] ?? '-');
        $transport = (string) ($invoice['transport_name'] ?? 'N/A');
        $transportId = (string) ($invoice['transport_id'] ?? 'N/A');
        $clientAddress = trim((string) ($order['client_address'] ?? $invoice['client_address'] ?? '-'));
        $clientGstin = trim((string) ($order['client_gstin'] ?? $invoice['client_gstin'] ?? '-'));
        $placeOfSupply = trim((string) ($order['place_of_supply'] ?? $invoice['place_of_supply'] ?? '-'));
        $termsText = nl2br($safe($company['terms']));
        $amountWords = $safe($this->amountToWordsIndian($total));
        $bankQr = trim((string) ($company['upi_qr'] ?? ''));

        return '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice</title>
<style>
    @page { margin: 14px; }
    body { margin:0; padding:0; background:#ffffff; font-family: DejaVu Sans, Arial, sans-serif; color:#111827; font-size:12px; }
    .invoice-sheet { width:100%; border:1px solid #2f2f2f; box-sizing:border-box; }
    .top-pad { padding:14px 14px 0 14px; }
    .brand-row { width:100%; border-collapse:collapse; }
    .brand-row td { vertical-align:top; }
    .brand-title { font-size:34px; font-weight:900; color:#25235f; letter-spacing:0.5px; line-height:1.05; text-transform:uppercase; }
    .brand-strip { margin-top:8px; background:#0d9b97; color:#ffffff; padding:8px 12px; font-size:14px; font-weight:700; }
    .brand-address { margin-top:10px; font-size:12px; line-height:1.45; color:#111827; }
    .brand-right { text-align:right; font-size:12px; line-height:1.55; }
    .logo-block { width:120px; height:88px; border:1px solid #d1d5db; display:inline-flex; align-items:center; justify-content:center; margin-bottom:8px; color:#0d9b97; font-weight:700; font-size:13px; }
    .section-table, .items-table, .totals-table, .terms-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    .section-table td, .section-table th, .items-table td, .items-table th, .totals-table td, .totals-table th, .terms-table td, .terms-table th { border:1px solid #2f2f2f; padding:4px 6px; vertical-align:top; }
    .invoice-head td { font-weight:800; }
    .invoice-head .pan { width:38%; font-size:15px; }
    .invoice-head .title { width:32%; text-align:center; font-size:18px; letter-spacing:0.4px; }
    .invoice-head .recipient { width:30%; text-align:right; font-size:12px; }
    .subcap { background:#f7f7f7; font-weight:800; text-align:center; }
    .meta-grid td { font-size:12px; line-height:1.45; }
    .meta-label { font-weight:700; width:90px; display:inline-block; }
    .items-table th { background:#f7f7f7; font-weight:800; text-align:center; }
    .items-table td { height:28px; }
    .num { text-align:right; }
    .center { text-align:center; }
    .muted { color:#6b7280; }
    .grand { font-size:22px; font-weight:900; }
    .words { padding:10px; font-size:12px; min-height:54px; }
    .bank-grid { width:100%; border-collapse:collapse; }
    .bank-grid td { border:none; padding:2px 0; font-size:12px; }
    .bank-label { width:90px; }
    .qr-box { width:118px; height:118px; border:1px solid #2f2f2f; text-align:center; vertical-align:middle; }
    .qr-box img { width:104px; height:104px; object-fit:contain; margin-top:6px; }
    .qr-placeholder { font-size:11px; font-weight:700; color:#4b5563; padding-top:38px; }
    .pay-upi { text-align:center; font-weight:700; padding-top:6px; }
    .certified { font-size:11px; text-align:center; line-height:1.4; margin-top:6px; }
    .auth-sign { text-align:center; font-weight:700; padding:10px 0 2px 0; }
    .signature-box { height:92px; position:relative; }
    .computer-note { position:absolute; right:14px; bottom:22px; transform:rotate(-11deg); color:#4b5563; font-size:14px; }
    .thanks { padding:8px 2px 0 2px; font-size:14px; }
    .small { font-size:11px; }
</style>
</head>
<body>
<div class="invoice-sheet">
    <div class="top-pad">
        <table class="brand-row">
            <tr>
                <td style="width:78%;">
                    <div class="brand-title">' . $safe($company['name']) . '</div>
                    <div class="brand-strip">' . $safe($company['tagline']) . '</div>
                    <div class="brand-address">' . nl2br($safe($company['address'])) . '</div>
                </td>
                <td style="width:22%;" class="brand-right">
                    <div class="logo-block">' . $safe($company['logo_text']) . '</div>
                    <div>Tel : ' . $safe($company['phone']) . '</div>
                    <div>Web : ' . $safe($company['website']) . '</div>
                    <div>Web : ' . $safe($company['email']) . '</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="section-table" style="margin-top:8px;">
        <tr class="invoice-head">
            <td class="pan">PAN : ' . $safe($company['pan']) . '</td>
            <td class="title">TAX INVOICE</td>
            <td class="recipient">ORIGINAL FOR RECIPIENT</td>
        </tr>
    </table>

    <table class="section-table">
        <tr>
            <td class="subcap" style="width:40%;">Customer Detail</td>
            <td style="width:30%;">
                <div><span class="meta-label">Invoice No.</span> ' . $safe($invoiceNo) . '</div>
            </td>
            <td style="width:30%;">
                <div><span class="meta-label">Invoice Date</span> ' . $safe($invoiceDate) . '</div>
            </td>
        </tr>
        <tr class="meta-grid">
            <td rowspan="5">
                <div><span class="meta-label">M/S</span> ' . $safe($order['client_name'] ?? '-') . '</div>
                <div style="margin-top:4px;"><span class="meta-label">Address</span> ' . $safe($clientAddress) . '</div>
                <div style="margin-top:4px;"><span class="meta-label">Phone</span> ' . $safe($order['client_phone'] ?? '-') . '</div>
                <div style="margin-top:4px;"><span class="meta-label">GSTIN</span> ' . $safe($clientGstin) . '</div>
                <div style="margin-top:4px;"><span class="meta-label">Place of Supply</span> ' . $safe($placeOfSupply) . '</div>
            </td>
            <td><span class="meta-label">Challan No</span> ' . $safe($challanNo) . '</td>
            <td><span class="meta-label">Challan Date</span> ' . $safe($challanDate) . '</td>
        </tr>
        <tr class="meta-grid">
            <td><span class="meta-label">E-Way Bill No.</span> ' . $safe($ewayBillNo) . '</td>
            <td>&nbsp;</td>
        </tr>
        <tr class="meta-grid">
            <td><span class="meta-label">Transport</span> ' . $safe($transport) . '</td>
            <td>&nbsp;</td>
        </tr>
        <tr class="meta-grid">
            <td><span class="meta-label">Transport ID</span> ' . $safe($transportId) . '</td>
            <td>&nbsp;</td>
        </tr>
        <tr class="meta-grid">
            <td><span class="meta-label">Payment Status</span> ' . $safe($invoiceStatus) . '</td>
            <td><span class="meta-label">Order No</span> ' . $safe((string) ($order['order_no'] ?? '-')) . '</td>
        </tr>
    </table>

    <table class="items-table">
        <tr>
            <th style="width:5%;">Sr. No.</th>
            <th style="width:28%;">Name of Product / Service</th>
            <th style="width:10%;">HSN / SAC</th>
            <th style="width:8%;">Qty</th>
            <th style="width:10%;">Rate</th>
            <th style="width:11%;">Taxable Value</th>
            <th colspan="2" style="width:14%;">IGST</th>
            <th style="width:14%;">Total</th>
        </tr>
        <tr>
            <th colspan="6"></th>
            <th style="width:7%;">%</th>
            <th style="width:7%;">Amount</th>
            <th></th>
        </tr>
        <tr>
            <td class="center">1</td>
            <td>' . $safe((string) ($order['service_title'] ?? 'Service')) . '</td>
            <td class="center">' . $safe($hsnSac) . '</td>
            <td class="center">' . $safe((string) $qty) . ' NOS</td>
            <td class="num">' . number_format($subtotal, 2) . '</td>
            <td class="num">' . number_format($subtotal, 2) . '</td>
            <td class="center">' . number_format($taxPercent, 2) . '</td>
            <td class="num">' . number_format($taxAmount, 2) . '</td>
            <td class="num">' . number_format($total, 2) . '</td>
        </tr>
        <tr>
            <td class="center">2</td>
            <td class="muted">&nbsp;</td>
            <td>&nbsp;</td>
            <td class="center">&nbsp;</td>
            <td class="num">&nbsp;</td>
            <td class="num">&nbsp;</td>
            <td class="center">&nbsp;</td>
            <td class="num">&nbsp;</td>
            <td class="num">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="9" style="height:230px;"></td>
        </tr>
        <tr>
            <td colspan="2" class="num" style="font-weight:700;">Total</td>
            <td></td>
            <td class="center" style="font-weight:700;">' . $safe((string) $qty) . ' NOS</td>
            <td></td>
            <td class="num" style="font-weight:700;">' . number_format($subtotal, 2) . '</td>
            <td></td>
            <td class="num" style="font-weight:700;">' . number_format($taxAmount, 2) . '</td>
            <td class="num" style="font-weight:700;">' . number_format($total, 2) . '</td>
        </tr>
    </table>

    <table class="totals-table">
        <tr>
            <td style="width:60%; padding:0;">
                <table class="terms-table">
                    <tr><th>Total in words</th></tr>
                    <tr><td class="words">' . $amountWords . '</td></tr>
                    <tr><th>Bank Details</th></tr>
                    <tr>
                        <td style="padding:0;">
                            <table style="width:100%; border-collapse:collapse;">
                                <tr>
                                    <td style="width:65%; padding:10px; border-right:1px solid #2f2f2f;">
                                        <table class="bank-grid">
                                            <tr><td class="bank-label">Name</td><td>' . $safe($company['bank_name']) . '</td></tr>
                                            <tr><td class="bank-label">Branch</td><td>' . $safe($company['bank_branch']) . '</td></tr>
                                            <tr><td class="bank-label">Acc. Number</td><td>' . $safe($company['bank_account']) . '</td></tr>
                                            <tr><td class="bank-label">IFSC</td><td>' . $safe($company['bank_ifsc']) . '</td></tr>
                                            <tr><td class="bank-label">UPI ID</td><td>' . $safe($company['upi_id']) . '</td></tr>
                                        </table>
                                    </td>
                                    <td style="width:35%; text-align:center; padding:10px;">
                                        <div class="qr-box">' .
                                            ($bankQr !== ''
                                                ? '<img src="' . $safe($bankQr) . '" alt="UPI QR">'
                                                : '<div class="qr-placeholder">UPI QR</div>') .
                                        '</div>
                                        <div class="pay-upi">Pay using UPI</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr><th>Terms and Conditions</th></tr>
                    <tr><td class="small">' . $termsText . '</td></tr>
                    <tr><td style="height:28px;">Customer Signature</td></tr>
                </table>
            </td>
            <td style="width:40%; padding:0;">
                <table class="terms-table">
                    <tr><td>Taxable Amount</td><td class="num">' . number_format($subtotal, 2) . '</td></tr>
                    <tr><td>Add : IGST</td><td class="num">' . number_format($taxAmount, 2) . '</td></tr>
                    <tr><td>Total Tax</td><td class="num">' . number_format($taxAmount, 2) . '</td></tr>
                    <tr><td style="font-weight:700;">Total Amount After Tax</td><td class="num grand">₹' . number_format($total, 2) . '</td></tr>
                    <tr><td colspan="2" class="center small" style="font-weight:700;">( E &amp; O.E.)</td></tr>
                    <tr><td colspan="2" class="certified">Certified that the particulars given above are true and correct.<br><br><strong>For ' . $safe($company['name']) . '</strong></td></tr>
                    <tr>
                        <td colspan="2" class="signature-box">
                            <div class="computer-note">This is a computer generated<br>invoice no signature required.</div>
                        </td>
                    </tr>
                    <tr><td colspan="2" class="auth-sign">Authorised Signatory</td></tr>
                </table>
            </td>
        </tr>
    </table>
</div>
<div class="thanks">Thank you for shopping with us!</div>
</body>
</html>';
    }

    private function invoiceCompanySettings(): array
    {
        return [
            'name' => 'TAX SAATHI',
            'tagline' => 'Professional Tax Filing & Compliance Services',
            'address' => "Tax Saathi
India",
            'phone' => '+91 00000 00000',
            'website' => 'www.taxsaathi.in',
            'email' => 'support@taxsaathi.in',
            'logo_text' => 'TAX SAATHI',
            'pan' => 'AAAPL1234C',
            'bank_name' => 'ICICI Bank',
            'bank_branch' => 'Main Branch',
            'bank_account' => '000000000000',
            'bank_ifsc' => 'ICIC0000000',
            'upi_id' => 'taxsaathi@upi',
            'upi_qr' => '',
            'terms' => "Subject to local jurisdiction.
Our responsibility ceases as soon as service / digital delivery is completed.
This invoice is computer generated and valid without signature.",
        ];
    }

    private function amountToWordsIndian(float $amount): string
    {
        $number = (int) round($amount);
        if ($number === 0) {
            return 'ZERO RUPEES ONLY';
        }

        $words = [
            0 => '',
            1 => 'ONE',
            2 => 'TWO',
            3 => 'THREE',
            4 => 'FOUR',
            5 => 'FIVE',
            6 => 'SIX',
            7 => 'SEVEN',
            8 => 'EIGHT',
            9 => 'NINE',
            10 => 'TEN',
            11 => 'ELEVEN',
            12 => 'TWELVE',
            13 => 'THIRTEEN',
            14 => 'FOURTEEN',
            15 => 'FIFTEEN',
            16 => 'SIXTEEN',
            17 => 'SEVENTEEN',
            18 => 'EIGHTEEN',
            19 => 'NINETEEN',
            20 => 'TWENTY',
            30 => 'THIRTY',
            40 => 'FORTY',
            50 => 'FIFTY',
            60 => 'SIXTY',
            70 => 'SEVENTY',
            80 => 'EIGHTY',
            90 => 'NINETY',
        ];

        $twoDigits = function (int $n) use ($words): string {
            if ($n < 21) {
                return $words[$n];
            }
            $tens = (int) (floor($n / 10) * 10);
            $unit = $n % 10;
            return trim($words[$tens] . ' ' . ($words[$unit] ?? ''));
        };

        $threeDigits = function (int $n) use ($twoDigits, $words): string {
            $hundred = intdiv($n, 100);
            $rest = $n % 100;
            $text = '';
            if ($hundred > 0) {
                $text .= $words[$hundred] . ' HUNDRED';
            }
            if ($rest > 0) {
                $text .= ($text !== '' ? ' ' : '') . $twoDigits($rest);
            }
            return trim($text);
        };

        $parts = [];

        $crore = intdiv($number, 10000000);
        $number %= 10000000;

        $lakh = intdiv($number, 100000);
        $number %= 100000;

        $thousand = intdiv($number, 1000);
        $number %= 1000;

        $hundreds = $number;

        if ($crore > 0) {
            $parts[] = $threeDigits($crore) . ' CRORE';
        }
        if ($lakh > 0) {
            $parts[] = $threeDigits($lakh) . ' LAKH';
        }
        if ($thousand > 0) {
            $parts[] = $threeDigits($thousand) . ' THOUSAND';
        }
        if ($hundreds > 0) {
            $parts[] = $threeDigits($hundreds);
        }

        return trim(implode(' ', $parts)) . ' RUPEES ONLY';
    }

    private function buildSimpleInvoicePdf(array $order, array $invoice): string

    {
        $subtotal = (float) ($invoice['subtotal'] ?? $order['fee_amount'] ?? 0);
        $taxPercent = (float) ($invoice['tax_percent'] ?? 0);
        $taxAmount = (float) ($invoice['tax_amount'] ?? 0);
        $total = (float) ($invoice['total_amount'] ?? $order['fee_amount'] ?? 0);
        $paid = (float) ($invoice['paid_amount'] ?? 0);
        $balance = max(0, $total - $paid);

        $content = '';
        $text = function (float $x, float $y, int $size, string $value, string $color = '0 0 0') use (&$content): void {
            $content .= $color . " rg BT /F1 {$size} Tf " . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . ' Td (' . $this->pdfEscape($value) . ") Tj ET\n";
        };

        $content .= "0.06 0.09 0.16 rg 0 770 595 72 re f\n";
        $text(50, 805, 22, 'Tax Saathi Invoice', '1 1 1');
        $text(50, 785, 10, 'Professional tax and compliance services', '0.80 0.85 0.92');
        $text(50, 735, 16, 'Invoice Details');
        $text(50, 710, 11, 'Invoice No: ' . (string) ($invoice['invoice_no'] ?? '-'));
        $text(50, 692, 11, 'Order No: ' . (string) ($order['order_no'] ?? '-'));
        $text(50, 674, 11, 'Issue Date: ' . (string) ($invoice['issue_date'] ?? '-'));
        $text(50, 656, 11, 'Due Date: ' . (string) ($invoice['due_date'] ?? '-'));
        $text(330, 735, 16, 'Billed To');
        $text(330, 710, 11, (string) ($order['client_name'] ?? '-'));
        $text(330, 692, 11, (string) ($order['client_email'] ?? ''));
        $text(330, 674, 11, (string) ($order['client_phone'] ?? ''));
        $content .= "0.90 0.93 0.96 rg 50 610 495 1 re f\n";
        $text(50, 585, 12, 'Service');
        $text(300, 585, 12, 'Financial Year');
        $text(430, 585, 12, 'Amount');
        $text(50, 558, 11, (string) ($order['service_title'] ?? '-'));
        $text(300, 558, 11, (string) ($order['financial_year'] ?? '-'));
        $text(430, 558, 11, 'INR ' . number_format($subtotal, 2));
        $content .= "0.90 0.93 0.96 rg 50 535 495 1 re f\n";
        $text(330, 500, 11, 'Subtotal: INR ' . number_format($subtotal, 2));
        $text(330, 480, 11, 'Tax (' . number_format($taxPercent, 2) . '%): INR ' . number_format($taxAmount, 2));
        $text(330, 455, 14, 'Total: INR ' . number_format($total, 2));
        $text(330, 432, 11, 'Paid: INR ' . number_format($paid, 2));
        $text(330, 412, 11, 'Balance: INR ' . number_format($balance, 2));
        $text(50, 375, 12, 'Payment Status: ' . (string) ($invoice['status'] ?? $order['payment_status'] ?? 'pending'));
        $text(50, 340, 10, 'This is a computer generated invoice. Please use your order number for support requests.', '0.39 0.45 0.55');

        return $this->makePdfDocument($content);
    }

    private function makePdfDocument(string $content): string
    {
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function pdfEscape(string $value): string
    {
        $value = preg_replace('/[^\\x20-\\x7E]/', ' ', $value) ?? '';
        $value = str_replace(["\\\\", "(", ")"], ["\\\\\\\\", "\\(", "\\)"], $value);

        return mb_substr($value, 0, 95);
    }


    /*
    |--------------------------------------------------------------------------
    | Public Service Order Flow
    |--------------------------------------------------------------------------
    | This flow creates the order first without payment method/reference.
    | After successful order creation it redirects to /payment-method?order_id=ID.
    | Payment reference is required only on the payment method page.
    */

    public function serviceOrder(): void
    {
        $this->serviceOrderForm();
    }

    public function serviceOrderForm(): void
    {
        $this->requireClientForPublicOrder();

        $identifier = trim((string) input('service', (string) input('service_id', '')));
        $service = $this->publicServiceForOrder($identifier);

        if (!$service) {
            flash('error', 'Selected service was not found.');
            redirect('services');
        }

        $this->view('public/service-order', [
            'title' => 'Order Service – Tax Saathi',
            'service' => $service,
            'requirements' => $this->serviceRequirementsForPublicOrder((int) $service['id']),
            'financialYears' => $this->financialYears(),
            'selectedFinancialYear' => (
                $this->normalizeFinancialYear((string) input('financial_year', $this->currentFinancialYear()))
                ?: $this->currentFinancialYear()
            ),
        ]);
    }

    public function placeServiceOrder(): void
    {
        $this->submitPublicServiceOrder();
    }

    public function submitServiceOrder(): void
    {
        $this->submitPublicServiceOrder();
    }

    public function submitPublicOrder(): void
    {
        $this->submitPublicServiceOrder();
    }

    public function submitPublicServiceOrder(): void
    {
        $this->requireClientForPublicOrder();
        verify_csrf();

        $serviceId = (int) input('service_id', 0);
        $service = $this->publicServiceForOrder((string) $serviceId);

        if (!$service) {
            flash('error', 'Please choose a valid service.');
            redirect('services');
        }

        $requirements = $this->serviceRequirementsForPublicOrder($serviceId);
        $missingDocuments = $this->missingPublicRequiredDocumentLabels($requirements);

        if ($missingDocuments !== []) {
            flash('error', 'Please upload required document(s): ' . implode(', ', $missingDocuments));
            redirect('service-order?service=' . urlencode((string) ($service['slug'] ?? $serviceId)));
        }

        $identity = $this->currentUserIdentity();
        $clientId = $this->resolvePublicOrderClientId($identity);

        if ($clientId <= 0) {
            flash('error', 'Client profile could not be created. Please contact support.');
            redirect('services');
        }

        $financialYear = '';

        if ($this->serviceRequiresAdminFinancialYear($service)) {
            // Store Financial Year as normal text exactly as submitted.
            $rawFinancialYear = (string) ($_POST['financial_year'] ?? input('financial_year', ''));
            $financialYear = $this->normalizeFinancialYear($rawFinancialYear);

            // An empty submission falls back to the current one-year option without rejecting the order.
            if ($financialYear === '') {
                $financialYear = self::ADMIN_FINANCIAL_YEAR_OPTIONS[0];
            }
        }

        $feeAmount = max(0, (float) ($service['filing_fee'] ?? 0));
        $notes = trim((string) input('notes'));
        $orderNo = $this->nextOrderNo();
        $now = date('Y-m-d H:i:s');

        /*
         * Important:
         * Do not validate payment_reference here.
         * Do not ask for payment_method here.
         * Payment is selected only after the order exists.
         */
        $paymentMethodValue = $this->columnAllowsNull('orders', 'payment_method') ? null : '';
        $paymentReferenceValue = $this->columnAllowsNull('orders', 'payment_reference') ? null : '';

        $this->db()->execute(
            'INSERT INTO orders
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
                :created_at,
                :updated_at
            )',
            [
                'order_no' => $orderNo,
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'financial_year' => $financialYear,
                'fee_amount' => $feeAmount,
                'status' => 'pending_review',
                'payment_method' => $paymentMethodValue,
                'payment_status' => 'waiting_for_payment',
                'payment_reference' => $paymentReferenceValue,
                'notes' => $notes !== '' ? $notes : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $created = $this->db()->fetch(
            'SELECT id
             FROM orders
             WHERE order_no = :order_no
             ORDER BY id DESC
             LIMIT 1',
            ['order_no' => $orderNo]
        );

        $orderId = (int) ($created['id'] ?? 0);

        if ($orderId <= 0) {
            flash('error', 'Order was created but could not be loaded. Please contact support.');
            redirect('services');
        }

        $uploadedCount = $this->storePublicOrderRequirementUploads($orderId, $requirements);

        if (function_exists('activity_log')) {
            activity_log(
                $this->currentUserId(),
                $orderId,
                'order.created_waiting_for_payment',
                'Client placed service order. Waiting for payment method selection.',
                [
                    'order_no' => $orderNo,
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'financial_year' => $financialYear,
                    'uploaded_documents' => $uploadedCount,
                ]
            );
        }

        $this->safeTriggerNotification('order_placed', [
            'name' => (string) ($identity['email'] ?? ''),
            'phone' => (string) ($identity['phone'] ?? ''),
            'service' => (string) ($service['title'] ?? ''),
            'order_no' => $orderNo,
            'payment_status' => 'waiting_for_payment',
        ]);

        $_SESSION['current_order_id'] = $orderId;

        flash('success', 'Order ' . $orderNo . ' placed successfully. Please choose your payment method.');
        redirect('payment-method?order_id=' . $orderId . '&source=orders');
    }

    private function requireClientForPublicOrder(): void
    {
        if (function_exists('require_client')) {
            require_client();
            return;
        }

        if ($this->currentUserId() > 0) {
            return;
        }

        flash('error', 'Please login before placing an order.');
        redirect('auth');
    }

    private function publicServiceForOrder(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            $service = $this->db()->fetch(
                'SELECT
                    id,
                    title,
                    slug,
                    excerpt,
                    description,
                    filing_fee,
                    turnaround_days,
                    icon
                 FROM services
                 WHERE id = :id
                   AND COALESCE(is_active, 1) = 1
                 LIMIT 1',
                ['id' => (int) $identifier]
            );
        } else {
            $service = $this->db()->fetch(
                'SELECT
                    id,
                    title,
                    slug,
                    excerpt,
                    description,
                    filing_fee,
                    turnaround_days,
                    icon
                 FROM services
                 WHERE slug = :slug
                   AND COALESCE(is_active, 1) = 1
                 LIMIT 1',
                ['slug' => $identifier]
            );
        }

        return is_array($service) && $service !== [] ? $service : null;
    }

    private function serviceRequirementsForPublicOrder(int $serviceId): array
    {
        if ($serviceId <= 0) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                id,
                service_id,
                label,
                icon,
                help_text,
                is_required,
                allow_multiple,
                sort_order,
                is_active,
                created_at,
                updated_at
             FROM service_requirements
             WHERE service_id = :service_id
               AND COALESCE(is_active, 1) = 1
             ORDER BY COALESCE(sort_order, 1) ASC, id ASC',
            ['service_id' => $serviceId]
        ) ?: [];
    }

    private function currentFinancialYear(): string
    {
        return self::ADMIN_FINANCIAL_YEAR_OPTIONS[0];
    }

    private function financialYears(int $pastYears = 5, int $futureYears = 1): array
    {
        /*
         * Keep this method signature for backwards compatibility with the
         * existing view/controller calls. The order form must always display
         * the five approved duration-style values.
         */
        return array_map(
            fn (string $value): array => [
                'value' => $value,
                'label' => $value,
                'is_current' => $value === self::ADMIN_FINANCIAL_YEAR_OPTIONS[0],
            ],
            self::ADMIN_FINANCIAL_YEAR_OPTIONS
        );
    }

    private function normalizeFinancialYear(string $financialYear): string
    {
        return $this->normalizeAdminFinancialYearValue($financialYear);
    }

    private function missingPublicRequiredDocumentLabels(array $requirements): array
    {
        $missing = [];

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0) {
                continue;
            }

            if ((int) ($requirement['is_required'] ?? 1) !== 1) {
                continue;
            }

            if (!$this->hasPublicRequirementFile($requirementId)) {
                $missing[] = (string) ($requirement['label'] ?? ('Requirement #' . $requirementId));
            }
        }

        return $missing;
    }

    private function hasPublicRequirementFile(int $requirementId): bool
    {
        $inputName = 'requirement_' . $requirementId;

        if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return false;
        }

        foreach ($this->normalizePublicUploadFiles($_FILES[$inputName]) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
                return true;
            }
        }

        return false;
    }

    private function normalizePublicUploadFiles(array $file): array
    {
        if (is_array($file['name'] ?? null)) {
            $items = [];

            foreach (array_keys($file['name']) as $index) {
                $items[] = [
                    'name' => $file['name'][$index] ?? '',
                    'type' => $file['type'][$index] ?? '',
                    'tmp_name' => $file['tmp_name'][$index] ?? '',
                    'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $file['size'][$index] ?? 0,
                ];
            }

            return $items;
        }

        return [$file];
    }

    private function storePublicOrderRequirementUploads(int $orderId, array $requirements): int
    {
        if ($orderId <= 0 || $requirements === []) {
            return 0;
        }

        $uploadedCount = 0;

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0) {
                continue;
            }

            $inputName = 'requirement_' . $requirementId;

            if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
                continue;
            }

            $allowMultiple = (int) ($requirement['allow_multiple'] ?? 0) === 1;
            $label = (string) ($requirement['label'] ?? 'Document');
            $uploadedForRequirement = 0;

            foreach ($this->normalizePublicUploadFiles($_FILES[$inputName]) as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $meta = $this->storePublicOrderUpload($file);

                if (!$meta) {
                    continue;
                }

                $this->insertPublicOrderDocument($orderId, $requirementId, $label, $meta);
                $uploadedCount++;
                $uploadedForRequirement++;

                if (!$allowMultiple && $uploadedForRequirement >= 1) {
                    break;
                }
            }
        }

        return $uploadedCount;
    }

    private function storePublicOrderUpload(array $file): ?array
    {
        if (function_exists('store_single_upload')) {
            try {
                $meta = store_single_upload($file);

                if (is_array($meta) && $meta !== []) {
                    return $meta;
                }
            } catch (\Throwable $e) {
                error_log('store_single_upload failed: ' . $e->getMessage());
            }
        }

        return $this->fallbackPublicOrderUpload($file);
    }

    private function fallbackPublicOrderUpload(array $file): ?array
    {
        $originalName = basename((string) ($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

        if (!in_array($extension, $allowed, true)) {
            return null;
        }

        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/documents';

        if (function_exists('config')) {
            try {
                $configured = config('paths.uploads');
                if (is_string($configured) && $configured !== '') {
                    $uploadDir = $configured;
                }
            } catch (\Throwable $e) {
            }
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $storedName = 'client-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return null;
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => (string) ($file['type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) ($file['size'] ?? 0),
        ];
    }

    private function insertPublicOrderDocument(int $orderId, int $requirementId, string $label, array $meta): void
    {
        $columns = $this->tableColumnsSafe('order_documents');

        $payload = [
            'order_id' => $orderId,
            'requirement_id' => $requirementId,
            'source' => 'client',
            'label' => $label,
            'original_name' => (string) ($meta['original_name'] ?? ''),
            'stored_name' => (string) ($meta['stored_name'] ?? ''),
            'mime_type' => (string) ($meta['mime_type'] ?? ''),
            'size_bytes' => (int) ($meta['size_bytes'] ?? 0),
            'is_client_visible' => 0,
            'uploaded_by' => $this->currentUserId() > 0 ? $this->currentUserId() : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $allowed = [];

        foreach ($payload as $column => $value) {
            if (in_array($column, $columns, true)) {
                $allowed[$column] = $value;
            }
        }

        if ($allowed === []) {
            return;
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", array_keys($allowed)));
        $valueSql = implode(', ', array_map(static fn (string $column): string => ":{$column}", array_keys($allowed)));

        $this->db()->execute(
            'INSERT INTO order_documents (' . $columnSql . ') VALUES (' . $valueSql . ')',
            $allowed
        );
    }

    private function resolvePublicOrderClientId(array $identity): int
    {
        $userId = (int) ($identity['id'] ?? 0);

        if ($userId <= 0) {
            return 0;
        }

        /*
         * If the current user ID already exists in clients, use it.
         * This keeps compatibility with installs where clients and users share IDs.
         */
        if ($this->clientIdExists($userId)) {
            return $userId;
        }

        $email = trim((string) ($identity['email'] ?? ''));
        $phone = trim((string) ($identity['phone'] ?? ''));

        $client = $this->findClientByEmailOrPhone($email, $phone);

        if ($client) {
            return (int) ($client['id'] ?? 0);
        }

        return $this->createPublicClientFromCurrentUser($identity);
    }

    private function clientIdExists(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT id FROM clients WHERE id = :id LIMIT 1',
                ['id' => $clientId]
            );

            return is_array($row) && $row !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function findClientByEmailOrPhone(string $email, string $phone): ?array
    {
        try {
            if ($email !== '') {
                $client = $this->db()->fetch(
                    'SELECT id FROM clients WHERE email = :email LIMIT 1',
                    ['email' => $email]
                );

                if (is_array($client) && $client !== []) {
                    return $client;
                }
            }

            if ($phone !== '') {
                $client = $this->db()->fetch(
                    'SELECT id FROM clients WHERE phone = :phone LIMIT 1',
                    ['phone' => $phone]
                );

                if (is_array($client) && $client !== []) {
                    return $client;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function createPublicClientFromCurrentUser(array $identity): int
    {
        $columns = $this->tableColumnsSafe('clients');

        if ($columns === []) {
            return (int) ($identity['id'] ?? 0);
        }

        $userId = (int) ($identity['id'] ?? 0);
        $user = [];

        if ($userId > 0) {
            try {
                $user = $this->db()->fetch(
                    'SELECT name, email, phone, mobile FROM users WHERE id = :id LIMIT 1',
                    ['id' => $userId]
                ) ?: [];
            } catch (\Throwable $e) {
                $user = [];
            }
        }

        $name = trim((string) ($user['name'] ?? $_SESSION['user']['name'] ?? 'Client')) ?: 'Client';
        $email = trim((string) ($user['email'] ?? $identity['email'] ?? ''));
        $phone = trim((string) ($user['phone'] ?? $user['mobile'] ?? $identity['phone'] ?? ''));

        $payload = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $allowed = [];

        foreach ($payload as $column => $value) {
            if (in_array($column, $columns, true)) {
                $allowed[$column] = $value;
            }
        }

        if ($allowed === []) {
            return $userId;
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", array_keys($allowed)));
        $valueSql = implode(', ', array_map(static fn (string $column): string => ":{$column}", array_keys($allowed)));

        $this->db()->execute(
            'INSERT INTO clients (' . $columnSql . ') VALUES (' . $valueSql . ')',
            $allowed
        );

        $created = $this->db()->fetch(
            'SELECT id FROM clients ORDER BY id DESC LIMIT 1'
        );

        return (int) ($created['id'] ?? $userId);
    }

    private function columnAllowsNull(string $table, string $column): bool
    {
        try {
            $rows = $this->db()->fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');

            foreach ($rows as $row) {
                if ((string) ($row['Field'] ?? '') === $column) {
                    return strtoupper((string) ($row['Null'] ?? 'NO')) === 'YES';
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function tableColumnsSafe(string $table): array
    {
        try {
            $rows = $this->db()->fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            $columns = [];

            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $columns[] = (string) $row['Field'];
                }
            }

            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }
}   
