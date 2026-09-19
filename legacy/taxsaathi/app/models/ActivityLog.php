<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class ActivityLog extends BaseModel
{
    protected static string $table = 'activity_logs';

    public static function recent(int $limit = 20): array
    {
        return static::db()->fetchAll(
            'SELECT al.*, u.name AS user_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.id DESC
             LIMIT ' . (int) $limit
        );
    }

    public static function forOrder(int $orderId): array
    {
        return static::db()->fetchAll(
            'SELECT al.*, u.name AS user_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.order_id = :order_id
             ORDER BY al.id DESC',
            ['order_id' => $orderId]
        );
    }
}
