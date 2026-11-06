<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;
use Modules\Mechanic\Support\ServiceOrderStatus;
use PDO;

class ServiceOrderResource extends Resource
{
    public function key(): string
    {
        return 'service-orders';
    }

    public function table(): string
    {
        return 'service_orders';
    }

    public function labels(): array
    {
        return ['singular' => 'OS', 'plural' => 'Ordens de serviço'];
    }

    public function icon(): string
    {
        return 'clipboard-list';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic', 'mechanic'];
    }

    public function indexView(): string
    {
        return 'mechanic::service_orders/index';
    }

    public function formView(): string
    {
        return 'mechanic::service_orders/form';
    }

    public function columns(): array
    {
        return [
            ['field' => 'number', 'label' => 'Nº'],
            ['field' => 'client_name', 'label' => 'Cliente'],
            ['field' => 'vehicle_plate', 'label' => 'Veículo'],
            ['field' => 'status', 'label' => 'Status'],
            ['field' => 'priority', 'label' => 'Prioridade'],
            ['field' => 'entry_date', 'label' => 'Entrada'],
        ];
    }

    public function fillable(): array
    {
        return [
            'number', 'client_id', 'vehicle_id', 'status', 'priority',
            'initial_complaint', 'diagnosis', 'entry_date', 'completion_date',
            'lead_mechanic_id', 'created_by',
        ];
    }

    /** Listagem com JOIN em cliente/veículo. */
    public function paginate(PDO $pdo, int $page, int $perPage, string $search): array
    {
        $page = max(1, $page);
        $params = [];
        $where = '1=1';

        if ($search !== '') {
            $where = '(so.number LIKE :s OR c.name LIKE :s OR v.plate LIKE :s OR v.model LIKE :s)';
            $params[':s'] = '%' . $search . '%';
        }

        $from = 'FROM service_orders so
                 LEFT JOIN clients c ON c.id = so.client_id
                 LEFT JOIN vehicles v ON v.id = so.vehicle_id';

        $count = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT so.*, c.name AS client_name, v.plate AS vehicle_plate, v.model AS vehicle_model
             {$from} WHERE {$where} ORDER BY so.number DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    public function selectOptions(PDO $pdo, array $data = []): array
    {
        $clients = [];
        foreach ($pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll() as $row) {
            $clients[(int) $row['id']] = $row['name'];
        }

        $vehicles = [];
        foreach ($pdo->query("SELECT id, plate, model FROM vehicles WHERE is_active = 1 ORDER BY plate")->fetchAll() as $row) {
            $vehicles[(int) $row['id']] = trim(($row['plate'] ?? '') . ' - ' . ($row['model'] ?? ''));
        }

        $mechanics = [];
        foreach ($pdo->query("SELECT id, name FROM users WHERE is_active = 1 AND role IN ('lead_mechanic','mechanic') ORDER BY name")->fetchAll() as $row) {
            $mechanics[(int) $row['id']] = $row['name'];
        }

        return [
            'client_id' => $clients,
            'vehicle_id' => $vehicles,
            'lead_mechanic_id' => $mechanics,
            'status' => ServiceOrderStatus::STATUSES,
            'priority' => ServiceOrderStatus::PRIORITIES,
        ];
    }

    public function prepareData(array $data, ?int $ignoreId, PDO $pdo): array
    {
        if ($ignoreId === null) {
            $data['number'] = (int) $pdo->query('SELECT COALESCE(MAX(number), 0) + 1 FROM service_orders')->fetchColumn();
            $data['created_by'] = $_SESSION['auth_user_id'] ?? null;
        }

        if (empty($data['entry_date'])) {
            $data['entry_date'] = date('Y-m-d');
        }

        return $data;
    }

    public function fields(): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'select', 'icon' => 'user', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'vehicle_id', 'label' => 'Veículo', 'type' => 'select', 'icon' => 'car', 'col' => 'col-md-6'],
            ['name' => 'priority', 'label' => 'Prioridade', 'type' => 'select', 'icon' => 'flag', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'icon' => 'activity', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'entry_date', 'label' => 'Entrada', 'type' => 'date', 'icon' => 'calendar', 'col' => 'col-md-4'],
            ['name' => 'lead_mechanic_id', 'label' => 'Responsável', 'type' => 'select', 'icon' => 'user-check', 'col' => 'col-md-6'],
            ['name' => 'initial_complaint', 'label' => 'Queixa inicial', 'type' => 'textarea', 'icon' => 'message-2'],
            ['name' => 'diagnosis', 'label' => 'Diagnóstico', 'type' => 'textarea', 'icon' => 'stethoscope'],
        ];
    }
}
