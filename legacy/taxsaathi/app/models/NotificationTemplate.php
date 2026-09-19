<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class NotificationTemplate extends BaseModel
{
    protected static string $table = 'notification_templates';

    public static function findByEvent(string $eventKey): ?array
    {
        return static::db()->fetch('SELECT * FROM notification_templates WHERE event_key = :event_key LIMIT 1', ['event_key' => $eventKey]);
    }
}
