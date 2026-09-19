<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function set(string $key, mixed $value): void
    {
        $this->bindings[$key] = $value;
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        if (!array_key_exists($key, $this->bindings)) {
            throw new RuntimeException('Container binding not found: ' . $key);
        }

        $value = $this->bindings[$key];
        if (is_callable($value)) {
            $value = $value();
        }

        $this->instances[$key] = $value;
        return $value;
    }
}
