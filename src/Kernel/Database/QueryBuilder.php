<?php
// =============================================
// QUERY BUILDER — montagem fluente de consultas
// sobre PDO (prepared statements). Hand-made (TCC).
// =============================================

declare(strict_types=1);

namespace Kernel\Database;

use PDO;

class QueryBuilder
{
    private PDO $pdo;
    private string $table;
    private array $where = [];
    private array $params = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $param = ':' . $column . '_' . count($this->where);
        $this->where[] = "{$column} {$operator} {$param}";
        $this->params[$param] = $value;
        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        return $this->where($column, 'LIKE', "%{$value}%");
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}" . $this->buildTail();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}" . $this->buildWhere();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return (int) $stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $primaryKey = 'id'): bool
    {
        $set = implode(', ', array_map(static fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$set} WHERE {$primaryKey} = :__id";

        $data['__id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $primaryKey = 'id'): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$primaryKey} = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function buildWhere(): string
    {
        return $this->where ? ' WHERE ' . implode(' AND ', $this->where) : '';
    }

    private function buildTail(): string
    {
        $sql = $this->buildWhere();

        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }
}
