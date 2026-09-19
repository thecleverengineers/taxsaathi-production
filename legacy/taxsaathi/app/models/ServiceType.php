<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class ServiceType
{
    public static function forService(int $serviceId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT *
             FROM service_types
             WHERE service_id = :service_id
               AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );

        $stmt->execute([
            'service_id' => $serviceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}