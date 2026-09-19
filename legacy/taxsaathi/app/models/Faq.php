<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Faq extends BaseModel
{
    protected static string $table = 'faqs';

    public static function active(): array
    {
        return static::db()->fetchAll('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    }
}
