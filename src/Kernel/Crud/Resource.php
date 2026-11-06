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
