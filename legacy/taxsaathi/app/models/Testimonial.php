<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Testimonial extends BaseModel
{
    protected static string $table = 'testimonials';

    public static function active(): array
    {
        return static::db()->fetchAll('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY id DESC');
    }
}
