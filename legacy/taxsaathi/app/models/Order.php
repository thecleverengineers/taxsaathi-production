<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Order extends BaseModel
{
    protected static string $table = 'orders';

    public static function nextOrderNo(): string
    {
        $lastId = (int) static::db()->scalar('SELECT COALESCE(MAX(id), 0) FROM orders');
        return 'TSO-' . date('Y') . '-' . str_pad((string) ($lastId + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function allDetailed(array $filters = []): array
    {
        $sql = 'SELECT o.*, s.title AS service_title, s.slug AS service_slug, c.name AS client_name, c.phone AS client_phone, u.name AS approved_by_name
                FROM orders o
                INNER JOIN services s ON s.id = o.service_id
                INNER JOIN clients c ON c.id = o.client_id
                LEFT JOIN users u ON u.id = o.approved_by
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['service_id'])) {
            $sql .= ' AND o.service_id = :service_id';
            $params['service_id'] = (int) $filters['service_id'];
        }

        if (!empty($filters['q'])) {
            $sql .= ' AND (o.order_no LIKE :q OR c.name LIKE :q OR c.phone LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY o.id DESC';
        return static::db()->fetchAll($sql, $params);
    }

    public static function detail(int $id): ?array
    {
        return static::db()->fetch(
            'SELECT o.*, s.title AS service_title, s.slug AS service_slug, s.description AS service_description, s.filing_fee, c.name AS client_name, c.phone AS client_phone, c.email AS client_email, c.city AS client_city, u.name AS approved_by_name
             FROM orders o
             INNER JOIN services s ON s.id = o.service_id
             INNER JOIN clients c ON c.id = o.client_id
             LEFT JOIN users u ON u.id = o.approved_by
             WHERE o.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public static function forClientUser(int $userId): array
    {
        return static::db()->fetchAll(
            'SELECT o.*, s.title AS service_title
             FROM orders o
             INNER JOIN users u ON u.client_id = o.client_id
             INNER JOIN services s ON s.id = o.service_id
             WHERE u.id = :user_id
             ORDER BY o.id DESC',
            ['user_id' => $userId]
        );
    }

    public static function findForClientUser(int $orderId, int $userId): ?array
    {
        return static::db()->fetch(
            'SELECT o.*, s.title AS service_title, s.description AS service_description, c.name AS client_name, c.phone AS client_phone
             FROM orders o
             INNER JOIN users u ON u.client_id = o.client_id
             INNER JOIN services s ON s.id = o.service_id
             INNER JOIN clients c ON c.id = o.client_id
             WHERE o.id = :id AND u.id = :user_id
             LIMIT 1',
            ['id' => $orderId, 'user_id' => $userId]
        );
    }

    public static function latestDetailed(int $limit = 8): array
    {
        return static::db()->fetchAll(
            'SELECT o.*, s.title AS service_title, c.name AS client_name
             FROM orders o
             INNER JOIN services s ON s.id = o.service_id
             INNER JOIN clients c ON c.id = o.client_id
             ORDER BY o.id DESC
             LIMIT ' . (int) $limit
        );
    }

    public static function summaryByStatus(): array
    {
        return static::db()->fetchAll('SELECT status, COUNT(*) AS total, COALESCE(SUM(fee_amount),0) AS amount FROM orders GROUP BY status ORDER BY total DESC');
    }
}
