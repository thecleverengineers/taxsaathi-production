<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Role extends BaseModel
{
    protected static string $table = 'roles';

    public static function allOrdered(): array
    {
        return static::db()->fetchAll('SELECT * FROM roles ORDER BY is_system DESC, name ASC');
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::db()->fetch('SELECT * FROM roles WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
    }
}
