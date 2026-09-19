<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Client extends BaseModel
{
    protected static string $table = 'clients';

    public static function findById(int $id): ?array
    {
        return static::db()->fetch(
            'SELECT * FROM clients WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return static::db()->fetch(
            'SELECT * FROM clients
             WHERE email = :email
             LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
    }

    public static function findByPhone(string $phone): ?array
    {
        return static::db()->fetch(
            'SELECT * FROM clients
             WHERE phone = :phone
             LIMIT 1',
            ['phone' => $phone]
        );
    }

    public static function all(string $orderBy = 'id DESC'): array
    {
        return static::db()->fetchAll(
            "SELECT * FROM clients ORDER BY {$orderBy}"
        );
    }

    public static function insert(array $data): int
    {
        static::db()->query(
            'INSERT INTO clients (
                name,
                phone,
                email,
                client_type,
                company_name,
                gst_number,
                pan_number,
                city,
                status,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :phone,
                :email,
                :client_type,
                :company_name,
                :gst_number,
                :pan_number,
                :city,
                :status,
                :created_at,
                :updated_at
            )',
            [
                'name'         => $data['name'] ?? '',
                'phone'        => $data['phone'] ?? '',
                'email'        => isset($data['email']) && $data['email'] !== null ? strtolower(trim((string) $data['email'])) : null,
                'client_type'  => $data['client_type'] ?? 'individual',
                'company_name' => $data['company_name'] ?? null,
                'gst_number'   => $data['gst_number'] ?? null,
                'pan_number'   => $data['pan_number'] ?? null,
                'city'         => $data['city'] ?? null,
                'status'       => $data['status'] ?? 'active',
                'created_at'   => $data['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at'   => $data['updated_at'] ?? date('Y-m-d H:i:s'),
            ]
        );

        return static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        if ($id <= 0 || $data === []) {
            return false;
        }

        $allowed = [
            'name',
            'phone',
            'email',
            'client_type',
            'company_name',
            'gst_number',
            'pan_number',
            'city',
            'status',
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

        return static::db()->execute(
            'UPDATE clients SET ' . implode(', ', $set) . ' WHERE id = :id',
            $params
        );
    }

    public static function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $email = strtolower(trim($email));

        if ($excludeId !== null && $excludeId > 0) {
            $count = static::db()->scalar(
                'SELECT COUNT(*) FROM clients WHERE email = :email AND id != :id',
                [
                    'email' => $email,
                    'id'    => $excludeId,
                ]
            );

            return (int) $count > 0;
        }

        $count = static::db()->scalar(
            'SELECT COUNT(*) FROM clients WHERE email = :email',
            ['email' => $email]
        );

        return (int) $count > 0;
    }

    public static function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        if ($excludeId !== null && $excludeId > 0) {
            $count = static::db()->scalar(
                'SELECT COUNT(*) FROM clients WHERE phone = :phone AND id != :id',
                [
                    'phone' => $phone,
                    'id'    => $excludeId,
                ]
            );

            return (int) $count > 0;
        }

        $count = static::db()->scalar(
            'SELECT COUNT(*) FROM clients WHERE phone = :phone',
            ['phone' => $phone]
        );

        return (int) $count > 0;
    }
}