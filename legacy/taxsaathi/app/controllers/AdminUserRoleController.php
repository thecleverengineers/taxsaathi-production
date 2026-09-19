<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__) . '/helpers/user_roles_rbac_helper.php';

final class AdminUserRoleController extends Controller
{
    /**
     * GET /admin/users/roles
     * Lists users with their assigned roles from user_roles only.
     */
    public function index(): void
    {
        require_auth();
        rbac_require_permission('users.roles.manage');

        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(u.name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $where[] = 'u.is_active = :status';
            $params['status'] = (int) $status;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $users = rbac_fetch_all(
            "SELECT
                u.id,
                u.name,
                u.email,
                u.phone,
                u.is_active,
                u.created_at,
                COUNT(ur.role_id) AS role_count,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                GROUP_CONCAT(r.slug ORDER BY r.name SEPARATOR ',') AS role_slugs
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             {$whereSql}
             GROUP BY u.id, u.name, u.email, u.phone, u.is_active, u.created_at
             ORDER BY u.id DESC",
            $params
        );

        $this->view('admin/user_roles/index', [
            'title' => 'User Roles & Permissions',
            'users' => $users,
            'filters' => ['q' => $q, 'status' => $status],
        ], 'layouts/dashboard');
    }

    /**
     * GET /admin/users/roles/edit?id=USER_ID
     */
    public function edit(): void
    {
        require_auth();
        rbac_require_permission('users.roles.manage');

        $userId = (int) ($_GET['id'] ?? 0);
        if ($userId <= 0) {
            $this->failRedirect('Invalid user.', 'admin/users/roles');
            return;
        }

        $user = $this->findUser($userId);
        if (!$user) {
            $this->failRedirect('User not found.', 'admin/users/roles');
            return;
        }

        $roles = $this->allRoles();
        $assignedRoleIds = $this->assignedRoleIds($userId);
        $effective = rbac_context($userId, true);

        $this->view('admin/user_roles/edit', [
            'title' => 'Edit User Roles',
            'userRow' => $user,
            'roles' => $roles,
            'assignedRoleIds' => $assignedRoleIds,
            'effectivePermissions' => $effective['permissions'] ?? [],
            'effectiveRoles' => $effective['roles'] ?? [],
        ], 'layouts/dashboard');
    }

    /**
     * POST /admin/users/roles/update
     */
    public function update(): void
    {
        require_auth();
        rbac_require_permission('users.roles.manage');
        $this->verifyCsrfIfAvailable();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $roleIds = $_POST['role_ids'] ?? [];
        $roleIds = is_array($roleIds) ? $roleIds : [$roleIds];
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn(int $id): bool => $id > 0)));

        if ($userId <= 0 || !$this->findUser($userId)) {
            $this->failRedirect('User not found.', 'admin/users/roles');
            return;
        }

        $validRoleIds = array_map(static fn(array $r): int => (int) $r['id'], $this->allRoles());
        $roleIds = array_values(array_intersect($roleIds, $validRoleIds));

        $this->replaceUserRoles($userId, $roleIds);

        if (function_exists('flash')) {
            flash('success', 'User roles updated successfully.');
        }

        $this->go('admin/users/roles/edit?id=' . $userId);
    }

    /**
     * GET /admin/roles/permissions
     */
    public function permissions(): void
    {
        require_auth();
        rbac_require_permission('roles.manage');

        $roleId = (int) ($_GET['role_id'] ?? 0);
        $roles = $this->allRoles();
        $selectedRole = null;

        if ($roleId <= 0 && $roles !== []) {
            $roleId = (int) ($roles[0]['id'] ?? 0);
        }

        foreach ($roles as $role) {
            if ((int) ($role['id'] ?? 0) === $roleId) {
                $selectedRole = $role;
                break;
            }
        }

        $catalog = $this->permissionCatalog();
        $selectedPermissions = $selectedRole ? rbac_normalize_json_list($selectedRole['permissions_json'] ?? '') : [];

        $grouped = [];
        foreach ($catalog as $permission) {
            $group = trim((string) ($permission['group_key'] ?? 'General')) ?: 'General';
            $grouped[$group][] = $permission;
        }

        $this->view('admin/user_roles/permissions', [
            'title' => 'Role Permission Mapping',
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'selectedPermissions' => $selectedPermissions,
            'permissionGroups' => $grouped,
        ], 'layouts/dashboard');
    }

    /**
     * POST /admin/roles/permissions/update
     */
    public function updatePermissions(): void
    {
        require_auth();
        rbac_require_permission('roles.manage');
        $this->verifyCsrfIfAvailable();

        $roleId = (int) ($_POST['role_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $permissions = array_values(array_unique(array_filter(array_map(static fn($p): string => trim((string) $p), $permissions), static fn(string $p): bool => $p !== '')));
        sort($permissions);

        if ($roleId <= 0 || !$this->findRole($roleId)) {
            $this->failRedirect('Role not found.', 'admin/roles/permissions');
            return;
        }

        rbac_execute(
            'UPDATE roles SET permissions_json = :permissions_json, updated_at = NOW() WHERE id = :id',
            [
                'id' => $roleId,
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );

        if (function_exists('flash')) {
            flash('success', 'Role permissions updated successfully.');
        }

        $this->go('admin/roles/permissions?role_id=' . $roleId);
    }

    private function findUser(int $userId): ?array
    {
        return rbac_fetch_one(
            'SELECT id, name, email, phone, is_active, created_at FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
    }

    private function findRole(int $roleId): ?array
    {
        return rbac_fetch_one(
            'SELECT id, name, slug, permissions_json FROM roles WHERE id = :id LIMIT 1',
            ['id' => $roleId]
        );
    }

    private function allRoles(): array
    {
        return rbac_fetch_all('SELECT id, name, slug, permissions_json, is_system FROM roles ORDER BY is_system DESC, name ASC');
    }

    private function assignedRoleIds(int $userId): array
    {
        $rows = rbac_fetch_all('SELECT role_id FROM user_roles WHERE user_id = :user_id ORDER BY role_id ASC', ['user_id' => $userId]);
        return array_map(static fn(array $row): int => (int) $row['role_id'], $rows);
    }

    private function replaceUserRoles(int $userId, array $roleIds): void
    {
        $db = rbac_db();
        $transaction = $this->transactionAdapter($db);

        try {
            $transaction['begin']();

            rbac_execute('DELETE FROM user_roles WHERE user_id = :user_id', ['user_id' => $userId]);

            foreach ($roleIds as $roleId) {
                rbac_execute(
                    'INSERT INTO user_roles (user_id, role_id, created_at, updated_at) VALUES (:user_id, :role_id, NOW(), NOW())',
                    ['user_id' => $userId, 'role_id' => $roleId]
                );
            }

            $transaction['commit']();
        } catch (Throwable $e) {
            $transaction['rollback']();
            throw $e;
        }
    }

    private function permissionCatalog(): array
    {
        if (rbac_table_exists('permission_catalog')) {
            return rbac_fetch_all(
                'SELECT id, permission, label, group_key, description, sort_order
                 FROM permission_catalog
                 WHERE is_active = 1
                 ORDER BY group_key ASC, sort_order ASC, permission ASC'
            );
        }

        return [];
    }

    private function transactionAdapter(mixed $db): array
    {
        $pdo = null;

        if ($db instanceof \PDO) {
            $pdo = $db;
        } elseif (is_object($db)) {
            foreach (['pdo', 'getPdo', 'connection', 'getConnection'] as $method) {
                if (method_exists($db, $method)) {
                    try {
                        $candidate = $db->{$method}();
                        if ($candidate instanceof \PDO) {
                            $pdo = $candidate;
                            break;
                        }
                    } catch (Throwable $e) {
                    }
                }
            }

            if (!$pdo && isset($db->pdo) && $db->pdo instanceof \PDO) {
                $pdo = $db->pdo;
            }
        }

        if ($pdo instanceof \PDO) {
            return [
                'begin' => static function () use ($pdo): void { if (!$pdo->inTransaction()) { $pdo->beginTransaction(); } },
                'commit' => static function () use ($pdo): void { if ($pdo->inTransaction()) { $pdo->commit(); } },
                'rollback' => static function () use ($pdo): void { if ($pdo->inTransaction()) { $pdo->rollBack(); } },
            ];
        }

        return [
            'begin' => static function (): void {},
            'commit' => static function (): void {},
            'rollback' => static function (): void {},
        ];
    }

    private function verifyCsrfIfAvailable(): void
    {
        if (function_exists('verify_csrf')) {
            verify_csrf();
        }
    }

    private function failRedirect(string $message, string $path): void
    {
        if (function_exists('flash')) {
            flash('error', $message);
        }

        $this->go($path);
    }

    private function go(string $path): void
    {
        if (function_exists('redirect')) {
            redirect($path);
            return;
        }

        header('Location: ' . (function_exists('base_url') ? base_url($path) : '/' . ltrim($path, '/')));
        exit;
    }
}
