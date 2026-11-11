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

    /** Papéis que podem criar registros (padrão: os mesmos do recurso). */
    public function createRoles(): array
    {
        return $this->roles();
    }

    /** Habilita a seleção múltipla (checkbox) na listagem. */
    public function selectable(): bool
    {
        return true;
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

    /** Template da listagem (DataTables "Fixed Header" por padrão). */
    public function indexView(): string
    {
        return 'kernel::crud/datatables';
    }

    // =========================================================
    // DataTables (listagem "Fixed Header" do Vuexy)
    // =========================================================

    /** Subtítulo exibido abaixo do título da página. */
    public function datatableSubtitle(): string
    {
        return 'Gerencie ' . mb_strtolower($this->labels()['plural']);
    }

    /** Rótulo do botão de criação. */
    public function datatableNewLabel(): string
    {
        return 'Novo(a) ' . $this->labels()['singular'];
    }

    /** Ordenação inicial: [[índiceDaColuna, 'asc'|'desc']]. */
    public function datatableOrder(): array
    {
        return [[2, 'desc']];
    }

    /**
     * Filtros exibidos acima da listagem. Cada item:
     * ['field' => 'category', 'label' => 'Categoria'].
     */
    public function datatableFilters(): array
    {
        return [];
    }

    /**
     * Linhas exibidas na listagem. O DataTables pagina/ordena no cliente,
     * por isso o endpoint devolve todas as linhas.
     */
    public function datatableRows(\PDO $pdo): array
    {
        return $this->paginate($pdo, 1, 10000, '')['items'];
    }

    /** Campos derivados/extras de uma linha no JSON do DataTables. */
    public function datatableRow(array $row): array
    {
        return $row;
    }

    /**
     * Colunas do DataTables: title, data, render (control/select/text/name/
     * link/badge/date/money/number/actions), visible e priority.
     *
     * A prioridade responsiva 1 mantém a coluna no mobile; números maiores
     * são escondidos primeiro. Name = 1 e Ações = 2 garantem que, no mobile,
     * fiquem apenas o identificador principal e as ações.
     */
    public function datatableColumns(): array
    {
        $columns = [
            $this->dtControlColumn(),
            $this->dtSelectColumn(),
            $this->dtIdColumn(),
        ];

        $priority = 5;
        foreach ($this->columns() as $col) {
            $columns[] = [
                'title' => $col['label'],
                'data' => $col['field'],
                'render' => 'text',
                'priority' => $priority++,
            ];
        }

        $columns[] = $this->dtActionsColumn([
            ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
            ['label' => 'Excluir', 'icon' => 'trash', 'delete' => true],
        ]);

        return $columns;
    }

    protected function dtControlColumn(): array
    {
        return [
            'title' => '',
            'data' => '',
            'render' => 'control',
            'orderable' => false,
            'className' => 'control',
            'priority' => 9,
        ];
    }

    protected function dtSelectColumn(): array
    {
        return [
            'title' => '',
            'data' => 'id',
            'render' => 'select',
            'orderable' => false,
            'priority' => 9,
        ];
    }

    protected function dtIdColumn(): array
    {
        return [
            'title' => 'id',
            'data' => 'id',
            'visible' => false,
            'priority' => 99,
        ];
    }

    protected function dtActionsColumn(array $actions): array
    {
        return [
            'title' => 'Ações',
            'data' => '',
            'render' => 'actions',
            'orderable' => false,
            'className' => 'd-flex align-items-center',
            'priority' => 2,
            'actions' => $actions,
        ];
    }

    /**
     * Monta um mapa valor => [label, class] para colunas de badge.
     *
     * @param array<string, string> $labels
     */
    protected function dtBadges(array $labels, callable $classFor): array
    {
        $badges = [];
        foreach ($labels as $value => $label) {
            $badges[$value] = ['label' => $label, 'class' => $classFor((string) $value)];
        }
        return $badges;
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

    /** CSS extra do formulário (ex.: Select2, wizard). */
    public function formStyles(): array
    {
        return [];
    }

    /** JS extra do formulário (ex.: FIPE, wizard). */
    public function formScripts(): array
    {
        return [];
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
