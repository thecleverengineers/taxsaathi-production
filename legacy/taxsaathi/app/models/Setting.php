<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Setting extends BaseModel
{
    protected static string $table = 'website_settings';
    public static float|int|string|null $__cache_bust = null;
    private static array $cache = [];

    public static function get(string $key, string $default = ''): string
    {
        if (static::$__cache_bust !== null) {
            static::$cache = [];
            static::$__cache_bust = null;
        }

        if (!array_key_exists($key, static::$cache)) {
            $row = static::db()->fetch('SELECT setting_value FROM website_settings WHERE setting_key = :key LIMIT 1', ['key' => $key]);
            static::$cache[$key] = (string) ($row['setting_value'] ?? $default);
        }

        return (string) static::$cache[$key];
    }

    public static function set(string $key, string $value): void
    {
        $exists = static::db()->fetch('SELECT id FROM website_settings WHERE setting_key = :key LIMIT 1', ['key' => $key]);
        if ($exists) {
            static::db()->execute('UPDATE website_settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key', ['key' => $key, 'value' => $value]);
        } else {
            static::db()->execute('INSERT INTO website_settings (setting_key, setting_value, created_at, updated_at) VALUES (:key,:value,NOW(),NOW())', ['key' => $key, 'value' => $value]);
        }
        static::$__cache_bust = microtime(true);
    }

    public static function many(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = static::get($key, '');
        }
        return $out;
    }
}
