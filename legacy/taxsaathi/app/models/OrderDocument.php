<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class OrderDocument extends BaseModel
{
    protected static string $table = 'order_documents';

    public static function forOrder(int $orderId, ?string $source = null): array
    {
        $sql = 'SELECT od.*, sr.label AS requirement_label, u.name AS uploaded_by_name
                FROM order_documents od
                LEFT JOIN service_requirements sr ON sr.id = od.requirement_id
                LEFT JOIN users u ON u.id = od.uploaded_by
                WHERE od.order_id = :order_id';
        $params = ['order_id' => $orderId];

        if ($source !== null) {
            $sql .= ' AND od.source = :source';
            $params['source'] = $source;
        }

        $sql .= ' ORDER BY od.id DESC';
        return static::db()->fetchAll($sql, $params);
    }

    public static function findForAdmin(int $id): ?array
    {
        return static::db()->fetch(
            'SELECT od.*, o.status AS order_status
             FROM order_documents od
             INNER JOIN orders o ON o.id = od.order_id
             WHERE od.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public static function forClientDownload(int $orderId, int $userId): array
    {
        return static::db()->fetchAll(
            'SELECT od.*
             FROM order_documents od
             INNER JOIN orders o ON o.id = od.order_id
             INNER JOIN users u ON u.client_id = o.client_id
             WHERE od.order_id = :order_id
               AND u.id = :user_id
               AND od.source = "admin_output"
               AND od.is_client_visible = 1
               AND o.status = "completed"
             ORDER BY od.id DESC',
            ['order_id' => $orderId, 'user_id' => $userId]
        );
    }

    public static function findForClientDownload(int $documentId, int $userId): ?array
    {
        return static::db()->fetch(
            'SELECT od.*
             FROM order_documents od
             INNER JOIN orders o ON o.id = od.order_id
             INNER JOIN users u ON u.client_id = o.client_id
             WHERE od.id = :id
               AND u.id = :user_id
               AND od.source = "admin_output"
               AND od.is_client_visible = 1
               AND o.status = "completed"
             LIMIT 1',
            ['id' => $documentId, 'user_id' => $userId]
        );
    }
}
