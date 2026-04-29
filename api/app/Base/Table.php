<?php

namespace App\Base;

use App\Core\DB;
use App\Core\Cache;

class Table extends ClassObject
{
    // ── Subclass must define these ─────────────────────────────────────────────
    protected string $table      = '';
    protected string $primaryKey = 'id';
    protected string $connection = 'mysql';

    // ── Fillable / guarded ────────────────────────────────────────────────────
    protected array $fillable  = [];   // if set, only these columns are mass-assigned
    protected array $guarded   = ['id']; // always blocked from mass-assignment

    // ── Timestamps ────────────────────────────────────────────────────────────
    protected bool   $timestamps    = true;
    protected string $createdColumn = 'created_at';
    protected string $updatedColumn = 'updated_at';

    // ── Soft-delete ───────────────────────────────────────────────────────────
    protected bool   $softDelete       = false;
    protected string $deletedColumn    = 'deleted_at';

    // ── Internal state ────────────────────────────────────────────────────────
    protected array  $attributes  = [];
    protected array  $original    = [];
    protected bool   $exists      = false;

    // =========================================================================
    // Schema helpers
    // =========================================================================

    final public function getTable(): string
    {
        return DB::prefix() . $this->table;
    }

    final public function getColumns(): array
    {
        return Cache::remember(
            'schema_' . $this->getTable(),
            86400,
            fn() => $this->fetchColumns()
        );
    }

    private function fetchColumns(): array
    {
        $pdo  = DB::getPdo($this->connection);
        $stmt = $pdo->query('DESCRIBE `' . $this->getTable() . '`');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    }

    // =========================================================================
    // Static finders
    // =========================================================================

    final public static function find(int|string $id): ?static
    {
        $instance = new static();
        $row = DB::selectOne(
            'SELECT * FROM `' . $instance->getTable() . '` WHERE `' . $instance->primaryKey . '` = ' .
            DB::getPdo($instance->connection)->quote((string)$id)
        );
        return $row ? $instance->hydrate((array)$row, true) : null;
    }

    final public static function findOrFail(int|string $id): static
    {
        $record = static::find($id);
        if (!$record)
            (new static())->raiseError('Record not found.', 404);
        return $record;
    }

    final public static function where(string $column, mixed $value): TableQuery
    {
        return (new TableQuery(new static()))->where($column, $value);
    }

    final public static function all(): array
    {
        $instance = new static();
        $rows = DB::select('SELECT * FROM `' . $instance->getTable() . '`');
        return array_map(fn($r) => (new static())->hydrate((array)$r, true), $rows);
    }

    // =========================================================================
    // Attribute access
    // =========================================================================

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toObject(): object
    {
        return (object)$this->attributes;
    }

    // =========================================================================
    // Fill
    // =========================================================================

    public function fill(array $data): static
    {
        $columns  = $this->getColumns();
        $allowed  = !empty($this->fillable) ? $this->fillable : $columns;

        foreach ($data as $key => $value) {
            if (in_array($key, $this->guarded))    continue;
            if (!in_array($key, $allowed))          continue;
            if (!in_array($key, $columns))          continue;
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    // =========================================================================
    // Hydrate (internal — marks record as existing in DB)
    // =========================================================================

    protected function hydrate(array $row, bool $exists = false): static
    {
        $this->attributes = $row;
        $this->original   = $row;
        $this->exists     = $exists;
        return $this;
    }

    // =========================================================================
    // Save / create / update / delete
    // =========================================================================

    public function save(): bool
    {
        return $this->exists ? $this->performUpdate() : $this->performInsert();
    }

    private function performInsert(): bool
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes[$this->createdColumn] = $now;
            $this->attributes[$this->updatedColumn] = $now;
        }

        $data    = $this->filterColumns($this->attributes);
        $columns = array_keys($data);
        $pdo     = DB::getPdo($this->connection);

        $cols   = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $values = implode(', ', array_map(fn($c) => ':' . $c, $columns));
        $sql    = "INSERT INTO `{$this->getTable()}` ({$cols}) VALUES ({$values})";

        $stmt = $pdo->prepare($sql);
        foreach ($data as $col => $val)
            $stmt->bindValue(':' . $col, $val);

        $ok = $stmt->execute();
        if ($ok) {
            $this->attributes[$this->primaryKey] = $pdo->lastInsertId();
            $this->original = $this->attributes;
            $this->exists   = true;
        }
        return $ok;
    }

