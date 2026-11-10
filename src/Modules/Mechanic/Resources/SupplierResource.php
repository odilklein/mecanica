<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;

class SupplierResource extends Resource
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function table(): string
    {
        return 'suppliers';
    }

    public function labels(): array
    {
        return ['singular' => 'Fornecedor', 'plural' => 'Fornecedores'];
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function datatableSubtitle(): string
    {
        return 'Cadastre os fornecedores usados nas peças e pedidos';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo fornecedor';
    }

    public function columns(): array
    {
        return [
            ['field' => 'name', 'label' => 'Nome'],
            ['field' => 'document', 'label' => 'CNPJ/CPF'],
            ['field' => 'phone', 'label' => 'Telefone'],
            ['field' => 'email', 'label' => 'E-mail'],
        ];
    }

    public function datatableColumns(): array
    {
        return [
            $this->dtControlColumn(),
            $this->dtSelectColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Fornecedor',
                'data' => 'name',
                'render' => 'name',
                'secondary' => 'phone',
                'priority' => 1,
            ],
            ['title' => 'CNPJ/CPF', 'data' => 'document', 'render' => 'text', 'priority' => 5],
            ['title' => 'E-mail', 'data' => 'email', 'render' => 'text', 'priority' => 6],
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
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['status'] = (int) ($row['is_active'] ?? 1);
        return $row;
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'icon' => 'building-store', 'required' => true, 'unique' => true, 'max' => 160, 'col' => 'col-md-8', 'placeholder' => 'Ex.: Auto Peças Silva'],
            ['name' => 'document', 'label' => 'CNPJ/CPF', 'type' => 'text', 'icon' => 'id', 'max' => 30, 'col' => 'col-md-4', 'placeholder' => '00.000.000/0000-00'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 30, 'col' => 'col-md-4', 'placeholder' => '(11) 98888-7777'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'icon' => 'mail', 'validate' => 'email', 'max' => 120, 'col' => 'col-md-8', 'placeholder' => 'fornecedor@email.com'],
            ['name' => 'notes', 'label' => 'Observações', 'type' => 'textarea', 'icon' => 'notes', 'col' => 'col-12', 'placeholder' => 'Condições, contatos, prazo...'],
            ['name' => 'is_active', 'label' => 'Fornecedor ativo', 'type' => 'boolean', 'icon' => 'toggle-right', 'default' => 1, 'col' => 'col-md-6'],
        ];
    }
}
