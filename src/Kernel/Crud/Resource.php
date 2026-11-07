<?php
// =============================================
// RESOURCE — descreve uma entidade para o CRUD
// genérico: tabela, rótulos, colunas da lista,
// campos do form (com validação/máscara) e papéis.
// =============================================

declare(strict_types=1);

namespace Kernel\Crud;

abstract class Resource
{
    /** Chave/URL base da entidade (ex.: "clients"). */
    abstract public function key(): string;

    /** Nome da tabela no banco. */
    abstract public function table(): string;

    /** @return array{singular: string, plural: string} */
    abstract public function labels(): array;

    /**
     * Colunas exibidas na listagem.
     *
     * @return array<int, array{field: string, label: string}>
     */
    abstract public function columns(): array;

    /**
     * Campos do formulário (e regras de validação).
     *
     * @return array<int, array{
     *   name: string, label: string, type?: string,
     *   required?: bool, max?: int, mask?: string,
     *   validate?: string, default?: mixed, help?: string
     * }>
     */
    abstract public function fields(): array;

    /** Papéis que podem acessar o recurso ([] = todos autenticados). */
    public function roles(): array
    {
        return ['admin'];
    }

    /** Ícone Tabler do recurso (para cabeçalhos). */
    public function icon(): string
    {
        return 'database';
    }

    /**
     * Opções de campos do tipo "select" (nome do campo => [valor => rótulo]).
     * Recursos com selects dinâmicos (ex.: cliente) sobrescrevem.
     *
     * @return array<string, array<int|string, string>>
     */
    public function selectOptions(\PDO $pdo, array $data = []): array
    {
        return [];
    }

    /** Template da listagem (permite customização por módulo). */
    public function indexView(): string
    {
        return 'kernel::crud/index';
    }

    /** Dados paginados da listagem (recursos com JOIN sobrescrevem). */
    public function paginate(\PDO $pdo, int $page, int $perPage, string $search): array
    {
        return (new \Kernel\Database\Repository($pdo, $this->table(), $this->fillable(), $this->searchable()))
            ->paginate($page, $perPage, $search);
    }

    /** Template do formulário (permite customização por módulo). */
    public function formView(): string
    {
        return 'kernel::crud/form';
    }

    /** Se definido, a primeira coluna da listagem vira link para {detailPath}/{id}. */
    public function detailPath(): ?string
    {
        return null;
    }

    /**
     * Transforma os dados antes de gravar (ex.: hash de senha).
     * $ignoreId é null em criação e o id em edição.
     */
    public function prepareData(array $data, ?int $ignoreId, \PDO $pdo): array
    {
        return $data;
    }

    /** Colunas aceitas em insert/update. */
    public function fillable(): array
    {
        return array_map(static fn (array $f) => $f['name'], $this->fields());
    }

    /** Coluna pesquisável por texto livre (primeira coluna). */
    public function searchable(): string
    {
        return $this->columns()[0]['field'] ?? 'id';
    }

    /** Caminho base da URL (ex.: "/clients"). */
    public function path(): string
    {
        return '/' . $this->key();
    }
}
