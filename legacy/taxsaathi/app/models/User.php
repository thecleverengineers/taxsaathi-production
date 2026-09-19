<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class User extends BaseModel
{
    protected static string $table = 'users';

    private static function withPermissions(?array $row): ?array
    {
        if (!$row) {
            return null;
        }

        $roleRows = [];

        /*
         * Keep the authenticated session aligned with the database-driven
         * sidebar and multi-role RBAC layer. Authorization comes only from
         * user_roles.user_id -> user_roles.role_id.
         */
        try {
            $roleRows = static::db()->fetchAll(
                'SELECT DISTINCT r.id, r.name, r.slug, r.permissions_json
                 FROM user_roles ur
                 INNER JOIN users u ON u.id = ur.user_id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                   AND COALESCE(u.is_active, 1) = 1
                 ORDER BY r.id ASC',
                ['user_id' => (int) ($row['id'] ?? 0)]
            ) ?: [];
        } catch (\Throwable $e) {
            $roleRows = [];
        }

        $permissionSet = [];
        $roleIds = [];
        $roleSlugs = [];
        $roleNames = [];

        foreach ($roleRows as &$roleRow) {
            $decodedPermissions = json_decode((string) ($roleRow['permissions_json'] ?? '[]'), true);
            $roleRow['permissions'] = is_array($decodedPermissions) ? $decodedPermissions : [];

            $roleId = (int) ($roleRow['id'] ?? 0);
            $roleSlug = trim((string) ($roleRow['slug'] ?? ''));
            $roleName = trim((string) ($roleRow['name'] ?? ''));

            if ($roleId > 0) {
                $roleIds[$roleId] = $roleId;
            }
            if ($roleSlug !== '') {
                $roleSlugs[$roleSlug] = $roleSlug;
            }
            if ($roleName !== '') {
                $roleNames[$roleName] = $roleName;
            }

            foreach ($roleRow['permissions'] as $permission) {
                $permission = trim((string) $permission);
                if ($permission !== '') {
                    $permissionSet[$permission] = $permission;
                }
            }
        }
        unset($roleRow);

        $row['roles'] = array_values($roleRows);
        $row['role_ids'] = array_values($roleIds);
        $row['role_slugs'] = array_values($roleSlugs);
        $row['role_names'] = array_values($roleNames);
        $row['permissions'] = array_values($permissionSet);
        $row['permissions_json'] = json_encode(
            array_values($permissionSet),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $row;
    }

    public static function findByEmail(string $email): ?array
    {
        return self::withPermissions(static::db()->fetch(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.permissions_json
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1',
            ['email' => strtolower(trim($email))]
        ));
    }

    public static function findByPhone(string $phone): ?array
    {
        return self::withPermissions(static::db()->fetch(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.permissions_json
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.phone = :phone
             LIMIT 1',
            ['phone' => $phone]
        ));
    }

    public static function findByIdDetailed(int $id): ?array
    {
        return self::withPermissions(static::db()->fetch(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.permissions_json
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $id]
        ));
    }

    public static function allWithRoles(): array
    {
        return static::db()->fetchAll(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug, c.name AS client_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN clients c ON c.id = u.client_id
             ORDER BY u.id DESC'
        );
    }

    public static function insert(array $data): int
    {
        static::db()->query(
            'INSERT INTO users (
                role_id,
                client_id,
                name,
                phone,
                email,
                password_hash,
                is_phone_verified,
                is_active,
                last_login_at,
                created_at,
                updated_at
            ) VALUES (
                :role_id,
                :client_id,
                :name,
                :phone,
                :email,
                :password_hash,
                :is_phone_verified,
                :is_active,
                :last_login_at,
                :created_at,
                :updated_at
            )',
            [
                'role_id'           => $data['role_id'] ?? null,
                'client_id'         => $data['client_id'] ?? null,
                'name'              => $data['name'] ?? '',
                'phone'             => $data['phone'] ?? '',
                'email'             => isset($data['email']) && $data['email'] !== null ? strtolower(trim((string) $data['email'])) : null,
                'password_hash'     => $data['password_hash'] ?? null,
                'is_phone_verified' => (int) ($data['is_phone_verified'] ?? 0),
                'is_active'         => (int) ($data['is_active'] ?? 1),
                'last_login_at'     => $data['last_login_at'] ?? null,
                'created_at'        => $data['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at'        => $data['updated_at'] ?? date('Y-m-d H:i:s'),
            ]
        );

        $userId = static::db()->lastInsertId();
        self::ensureUserRoleAssignment($userId, (int) ($data['role_id'] ?? 0));

        return $userId;
    }

    public static function update(int $id, array $data): bool
    {
        if ($id <= 0 || $data === []) {
            return false;
        }

        $allowed = [
            'role_id',
            'client_id',
            'name',
            'phone',
            'email',
            'password_hash',
            'is_phone_verified',
            'is_active',
            'last_login_at',
            'created_at',
            'updated_at',
        ];

        $set = [];
        $params = ['id' => $id];

        foreach ($data as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }

            if ($column === 'email' && $value !== null) {
                $value = strtolower(trim((string) $value));
            }

            $set[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        if ($set === []) {
            return false;
        }

        if (!array_key_exists('updated_at', $params)) {
            $set[] = 'updated_at = :updated_at';
            $params['updated_at'] = date('Y-m-d H:i:s');
        }

        $updated = static::db()->execute(
            'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id',
            $params
        );

        if ($updated && array_key_exists('role_id', $data)) {
            self::ensureUserRoleAssignment($id, (int) ($data['role_id'] ?? 0));
        }

        return $updated;
    }

    /**
     * Keep the primary role assignment represented in user_roles for newly
     * created or administratively edited users. Existing multi-role rows are
     * preserved; the dedicated user-role editor remains the source for adding
     * or removing additional assignments.
     */
    private static function ensureUserRoleAssignment(int $userId, int $roleId): void
    {
        if ($userId <= 0 || $roleId <= 0) {
            return;
        }

        try {
            static::db()->query(
                'INSERT INTO user_roles (user_id, role_id, created_at, updated_at)
                 VALUES (:user_id, :role_id, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
                [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ]
            );
        } catch (\Throwable $e) {
            /* The strict RBAC layer will deny access until user_roles exists. */
            error_log('Unable to sync user_roles assignment for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    public static function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $email = strtolower(trim($email));

        if ($excludeId !== null && $excludeId > 0) {
            $count = static::db()->scalar(
                'SELECT COUNT(*) FROM users WHERE email = :email AND id != :id',
                [
                    'email' => $email,
                    'id'    => $excludeId,
                ]
            );

            return (int) $count > 0;
        }

        $count = static::db()->scalar(
            'SELECT COUNT(*) FROM users WHERE email = :email',
            ['email' => $email]
        );

        return (int) $count > 0;
    }

    public static function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        if ($excludeId !== null && $excludeId > 0) {
            $count = static::db()->scalar(
                'SELECT COUNT(*) FROM users WHERE phone = :phone AND id != :id',
                [
                    'phone' => $phone,
                    'id'    => $excludeId,
                ]
            );

            return (int) $count > 0;
        }

        $count = static::db()->scalar(
            'SELECT COUNT(*) FROM users WHERE phone = :phone',
            ['phone' => $phone]
        );

        return (int) $count > 0;
    }
public static function allByRole(int $roleId): array
{
    return static::db()->fetchAll(
        'SELECT DISTINCT u.id, u.name, u.email, u.phone
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         WHERE ur.role_id = :role_id
         ORDER BY u.name ASC',
        ['role_id' => $roleId]
    ) ?: [];
}

public static function existsWithRole(int $id, int $roleId): bool
{
    return (bool) static::db()->fetch(
        'SELECT u.id
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         WHERE u.id = :id AND ur.role_id = :role_id
         LIMIT 1',
        [
            'id' => $id,
            'role_id' => $roleId,
        ]
    );
}
    public static function verifyPassword(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);

        if (!$user) {
            return null;
        }

        $hash = (string) ($user['password_hash'] ?? '');

        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        return $user;
    }
}
