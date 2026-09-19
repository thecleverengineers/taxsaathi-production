<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ServiceBenefit extends Model
{
    protected static string $table = 'service_benefits';

    public static function forService(int $serviceId): array
    {
        return static::query(
            'SELECT * FROM service_benefits WHERE service_id = :service_id AND is_active = 1 ORDER BY sort_order ASC, id ASC',
            ['service_id' => $serviceId]
        )->fetchAll();
    }
}