<?php

namespace App\Base;

use App\Core\DB;

class Sql extends ClassObject
{
    protected ?Query $sqlQuery = null;
    protected string $dbConnection = 'mysql';

    final public function setQuery(Query $query): void    { $this->sqlQuery = $query; }
    final public function setDbConnection(string $c): void { $this->dbConnection = $c; }
    final public function getDbConnection(): string        { return $this->dbConnection; }
    final public function getQuery(): string               { return (string)$this->sqlQuery; }
    final public function getQueryName(): string           { return $this->sqlQuery?->getName() ?? ''; }

    final public function load(string $name, array $input = [], string $db = ''): static
    {
        if (empty($name)) return $this;

        [$className, $method] = Factory::findClass('App\\Sql', $name);

        $method = trim($method ?? 'default');
        $class  = new $className();

        if ($db) $class->setDbConnection($db);

        if (!method_exists($class, $method))
            $this->raiseError("{$className}:{$method} - method not found!");

        $query = $class->$method($input);

        if (!$query)
            $this->raiseError("{$className}:{$method} - empty query returned!");

        if ($query instanceof Query) {
            $query->assignKeys($input);
            $query->bind($input);
        }

        $class->setQuery($query);
        return $class;
    }

    public function __toString(): string { return $this->getQuery(); }

    // ── Result methods ────────────────────────────────────────────────────────

    final public function result(): mixed
    {
        $row = $this->assoc();
        return $row ? current($row) : '';
    }

    final public function resultList(string $column = ''): array
    {
        $rows = $this->objectList();
        if (empty($rows)) return [];
        $col = $column ?: array_key_first((array)$rows[0]);
        return array_column(array_map(fn($r) => (array)$r, $rows), $col);
    }

    final public function assoc(): array
    {
        $obj = $this->object();
        return $obj ? json_decode(json_encode($obj, JSON_INVALID_UTF8_SUBSTITUTE), true) : [];
    }

    final public function assocList(?string $groupColumn = null): array
    {
        return json_decode(json_encode($this->objectList($groupColumn), JSON_INVALID_UTF8_SUBSTITUTE), true);
    }

    final public function object(): ?object
    {
        return DB::selectOne($this->getQuery());
    }

    final public function objectList(?string $groupColumn = null): array
    {
        $out = DB::select($this->getQuery());

        if ($groupColumn && !empty($out)) {
            $grouped = [];
            foreach ($out as $row) {
                if (!property_exists($row, $groupColumn))
                    $this->raiseError($this->getQueryName() . " - group column '{$groupColumn}' missing!");
                $grouped[$row->$groupColumn] = $row;
            }
            return $grouped;
        }

        return $out;
    }
}
