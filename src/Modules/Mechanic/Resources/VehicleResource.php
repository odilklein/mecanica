<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;
use Modules\Mechanic\Services\FipeService;

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

    public function formView(): string
    {
        return 'mechanic::vehicles/form';
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

    public function datatableSubtitle(): string
    {
        return 'Gerencie os veículos dos clientes';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo veículo';
    }

    public function datatableOrder(): array
    {
        return [[3, 'asc']];
    }

    public function datatableRows(\PDO $pdo): array
    {
        return $pdo->query(
            'SELECT v.*, c.name AS client_name
               FROM vehicles v
               LEFT JOIN clients c ON c.id = v.client_id
              ORDER BY v.plate'
        )->fetchAll();
    }

    public function datatableColumns(): array
    {
        return [
            $this->dtControlColumn(),
            $this->dtSelectColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Veículo',
                'data' => 'plate',
                'render' => 'name',
                'secondary' => 'vehicle_label',
                'href' => $this->path() . '/{id}/edit',
                'priority' => 1,
            ],
            ['title' => 'Cliente', 'data' => 'client_name', 'render' => 'text', 'priority' => 5],
            ['title' => 'Ano', 'data' => 'year', 'render' => 'text', 'priority' => 6],
            ['title' => 'Cor', 'data' => 'color', 'render' => 'text', 'priority' => 7],
            ['title' => 'KM', 'data' => 'current_km', 'render' => 'number', 'priority' => 8],
            $this->dtActionsColumn([
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
                ['label' => 'Excluir', 'icon' => 'trash', 'delete' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['vehicle_label'] = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''));
        return $row;
    }

    public function fillable(): array
    {
        return [
            'client_id', 'plate', 'brand', 'model', 'year', 'color',
            'fipe_brand_code', 'fipe_model_code', 'fipe_year_code', 'fipe_year', 'fipe_value', 'fipe_reference',
        ];
    }

    public function selectOptions(\PDO $pdo, array $data = []): array
    {
        $fipe = new FipeService($pdo);
        $options = ['fipe_brand_code' => $fipe->brandOptions()];

        $clients = [];
        foreach ($pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll() as $row) {
            $clients[(int) $row['id']] = $row['name'];
        }
        $options['client_id'] = $clients;

        $brand = (string) ($data['fipe_brand_code'] ?? '');
        if ($brand !== '') {
            $options['fipe_model_code'] = array_column($fipe->modelsForBrand($brand), 'name', 'code');
        }

        $modelCode = (string) ($data['fipe_model_code'] ?? '');
        if ($brand !== '' && $modelCode !== '') {
            $modelId = $fipe->findModelId($brand, $modelCode);
            if ($modelId) {
                $options['fipe_year_code'] = array_column($fipe->yearsForModel($modelId), 'name', 'code');
            }
        }

        return $options;
    }

    public function prepareData(array $data, ?int $ignoreId, \PDO $pdo): array
    {
        $brandCode = (string) ($data['fipe_brand_code'] ?? '');
        if ($brandCode !== '') {
            $stmt = $pdo->prepare('SELECT name FROM fipe_brands WHERE code = :c');
            $stmt->execute([':c' => $brandCode]);
            $data['brand'] = (string) ($stmt->fetchColumn() ?: '');
        }

        $modelCode = (string) ($data['fipe_model_code'] ?? '');
        if ($brandCode !== '' && $modelCode !== '') {
            $stmt = $pdo->prepare(
                'SELECT m.name FROM fipe_models m JOIN fipe_brands b ON b.id = m.brand_id WHERE b.code = :b AND m.code = :m'
            );
            $stmt->execute([':b' => $brandCode, ':m' => $modelCode]);
            $data['model'] = (string) ($stmt->fetchColumn() ?: '');
        }

        $yearCode = (string) ($data['fipe_year_code'] ?? '');
        if ($yearCode !== '' && preg_match('/(\d{4})/', $yearCode, $m)) {
            $data['fipe_year'] = (int) $m[1];
            if (empty($data['year'])) {
                $data['year'] = (int) $m[1];
            }
        }

        if (array_key_exists('fipe_value', $data) && ($data['fipe_value'] === '' || $data['fipe_value'] === null)) {
            $data['fipe_value'] = null;
        }

        return $data;
    }

    public function fields(): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'select', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'plate', 'label' => 'Placa', 'type' => 'text', 'mask' => 'plate', 'validate' => 'plate', 'unique' => true, 'required' => true, 'max' => 10, 'col' => 'col-md-3'],
            ['name' => 'year', 'label' => 'Ano', 'type' => 'number', 'min' => 1950, 'max' => 2030, 'col' => 'col-md-3'],
            ['name' => 'fipe_brand_code', 'label' => 'Marca (FIPE)', 'type' => 'select', 'col' => 'col-md-6'],
            ['name' => 'fipe_model_code', 'label' => 'Modelo (FIPE)', 'type' => 'select', 'col' => 'col-md-6'],
            ['name' => 'fipe_year_code', 'label' => 'Ano (FIPE)', 'type' => 'select', 'col' => 'col-md-3'],
            ['name' => 'fipe_value', 'label' => 'Valor FIPE', 'type' => 'text', 'col' => 'col-md-3'],
            ['name' => 'color', 'label' => 'Cor', 'type' => 'text', 'max' => 30, 'col' => 'col-md-4'],
        ];
    }
}
