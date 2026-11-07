<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;

class PartResource extends Resource
{
    public function key(): string
    {
        return 'parts';
    }

    public function table(): string
    {
        return 'parts';
    }

    public function labels(): array
    {
        return ['singular' => 'Peça', 'plural' => 'Peças'];
    }

    public function icon(): string
    {
        return 'package';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function datatableSubtitle(): string
    {
        return 'Base de peças e preços de referência para agilizar as OS';
    }

    public function datatableNewLabel(): string
    {
        return 'Nova peça';
    }

    public function datatableOrder(): array
    {
        return [[3, 'asc']];
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'category', 'label' => 'Categoria'],
            ['field' => 'brand', 'label' => 'Marca'],
        ];
    }

    public function columns(): array
    {
        return [
            ['field' => 'name', 'label' => 'Peça'],
            ['field' => 'brand', 'label' => 'Marca'],
            ['field' => 'part_code', 'label' => 'Código'],
            ['field' => 'category', 'label' => 'Categoria'],
            ['field' => 'price', 'label' => 'Preço'],
        ];
    }

    public function datatableColumns(): array
    {
        return [
            $this->dtControlColumn(),
            $this->dtSelectColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Peça',
                'data' => 'name',
                'render' => 'name',
                'secondary' => 'subtitle',
                'priority' => 1,
            ],
            ['title' => 'Marca', 'data' => 'brand', 'render' => 'text', 'priority' => 5],
            ['title' => 'Código', 'data' => 'part_code', 'render' => 'text', 'priority' => 6],
            ['title' => 'Categoria', 'data' => 'category', 'render' => 'text', 'priority' => 7],
            ['title' => 'Preço', 'data' => 'price', 'render' => 'money', 'priority' => 8],
            $this->dtActionsColumn([
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
                ['label' => 'Excluir', 'icon' => 'trash', 'delete' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['subtitle'] = trim(($row['brand'] ?? '') . (($row['part_code'] ?? '') ? ' · ' . $row['part_code'] : ''));
        return $row;
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome da peça', 'type' => 'text', 'icon' => 'package', 'required' => true, 'max' => 200, 'col' => 'col-md-8', 'placeholder' => 'Ex.: Pastilha de freio dianteira'],
            ['name' => 'brand', 'label' => 'Marca', 'type' => 'text', 'icon' => 'building-factory-2', 'max' => 120, 'col' => 'col-md-4', 'placeholder' => 'Ex.: Cobreq'],
            ['name' => 'part_code', 'label' => 'Código', 'type' => 'text', 'icon' => 'barcode', 'max' => 80, 'col' => 'col-md-4', 'placeholder' => 'Ex.: N1728'],
            ['name' => 'category', 'label' => 'Categoria', 'type' => 'text', 'icon' => 'category', 'max' => 120, 'col' => 'col-md-4', 'placeholder' => 'Ex.: Freios'],
            ['name' => 'price', 'label' => 'Preço de referência (R$)', 'type' => 'number', 'icon' => 'currency-real', 'min' => 0, 'step' => '0.01', 'col' => 'col-md-4'],
        ];
    }
}
