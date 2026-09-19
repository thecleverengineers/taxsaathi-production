<?php

declare(strict_types=1);

/**
 * User Roles RBAC Helper - database-backed role and sidebar access.
 * -----------------------------------------------------------------------------
 * IMPORTANT:
 * - The authenticated session user id is matched to user_roles.user_id.
 * - Only the matching user_roles.role_id values authorize the account.
 * - users.role_id is not an authorization source or a fallback.
 * - Effective permissions are the merged roles.permissions_json values.
 * - Sidebar visibility is database-driven from sidebar_menus.
 */

if (!function_exists('rbac_db')) {
    function rbac_db(): mixed
    {
        static $db = null;

        if ($db !== null) {
            return $db;
        }

        if (function_exists('app')) {
            try {
                $candidate = app('db');
                if ($candidate) {
                    return $db = $candidate;
                }
            } catch (Throwable $e) {
            }
        }

        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $db = $GLOBALS['pdo'];
        }

        $databaseClass = '\\App\\Core\\Database';
        if (class_exists($databaseClass)) {
            $config = [];

            if (function_exists('config')) {
                foreach (['db', 'database'] as $key) {
                    try {
                        $raw = config($key);
                        if (is_array($raw) && $raw !== []) {
                            $config = $raw;
                            break;
                        }
                    } catch (Throwable $e) {
                    }
                }
            }

            foreach ([
                dirname(__DIR__, 2) . '/config/config.php',
                dirname(__DIR__, 2) . '/config/database.php',
                dirname(__DIR__) . '/config/config.php',
                dirname(__DIR__) . '/config/database.php',
            ] as $file) {
                if ($config !== [] || !is_file($file)) {
                    continue;
                }

                try {
                    $raw = require $file;
                    if (is_array($raw)) {
                        $config = $raw['db'] ?? $raw['database'] ?? $raw;
                    }
                } catch (Throwable $e) {
                }
            }

            if ($config !== []) {
                return $db = new $databaseClass($config);
            }
        }

        throw new RuntimeException('RBAC database connection not found. Ensure app(\'db\') or $GLOBALS[\'pdo\'] is available.');
    }
}

if (!function_exists('rbac_fetch_all')) {
    function rbac_fetch_all(string $sql, array $params = []): array
    {
        $db = rbac_db();

        if ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':'), $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (method_exists($db, 'fetchAll')) {
            return $db->fetchAll($sql, $params) ?: [];
        }

        if (method_exists($db, 'query')) {
            $result = $db->query($sql, $params);
            if (is_array($result)) {
                return $result;
            }
        }

        throw new RuntimeException('RBAC fetchAll is not supported by the configured DB adapter.');
    }
}

if (!function_exists('rbac_fetch_one')) {
    function rbac_fetch_one(string $sql, array $params = []): ?array
    {
        $db = rbac_db();

        if ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':'), $value);
            }
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        }

        if (method_exists($db, 'fetch')) {
            $row = $db->fetch($sql, $params);
            return is_array($row) ? $row : null;
        }

        $rows = rbac_fetch_all($sql, $params);
        return $rows[0] ?? null;
    }
}

if (!function_exists('rbac_execute')) {
    function rbac_execute(string $sql, array $params = []): bool
    {
        $db = rbac_db();

        if ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':'), $value);
            }
            return $stmt->execute();
        }

        if (method_exists($db, 'execute')) {
            return (bool) $db->execute($sql, $params);
        }

        if (method_exists($db, 'query')) {
            $db->query($sql, $params);
            return true;
        }

        throw new RuntimeException('RBAC execute is not supported by the configured DB adapter.');
    }
}

