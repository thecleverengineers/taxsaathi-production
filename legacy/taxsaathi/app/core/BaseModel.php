<?php
declare(strict_types=1);

namespace App\Core;

abstract class BaseModel
{
    protected static string $table = '';

    protected static function db(): Database
    {
        /** @var Database $db */
        $db = app('db');
        return $db;
    }

    public static function table(): string
    {
        return static::$table;
    }

    public static function all(string $orderBy = 'id DESC'): array
    {
        return static::db()->fetchAll('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy);
    }

    public static function find(int $id): ?array
    {
        return static::db()->fetch('SELECT * FROM ' . static::$table . ' WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        return static::db()->execute('DELETE FROM ' . static::$table . ' WHERE id = :id', ['id' => $id]);
    }

    public static function countAll(): int
    {
        return (int) static::db()->scalar('SELECT COUNT(*) FROM ' . static::$table);
    }

    public static function insert(array $data): int
    {
        $columns = array_keys($data);
        $params = array_map(static fn(string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO ' . static::$table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $params) . ')';
        static::db()->execute($sql, $data);
        return static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $segments = [];
        foreach (array_keys($data) as $column) {
            $segments[] = $column . ' = :' . $column;
        }
        $data['id'] = $id;
        $sql = 'UPDATE ' . static::$table . ' SET ' . implode(', ', $segments) . ' WHERE id = :id';
        return static::db()->execute($sql, $data);
    }
}
