<?php
// =============================================
// REPOSITORY — acesso a dados genérico sobre uma
// tabela (listar/paginar/buscar/criar/editar/excluir).
// Módulos estendem e definem $table + $fillable.
// =============================================

declare(strict_types=1);

namespace Kernel\Database;

use PDO;

abstract class Repository
{
    protected PDO $pdo;

    /** Nome da tabela. */
    protected string $table;

    /** Colunas aceitas em insert/update (whitelist). */
    protected array $fillable = [];

    /** Coluna pesquisável por texto livre. */
    protected string $searchable = 'name';

    protected string $orderBy = 'id';
    protected string $orderDirection = 'desc';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array{items: array, total: int, page: int, perPage: int, lastPage: int} */
    public function paginate(int $page = 1, int $perPage = 15, string $search = ''): array
    {
        $page = max(1, $page);

        $countQb = $this->query();
        $listQb = $this->query();

        if ($search !== '') {
            $countQb->whereLike($this->searchable, $search);
            $listQb->whereLike($this->searchable, $search);
        }

        $total = $countQb->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $items = $listQb
            ->orderBy($this->orderBy, $this->orderDirection)
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return compact('items', 'total', 'page', 'perPage', 'lastPage');
    }

    public function all(): array
    {
        return $this->query()->orderBy($this->orderBy, $this->orderDirection)->get();
    }

    public function find(int $id): ?array
    {
        return $this->query()->where('id', '=', $id)->first();
    }

    public function create(array $data): int
    {
        return $this->query()->insert($this->onlyFillable($data));
    }

    public function update(int $id, array $data): bool
    {
        return $this->query()->update($id, $this->onlyFillable($data));
    }

    public function delete(int $id): bool
    {
        return $this->query()->delete($id);
    }

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->pdo, $this->table);
    }

    /**
     * Filtra o array mantendo apenas as colunas permitidas (whitelist).
     */
    protected function onlyFillable(array $data): array
    {
        if (!$this->fillable) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
