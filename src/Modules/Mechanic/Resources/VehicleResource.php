<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;

class VehicleResource extends Resource
{
    public function key(): string
    {
        return 'vehicles';
    }

    public function table(): string
    {
        return 'vehicles';
    }

    public function labels(): array
    {
        return ['singular' => 'Veículo', 'plural' => 'Veículos'];
    }

    public function icon(): string
    {
        return 'car';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'plate', 'label' => 'Placa'],
            ['field' => 'brand', 'label' => 'Marca'],
            ['field' => 'model', 'label' => 'Modelo'],
            ['field' => 'year', 'label' => 'Ano'],
            ['field' => 'color', 'label' => 'Cor'],
            ['field' => 'current_km', 'label' => 'KM'],
        ];
    }

    public function selectOptions(\PDO $pdo): array
    {
        $options = [];
        foreach ($pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll() as $row) {
            $options[(int) $row['id']] = $row['name'];
        }
        return ['client_id' => $options];
    }

    public function fields(): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'select', 'icon' => 'user', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'plate', 'label' => 'Placa', 'type' => 'text', 'icon' => 'license', 'mask' => 'plate', 'validate' => 'plate', 'unique' => true, 'required' => true, 'max' => 10, 'col' => 'col-md-3'],
            ['name' => 'year', 'label' => 'Ano', 'type' => 'number', 'icon' => 'calendar', 'min' => 1950, 'max' => 2030, 'col' => 'col-md-3'],
            ['name' => 'brand', 'label' => 'Marca', 'type' => 'text', 'icon' => 'building-factory-2', 'max' => 80, 'col' => 'col-md-6'],
            ['name' => 'model', 'label' => 'Modelo', 'type' => 'text', 'icon' => 'car', 'max' => 120, 'col' => 'col-md-6'],
            ['name' => 'color', 'label' => 'Cor', 'type' => 'text', 'icon' => 'palette', 'max' => 30, 'col' => 'col-md-4'],
            ['name' => 'current_km', 'label' => 'KM atual', 'type' => 'number', 'icon' => 'gauge', 'min' => 0, 'col' => 'col-md-4'],
            ['name' => 'chassis', 'label' => 'Chassi', 'type' => 'text', 'icon' => 'fingerprint', 'max' => 30, 'col' => 'col-md-4'],
            ['name' => 'notes', 'label' => 'Observações', 'type' => 'textarea', 'icon' => 'notes'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'boolean', 'icon' => 'toggle-left', 'default' => 1],
        ];
    }
}
