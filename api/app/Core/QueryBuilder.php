<?php

namespace App\Core;

use PDO;

/**
 * Minimal query builder used internally by Table::save() / delete().
 * Not intended for general SQL queries — use Sql classes for those.
 */
class QueryBuilder
{
    private string $table;
    private PDO    $pdo;
    private array  $wheres  = [];
    private array  $params  = [];

    public function __construct(string $table, PDO $pdo)
    {
        $this->table = $table;
        $this->pdo   = $pdo;
    }

    public function where(string|array $column, mixed $value = null): static
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->wheres[]  = "`{$col}` = ?";
                $this->params[]  = $val;
            }
        } else {
            $this->wheres[] = "`{$column}` = ?";
            $this->params[] = $value;
        }
        return $this;
    }

    public function first(): ?object
    {
        $sql  = "SELECT * FROM `{$this->table}`";
        $sql .= $this->buildWhere();
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function get(): array
    {
        $sql  = "SELECT * FROM `{$this->table}`";
        $sql .= $this->buildWhere();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function insert(array $data): string
    {
        $cols   = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $places = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` ({$cols}) VALUES ({$places})");
        $stmt->execute(array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $sql  = "UPDATE `{$this->table}` SET {$sets}" . $this->buildWhere();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...array_values($data), ...$this->params]);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql  = "DELETE FROM `{$this->table}`" . $this->buildWhere();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->rowCount();
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres))
            return '';
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }
}
