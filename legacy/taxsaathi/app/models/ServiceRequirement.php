<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class ServiceRequirement extends BaseModel
{
    protected static string $table = 'service_requirements';

    public static function forService(int $serviceId): array
    {
        return static::db()->fetchAll('SELECT * FROM service_requirements WHERE service_id = :service_id ORDER BY sort_order ASC, id ASC', ['service_id' => $serviceId]);
    }
}
