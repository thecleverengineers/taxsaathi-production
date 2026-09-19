<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Service extends BaseModel
{
    protected static string $table = 'services';

    public static function active(): array
    {
        return static::db()->fetchAll('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id DESC');
    }

    public static function featured(): array
    {
        return static::db()->fetchAll('SELECT * FROM services WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC, id DESC');
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::db()->fetch('SELECT * FROM services WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
    }
}
