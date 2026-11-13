<?php
declare(strict_types=1);

namespace Modules\Core\Resources;

use Kernel\Crud\Resource;

class ClientResource extends Resource
{
    public function key(): string
    {
        return 'clients';
    }

    public function prefillKey(): ?string
    {
        return 'client';
    }

    public function table(): string
    {
        return 'clients';
    }

    public function labels(): array
    {
        return ['singular' => 'Cliente', 'plural' => 'Clientes'];
    }

    public function formTitle(bool $isEdit): string
    {
        return $isEdit ? 'Editar cliente' : 'Novo cliente';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function detailPath(): ?string
    {
        return '/clients';
    }

    public function columns(): array
    {
        return [
            ['field' => 'name', 'label' => 'Nome'],
            ['field' => 'phone', 'label' => 'Telefone'],
            ['field' => 'email', 'label' => 'E-mail'],
            ['field' => 'city', 'label' => 'Cidade'],
        ];
    }

    public function datatableSubtitle(): string
    {
        return 'Gerencie os clientes da oficina';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo cliente';
    }

    public function datatableOrder(): array
    {
        return [[2, 'asc']];
    }

    /** A listagem não tem ações em massa: sem checkboxes. */
    public function selectable(): bool
    {
        return false;
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'status', 'label' => 'Situação', 'options' => [
                1 => 'Ativo',
                0 => 'Inativo',
            ]],
            ['field' => 'city', 'label' => 'Cidade'],
        ];
    }

    public function datatableColumns(): array
    {
        return [
            $this->dtControlColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Cliente',
                'data' => 'name',
                'render' => 'name',
                'secondary' => 'phone',
                'href' => $this->path() . '/{id}',
                'priority' => 1,
            ],
            ['title' => 'E-mail', 'data' => 'email', 'render' => 'text', 'priority' => 5],
            ['title' => 'Cidade', 'data' => 'location', 'render' => 'text', 'priority' => 6],
            [
                'title' => 'Situação',
                'data' => 'status',
                'render' => 'badge',
                'badge' => [
                    1 => ['label' => 'Ativo', 'class' => 'bg-label-success'],
                    0 => ['label' => 'Inativo', 'class' => 'bg-label-secondary'],
                ],
                'priority' => 4,
            ],
            $this->dtActionsColumn([
                ['label' => 'Detalhes', 'icon' => 'eye', 'href' => $this->path() . '/{id}'],
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['location'] = trim(($row['city'] ?? '') . (($row['state'] ?? '') ? '/' . $row['state'] : ''));
        $row['status'] = (int) ($row['is_active'] ?? 1);
        return $row;
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'icon' => 'user', 'required' => true, 'max' => 120, 'col' => 'col-md-8', 'placeholder' => 'Ex.: Maria da Silva'],
            ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'icon' => 'id', 'mask' => 'cpf', 'validate' => 'cpf', 'unique' => true, 'max' => 14, 'col' => 'col-md-4', 'placeholder' => '000.000.000-00'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 20, 'col' => 'col-md-4', 'placeholder' => '(11) 98888-7777'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'icon' => 'mail', 'validate' => 'email', 'max' => 120, 'col' => 'col-md-8', 'placeholder' => 'cliente@email.com'],
            ['name' => 'zip', 'label' => 'CEP', 'type' => 'text', 'icon' => 'map-pin', 'mask' => 'cep', 'max' => 10, 'col' => 'col-md-4', 'placeholder' => '00000-000', 'help' => 'Preenche o endereço automaticamente.'],
            ['name' => 'address', 'label' => 'Endereço', 'type' => 'text', 'icon' => 'road', 'max' => 255, 'col' => 'col-md-6', 'placeholder' => 'Rua'],
            ['name' => 'address_number', 'label' => 'Número', 'type' => 'text', 'icon' => 'hash', 'max' => 20, 'col' => 'col-md-2', 'placeholder' => 'Ex.: 123'],
            ['name' => 'neighborhood', 'label' => 'Bairro', 'type' => 'text', 'icon' => 'building-community', 'max' => 80, 'col' => 'col-md-4', 'placeholder' => 'Centro'],
            ['name' => 'city', 'label' => 'Cidade', 'type' => 'text', 'icon' => 'building', 'max' => 80, 'col' => 'col-md-6', 'placeholder' => 'São Paulo'],
            ['name' => 'state', 'label' => 'UF', 'type' => 'text', 'icon' => 'map', 'max' => 2, 'col' => 'col-md-2', 'placeholder' => 'SP'],
            ['name' => 'notes', 'label' => 'Observações', 'type' => 'textarea', 'icon' => 'notes', 'col' => 'col-12', 'placeholder' => 'Informações internas sobre o cliente'],
            ['name' => 'is_active', 'label' => 'Cliente ativo', 'type' => 'boolean', 'icon' => 'toggle-right', 'default' => 1, 'col' => 'col-md-6'],
        ];
    }
}
