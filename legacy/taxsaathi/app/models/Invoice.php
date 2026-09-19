<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Invoice extends BaseModel
{
    protected static string $table = 'invoices';

    public static function findByOrder(int $orderId): ?array
    {
        return static::db()->fetch('SELECT * FROM invoices WHERE order_id = :order_id LIMIT 1', ['order_id' => $orderId]);
    }

    public static function nextInvoiceNo(): string
    {
        $last = (int) static::db()->scalar('SELECT COALESCE(MAX(id), 0) FROM invoices');
        return 'TSI-' . date('Y') . '-' . str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