    private function performUpdate(): bool
    {
        if ($this->timestamps)
            $this->attributes[$this->updatedColumn] = date('Y-m-d H:i:s');

        $data = $this->filterColumns($this->attributes);
        unset($data[$this->primaryKey]);

        $pdo   = DB::getPdo($this->connection);
        $sets  = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", array_keys($data)));
        $pkVal = $this->attributes[$this->primaryKey] ?? null;

        if (!$pkVal) $this->raiseError('Cannot update: primary key missing.');

        $sql  = "UPDATE `{$this->getTable()}` SET {$sets} WHERE `{$this->primaryKey}` = :__pk";
        $stmt = $pdo->prepare($sql);
        foreach ($data as $col => $val)
            $stmt->bindValue(':' . $col, $val);
        $stmt->bindValue(':__pk', $pkVal);

        $ok = $stmt->execute();
        if ($ok) $this->original = $this->attributes;
        return $ok;
    }

    public function delete(): bool
    {
        $pkVal = $this->attributes[$this->primaryKey] ?? null;
        if (!$pkVal) $this->raiseError('Cannot delete: primary key missing.');

        $pdo = DB::getPdo($this->connection);

        if ($this->softDelete) {
            $now  = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                "UPDATE `{$this->getTable()}` SET `{$this->deletedColumn}` = :ts WHERE `{$this->primaryKey}` = :pk"
            );
            $stmt->bindValue(':ts', $now);
            $stmt->bindValue(':pk', $pkVal);
            return $stmt->execute();
        }

        $stmt = $pdo->prepare("DELETE FROM `{$this->getTable()}` WHERE `{$this->primaryKey}` = :pk");
        $stmt->bindValue(':pk', $pkVal);
        return $stmt->execute();
    }

    // =========================================================================
    // Static shortcuts
    // =========================================================================

    final public static function create(array $data): static
    {
        $instance = new static();
        $instance->fill($data);
        $instance->save();
        return $instance;
    }

    final public static function updateWhere(array $where, array $data): int
    {
        $instance = new static();
        $pdo      = DB::getPdo($instance->connection);

        $data = $instance->filterColumns($data);
        if (empty($data)) return 0;

        if ($instance->timestamps)
            $data[$instance->updatedColumn] = date('Y-m-d H:i:s');

        $sets      = implode(', ', array_map(fn($c) => "`{$c}` = :set_{$c}", array_keys($data)));
        $wherePart = implode(' AND ', array_map(fn($c) => "`{$c}` = :wh_{$c}", array_keys($where)));
        $sql       = "UPDATE `{$instance->getTable()}` SET {$sets} WHERE {$wherePart}";

        $stmt = $pdo->prepare($sql);
        foreach ($data  as $col => $val) $stmt->bindValue(':set_' . $col, $val);
        foreach ($where as $col => $val) $stmt->bindValue(':wh_'  . $col, $val);

        $stmt->execute();
        return $stmt->rowCount();
    }

    final public static function deleteWhere(array $where): int
    {
        $instance  = new static();
        $pdo       = DB::getPdo($instance->connection);
        $wherePart = implode(' AND ', array_map(fn($c) => "`{$c}` = :{$c}", array_keys($where)));
        $sql       = "DELETE FROM `{$instance->getTable()}` WHERE {$wherePart}";

        $stmt = $pdo->prepare($sql);
        foreach ($where as $col => $val) $stmt->bindValue(':' . $col, $val);

        $stmt->execute();
        return $stmt->rowCount();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function filterColumns(array $data): array
    {
        $columns = $this->getColumns();
        return array_filter(
            $data,
            fn($k) => in_array($k, $columns),
            ARRAY_FILTER_USE_KEY
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Lightweight fluent WHERE-builder returned by Table::where()
// ─────────────────────────────────────────────────────────────────────────────

class TableQuery
{
    private Table  $model;
    private array  $wheres = [];

    public function __construct(Table $model)
    {
        $this->model = $model;
    }

    public function where(string $column, mixed $value): static
    {
        $this->wheres[] = [$column, $value];
        return $this;
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) return '';
        $pdo   = DB::getPdo();
        $parts = [];
        foreach ($this->wheres as [$col, $val])
            $parts[] = "`{$col}` = " . (is_numeric($val) ? $val : $pdo->quote((string)$val));
        return 'WHERE ' . implode(' AND ', $parts);
    }

    public function first(): ?Table
    {
        $table = $this->model->getTable();
        $sql   = "SELECT * FROM `{$table}` {$this->buildWhere()} LIMIT 1";
        $row   = DB::selectOne($sql);
        if (!$row) return null;
        return (new ($this->model::class)())->hydrate((array)$row, true);
    }

    public function get(): array
    {
        $table = $this->model->getTable();
        $sql   = "SELECT * FROM `{$table}` {$this->buildWhere()}";
        $rows  = DB::select($sql);
        return array_map(
            fn($r) => (new ($this->model::class)())->hydrate((array)$r, true),
            $rows
        );
    }

    public function delete(): int
    {
        $table = $this->model->getTable();
        $sql   = "DELETE FROM `{$table}` {$this->buildWhere()}";
        return DB::affectingStatement($sql);
    }
}
