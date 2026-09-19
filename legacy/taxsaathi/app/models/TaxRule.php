<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class TaxRule extends BaseModel
{
    protected static string $table = 'tax_rules';

    public static function byFinancialYear(string $financialYear, string $regime): array
    {
        return static::db()->fetchAll(
            'SELECT * FROM tax_rules WHERE financial_year = :financial_year AND regime = :regime ORDER BY income_from ASC',
            ['financial_year' => $financialYear, 'regime' => $regime]
        );
    }

    public static function grouped(): array
    {
        return static::db()->fetchAll('SELECT * FROM tax_rules ORDER BY financial_year DESC, regime ASC, income_from ASC');
    }
}
