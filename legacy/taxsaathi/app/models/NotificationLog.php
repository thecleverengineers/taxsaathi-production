<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class NotificationLog extends BaseModel
{
    protected static string $table = 'notification_logs';

    public static function latest(int $limit = 25): array
    {
        return static::db()->fetchAll('SELECT * FROM notification_logs ORDER BY id DESC LIMIT ' . (int) $limit);
    }
}
