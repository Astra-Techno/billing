<?php

namespace App\Base;

use App\Core\DB;

class Entity extends ClassObject
{
    // ── Subclass defines these ─────────────────────────────────────────────────
    protected string $sqlNamespace   = 'App\\Sql';
    protected string $tableNamespace = 'App\\Tables';

    // ── Internal state ─────────────────────────────────────────────────────────
    protected array  $data    = [];
    protected string $name    = '';

    // =========================================================================
    // Load / factory
    // =========================================================================

    /**
     * Load an entity by name and input.
     *
     * Usage:
     *   $entity = Entity::load('Invoice', ['id' => 5]);
     *   $entity->get('number');
     */
    final public static function load(string $name, array $input = [], string $method = 'entity'): static
    {
        [$className, $sqlMethod] = Factory::findClass('App\\Sql', $name);

        $sqlMethod = $sqlMethod ?? $method;

        $sql   = new $className();
        $query = method_exists($sql, $sqlMethod) ? $sql->$sqlMethod($input) : null;

        if (!$query)
            (new static())->raiseError("Entity SQL {$className}::{$sqlMethod} returned empty query.");

        if ($query instanceof Query) {
            $query->assignKeys($input);
            $query->bind($input);
            $sql->setQuery($query);
        }

        $row = $sql->assoc();

        $instance       = new static();
        $instance->name = $name;
        $instance->data = $row ?: [];
        return $instance;
    }

    // =========================================================================
    // Attribute access
    // =========================================================================

    public function get(string $key, mixed $default = ''): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null && $this->data[$key] !== '';
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toObject(): object
    {
        return (object)$this->data;
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    // =========================================================================
    // Magic access
    // =========================================================================

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    // =========================================================================
    // Merge / refresh helpers
    // =========================================================================

    public function merge(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }
}
