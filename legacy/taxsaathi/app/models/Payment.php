<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Payment extends BaseModel
{
    protected static string $table = 'payments';

    public static function forOrder(int $orderId): array
    {
        return static::db()->fetchAll(
            'SELECT p.*, u.name AS received_by_name
             FROM payments p
             LEFT JOIN users u ON u.id = p.received_by
             WHERE p.order_id = :order_id
             ORDER BY p.id DESC',
            ['order_id' => $orderId]
        );
    }
}