if (!function_exists('rbac_table_exists')) {
    function rbac_table_exists(string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        if ($table === '') {
            return false;
        }

        try {
            $row = rbac_fetch_one(
                'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                ['table' => $table]
            );
            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('rbac_column_exists')) {
    function rbac_column_exists(string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?: '';

        if ($table === '' || $column === '') {
            return false;
        }

        try {
            $row = rbac_fetch_one(
                'SELECT COUNT(*) AS total
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column',
                [
                    'table' => $table,
                    'column' => $column,
                ]
            );

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('rbac_current_user')) {
    function rbac_current_user(): array
    {
        if (function_exists('auth_user')) {
            try {
                $user = auth_user();
                if (is_array($user)) {
                    return $user;
                }
            } catch (Throwable $e) {
            }
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (is_array($_SESSION['auth_user'] ?? null)) {
            return $_SESSION['auth_user'];
        }

        if (is_array($_SESSION['user'] ?? null)) {
            return $_SESSION['user'];
        }

        return [];
    }
}

if (!function_exists('rbac_current_user_id')) {
    function rbac_current_user_id(): int
    {
        $user = rbac_current_user();

        $candidates = [
            $_SESSION['user_id'] ?? null,
            $_SESSION['auth_user_id'] ?? null,
            $user['id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['user']['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $id = (int) $candidate;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }
}

if (!function_exists('rbac_normalize_json_list')) {
    function rbac_normalize_json_list(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $value), static fn(string $v): bool => $v !== ''));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                if (array_is_list($decoded)) {
                    return array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $decoded), static fn(string $v): bool => $v !== ''));
                }

                return array_values(array_filter(array_map(static fn($v): string => trim((string) $v), array_values($decoded)), static fn(string $v): bool => $v !== ''));
            }
        }

        if (str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $v): bool => $v !== ''));
        }

        return [$value];
    }
}

if (!function_exists('rbac_context')) {
    function rbac_context(?int $userId = null, bool $forceReload = false): array
    {
        static $cache = [];

        $userId = $userId !== null ? (int) $userId : rbac_current_user_id();

        if ($userId <= 0) {
            return [
                'user_id' => 0,
                'roles' => [],
                'role_ids' => [],
                'role_slugs' => [],
                'role_names' => [],
                'permissions' => [],
                'permission_set' => [],
                'is_multi_role' => false,
            ];
        }

        if (!$forceReload && isset($cache[$userId])) {
            return $cache[$userId];
        }

        $rows = [];

        /*
         * Validate the session identity against the assignment table before
         * loading roles. An account without a user_roles row has no active
         * RBAC context, even when users.role_id contains a value.
         */
        if (rbac_table_exists('user_roles') && rbac_table_exists('users') && rbac_table_exists('roles')) {
            try {
                $rows = rbac_fetch_all(
                    "SELECT DISTINCT
                        r.id AS role_id,
                        r.name AS role_name,
                        r.slug AS role_slug,
                        r.permissions_json
                     FROM user_roles ur
                     INNER JOIN users u ON u.id = ur.user_id
                     INNER JOIN roles r ON r.id = ur.role_id
                     WHERE ur.user_id = :user_id
                       AND COALESCE(u.is_active, 1) = 1
                     ORDER BY r.id ASC",
                    ['user_id' => $userId]
                );
            } catch (Throwable $e) {
                $rows = [];
            }
        }

        $roles = [];
        $roleIds = [];
        $roleSlugs = [];
        $roleNames = [];
        $permissionSet = [];

        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            $roleSlug = strtolower(trim((string) ($row['role_slug'] ?? '')));
            $roleName = trim((string) ($row['role_name'] ?? ''));

            if ($roleId <= 0) {
                continue;
            }

            $roles[] = [
                'id' => $roleId,
                'name' => $roleName,
                'slug' => $roleSlug,
            ];
            $roleIds[$roleId] = $roleId;

            if ($roleSlug !== '') {
                $roleSlugs[$roleSlug] = $roleSlug;
            }

            if ($roleName !== '') {
                $roleNames[$roleName] = $roleName;
            }

            foreach (rbac_normalize_json_list($row['permissions_json'] ?? '') as $permission) {
                $permission = trim($permission);
                if ($permission !== '') {
                    $permissionSet[$permission] = true;
                }
            }
        }

        $permissions = array_keys($permissionSet);
        sort($permissions);

        return $cache[$userId] = [
            'user_id' => $userId,
            'roles' => $roles,
            'role_ids' => array_values($roleIds),
            'role_slugs' => array_values($roleSlugs),
            'role_names' => array_values($roleNames),
            'permissions' => $permissions,
            'permission_set' => $permissionSet,
            'is_multi_role' => count($roles) > 1,
        ];
    }
}

