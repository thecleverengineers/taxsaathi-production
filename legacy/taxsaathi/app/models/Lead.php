<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Lead extends BaseModel
{
    protected static string $table = 'leads';

    public static function latestDetailed(int $limit = 10): array
    {
        return static::db()->fetchAll(
            'SELECT l.*, u.name AS assigned_to_name
             FROM leads l
             LEFT JOIN users u ON u.id = l.assigned_to
             ORDER BY l.id DESC
             LIMIT ' . (int) $limit
        );
    }
}
