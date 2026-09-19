<?php
declare(strict_types=1);

/**
 * Dynamic user_roles RBAC helper.
 * Load this before controllers/views if your existing app does not already provide a multi-role can().
 * Authorization is valid only when the session user id has a matching
 * user_roles.user_id assignment.
 */

if (!function_exists('rbac_db')) {
    function rbac_db(): mixed
    {
        if (function_exists('app')) {
            try {
                return app('db');
            } catch (Throwable $e) {}
        }
        return null;
    }
}

if (!function_exists('rbac_fetch_all')) {
    function rbac_fetch_all(string $sql, array $params = []): array
    {
        $db = rbac_db();
        if (is_object($db) && method_exists($db, 'fetchAll')) {
            return $db->fetchAll($sql, $params) ?: [];
        }
        if ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return [];
    }
}

if (!function_exists('rbac_current_user_id')) {
    function rbac_current_user_id(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $user = function_exists('auth_user') ? (auth_user() ?: []) : [];
        return (int) (
            $_SESSION['user_id']
            ?? $_SESSION['auth_user_id']
            ?? $user['id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? 0
        );
    }
}

if (!function_exists('rbac_decode_permissions')) {
    function rbac_decode_permissions(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        $raw = trim((string) $raw);
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
        if (str_contains($raw, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }
        return [$raw];
    }
}

if (!function_exists('user_effective_permissions')) {
    function user_effective_permissions(?int $userId = null): array
    {
        $userId = $userId ?: rbac_current_user_id();
        if ($userId <= 0) return [];

        $rows = rbac_fetch_all(
            'SELECT DISTINCT r.permissions_json
             FROM user_roles ur
             INNER JOIN users u ON u.id = ur.user_id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND COALESCE(u.is_active, 1) = 1
               AND r.id IS NOT NULL',
            ['user_id' => $userId]
        );

        $set = [];
        foreach ($rows as $row) {
            foreach (rbac_decode_permissions($row['permissions_json'] ?? '[]') as $permission) {
                $permission = trim((string) $permission);
                if ($permission !== '') $set[$permission] = $permission;
            }
        }

        ksort($set);
        return array_values($set);
    }
}

if (!function_exists('user_has_permission')) {
    function user_has_permission(string $permission, ?int $userId = null): bool
    {
        $permission = trim($permission);
        if ($permission === '') return false;

        $permissions = user_effective_permissions($userId);
        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        $segments = explode('.', $permission);
        while (count($segments) > 1) {
            array_pop($segments);
            if (in_array(implode('.', $segments) . '.*', $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return user_has_permission($permission);
    }
}

if (!function_exists('hydrateUserRolesForSession')) {
    function hydrateUserRolesForSession(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) return $user;

        $rows = rbac_fetch_all(
            'SELECT r.id, r.name, r.slug, r.permissions_json
             FROM user_roles ur
             INNER JOIN users u ON u.id = ur.user_id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND COALESCE(u.is_active, 1) = 1
             ORDER BY r.id ASC',
            ['user_id' => $userId]
        );

        $roleIds = [];
        $roleSlugs = [];
        $permissionSet = [];

        foreach ($rows as &$row) {
            $row['permissions'] = rbac_decode_permissions($row['permissions_json'] ?? '[]');
            $roleIds[] = (int) ($row['id'] ?? 0);
            $roleSlugs[] = (string) ($row['slug'] ?? '');
            foreach ($row['permissions'] as $permission) {
                $permissionSet[$permission] = $permission;
            }
        }
        unset($row);

        $user['roles'] = $rows;
        $user['role_ids'] = array_values(array_filter($roleIds));
        $user['role_slugs'] = array_values(array_filter($roleSlugs));
        $user['permissions'] = array_values($permissionSet);
        $user['permissions_json'] = json_encode(array_values($permissionSet), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $user;
    }
}