if (!function_exists('rbac_can')) {
    function rbac_can(string $permission, ?int $userId = null): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        $set = rbac_context($userId)['permission_set'] ?? [];

        if (isset($set['*']) || isset($set[$permission])) {
            return true;
        }

        $segments = explode('.', $permission);
        while (count($segments) > 1) {
            array_pop($segments);
            $wildcard = implode('.', $segments) . '.*';
            if (isset($set[$wildcard])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('rbac_can_any')) {
    function rbac_can_any(array $permissions, ?int $userId = null): bool
    {
        foreach ($permissions as $permission) {
            if (rbac_can((string) $permission, $userId)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('rbac_can_all')) {
    function rbac_can_all(array $permissions, ?int $userId = null): bool
    {
        foreach ($permissions as $permission) {
            if (!rbac_can((string) $permission, $userId)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return rbac_can($permission);
    }
}

if (!function_exists('rbac_require_permission')) {
    function rbac_require_permission(string $permission, string $redirectTo = 'dashboard'): void
    {
        if (!rbac_can($permission)) {
            if (function_exists('flash')) {
                flash('error', 'You do not have permission to access this section.');
            }

            if (function_exists('redirect')) {
                redirect($redirectTo);
                return;
            }

            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}

if (!function_exists('rbac_sidebar_items')) {
    function rbac_sidebar_items(?int $userId = null): array
    {
        $userId = $userId !== null ? (int) $userId : rbac_current_user_id();

        if ($userId <= 0) {
            return [];
        }

        $context = rbac_context($userId);
        $assignedRoleIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($context['role_ids'] ?? null) ? $context['role_ids'] : []),
            static fn (int $roleId): bool => $roleId > 0
        )));

        /* No user_roles assignment means no sidebar, regardless of users.role_id. */
        if ($assignedRoleIds === [] || !rbac_table_exists('sidebar_menus')) {
            return [];
        }

        $hasRoleMapping = rbac_column_exists('sidebar_menus', 'required_roles');
        $requiredRolesSelect = $hasRoleMapping
            ? 'required_roles'
            : 'NULL AS required_roles';

        $rows = rbac_fetch_all(
            "SELECT
                id,
                label,
                href,
                icon,
                section,
                required_permission,
                required_any_permissions,
                required_all_permissions,
                {$requiredRolesSelect},
                sort_order,
                is_active
             FROM sidebar_menus
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );

        $items = [];

        foreach ($rows as $row) {
            $requiredRoles = rbac_normalize_json_list($row['required_roles'] ?? '');

            if ($requiredRoles !== []) {
                $requiredRoleIds = [];
                $hasWildcard = false;
                $roleAliases = [
                    'admin' => 1,
                    'administrator' => 1,
                    'administrators' => 1,
                    'super-admin' => 1,
                    'superadmin' => 1,
                    'manager' => 2,
                    'executive' => 3,
                    'staff' => 3,
                    'partner' => 4,
                    'partners' => 4,
                    'client' => 5,
                    'customer' => 5,
                    'user' => 5,
                ];

                foreach ($requiredRoles as $requiredRole) {
                    $requiredRole = strtolower(trim((string) $requiredRole));

                    if ($requiredRole === '*') {
                        $hasWildcard = true;
                        continue;
                    }

                    if (preg_match('/^\d+$/', $requiredRole) === 1) {
                        $roleId = (int) $requiredRole;
                        if ($roleId > 0) {
                            $requiredRoleIds[$roleId] = $roleId;
                        }
                        continue;
                    }

                    $requiredRole = str_replace([' ', '_'], '-', $requiredRole);
                    if (isset($roleAliases[$requiredRole])) {
                        $requiredRoleIds[$roleAliases[$requiredRole]] = $roleAliases[$requiredRole];
                    }
                }

                $requiredRoleIds = array_values($requiredRoleIds);

                if (
                    !$hasWildcard
                    && ($requiredRoleIds === [] || array_intersect($requiredRoleIds, $assignedRoleIds) === [])
                ) {
                    continue;
                }
            }

            $requiredPermission = trim((string) ($row['required_permission'] ?? ''));
            $requiredAny = rbac_normalize_json_list($row['required_any_permissions'] ?? '');
            $requiredAll = rbac_normalize_json_list($row['required_all_permissions'] ?? '');

            $allowed = true;

            if ($requiredPermission !== '' && !rbac_can($requiredPermission, $userId)) {
                $allowed = false;
            }

            if ($allowed && $requiredAny !== [] && !rbac_can_any($requiredAny, $userId)) {
                $allowed = false;
            }

            if ($allowed && $requiredAll !== [] && !rbac_can_all($requiredAll, $userId)) {
                $allowed = false;
            }

            if (!$allowed) {
                continue;
            }

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'href' => function_exists('base_url') ? base_url((string) ($row['href'] ?? '')) : '/' . ltrim((string) ($row['href'] ?? ''), '/'),
                'icon' => (string) ($row['icon'] ?? 'external'),
                'section' => trim((string) ($row['section'] ?? 'Menu')) ?: 'Menu',
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $items;
    }
}
