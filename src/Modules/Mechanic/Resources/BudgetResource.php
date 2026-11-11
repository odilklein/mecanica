<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;
use Modules\Mechanic\Support\BudgetStatus;
use PDO;

class BudgetResource extends Resource
{
    public function key(): string
    {
        return 'budgets';
    }

    public function table(): string
    {
        return 'budgets';
    }

    public function labels(): array
    {
        return ['singular' => 'Orçamento', 'plural' => 'Orçamentos'];
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function datatableSubtitle(): string
    {
        return 'Gerencie os orçamentos da oficina';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo orçamento';
    }

    /** A listagem não tem ações em massa: sem checkboxes. */
    public function selectable(): bool
    {
        return false;
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'status', 'label' => 'Status', 'options' => BudgetStatus::STATUSES],
            ['field' => 'client_name', 'label' => 'Cliente'],
        ];
    }

    public function columns(): array
    {
        return [
            ['field' => 'number', 'label' => 'Nº'],
            ['field' => 'client_name', 'label' => 'Cliente'],
            ['field' => 'vehicle_plate', 'label' => 'Veículo'],
            ['field' => 'status', 'label' => 'Status'],
            ['field' => 'total', 'label' => 'Total'],
            ['field' => 'budget_date', 'label' => 'Data'],
        ];
    }

    public function datatableColumns(): array
    {
        $statuses = $this->dtBadges(
            BudgetStatus::STATUSES,
            static fn (string $status): string => BudgetStatus::badgeClass($status)
        );

        return [
            $this->dtControlColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Nº',
                'data' => 'number_label',
                'render' => 'link',
                'href' => $this->path() . '/{id}',
                'priority' => 1,
            ],
            ['title' => 'Cliente', 'data' => 'client_name', 'render' => 'text', 'priority' => 5],
            ['title' => 'Veículo', 'data' => 'vehicle_label', 'render' => 'text', 'priority' => 6],
            ['title' => 'OS', 'data' => 'order_number_label', 'render' => 'text', 'priority' => 7],
            ['title' => 'Status', 'data' => 'status', 'render' => 'badge', 'badge' => $statuses, 'priority' => 4],
            ['title' => 'Total', 'data' => 'total', 'render' => 'money', 'priority' => 7, 'className' => 'text-nowrap'],
            ['title' => 'Data', 'data' => 'budget_date', 'render' => 'date', 'priority' => 8],
            ['title' => 'Validade', 'data' => 'valid_until', 'render' => 'date', 'priority' => 8],
            $this->dtActionsColumn([
                ['label' => 'Detalhes', 'icon' => 'eye', 'href' => $this->path() . '/{id}'],
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
                ['label' => 'Excluir', 'icon' => 'trash', 'delete' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['number_label'] = '#' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT);
        $row['vehicle_label'] = trim(
            ($row['vehicle_plate'] ?? '') . (($row['vehicle_model'] ?? '') ? ' · ' . $row['vehicle_model'] : '')
        );
        $row['order_number_label'] = !empty($row['order_number'])
            ? '#' . str_pad((string) $row['order_number'], 4, '0', STR_PAD_LEFT)
            : '—';
        return $row;
    }

    public function fillable(): array
    {
        return [
            'number', 'client_id', 'vehicle_id', 'service_order_id',
            'budget_date', 'valid_until', 'status', 'discount',
            'parts_total', 'labor_total', 'total', 'notes', 'created_by',
        ];
    }

    public function paginate(PDO $pdo, int $page, int $perPage, string $search): array
    {
        $page = max(1, $page);
        $params = [];
        $where = '1=1';

        if ($search !== '') {
            $where = "CONCAT_WS(' ', b.number, c.name, v.plate, v.model, so.number) LIKE :s";
            $params[':s'] = '%' . $search . '%';
        }

        $from = 'FROM budgets b
                 LEFT JOIN clients c ON c.id = b.client_id
                 LEFT JOIN vehicles v ON v.id = b.vehicle_id
                 LEFT JOIN service_orders so ON so.id = b.service_order_id';

        $count = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT b.*, c.name AS client_name, v.plate AS vehicle_plate, v.model AS vehicle_model,
                    so.number AS order_number
             {$from} WHERE {$where} ORDER BY b.number DESC LIMIT {$perPage} OFFSET {$offset}"
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
        foreach ($pdo->query('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name')->fetchAll() as $row) {
            $clients[(int) $row['id']] = $row['name'];
        }

        $vehicles = [];
        foreach ($pdo->query('SELECT id, plate, model FROM vehicles WHERE is_active = 1 ORDER BY plate')->fetchAll() as $row) {
            $vehicles[(int) $row['id']] = trim(($row['plate'] ?? '') . ' - ' . ($row['model'] ?? ''));
        }

        $orders = [];
        $orderClients = [];
        $orderVehicles = [];
        $orderStmt = $pdo->query(
            'SELECT so.id, so.number, so.client_id, so.vehicle_id, c.name AS client_name
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
              ORDER BY so.number DESC'
        );
        foreach ($orderStmt->fetchAll() as $row) {
            $orderId = (int) $row['id'];
            $orders[$orderId] = 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT)
                . (!empty($row['client_name']) ? ' - ' . $row['client_name'] : '');
            $orderClients[$orderId] = (int) ($row['client_id'] ?? 0);
            $orderVehicles[$orderId] = (int) ($row['vehicle_id'] ?? 0);
        }

        return [
            'client_id' => $clients,
            'vehicle_id' => $vehicles,
            'service_order_id' => $orders,
            'order_clients' => $orderClients,
            'order_vehicles' => $orderVehicles,
            'status' => BudgetStatus::STATUSES,
        ];
    }

    public function prepareData(array $data, ?int $ignoreId, PDO $pdo): array
    {
        if ($ignoreId === null) {
            $data['number'] = (int) $pdo->query('SELECT COALESCE(MAX(number), 0) + 1 FROM budgets')->fetchColumn();
            $data['created_by'] = $_SESSION['auth_user_id'] ?? null;
        }

        if (empty($data['budget_date'])) {
            $data['budget_date'] = date('Y-m-d');
        }

        $parts = 0.0;
        $labor = 0.0;
        if ($ignoreId !== null) {
            $stmt = $pdo->prepare('SELECT parts_total, labor_total FROM budgets WHERE id = :id');
            $stmt->execute([':id' => $ignoreId]);
            $row = $stmt->fetch();
            $parts = (float) ($row['parts_total'] ?? 0);
            $labor = (float) ($row['labor_total'] ?? 0);
        }

        $discount = (float) ($data['discount'] ?? 0);
        $data['parts_total'] = $parts;
        $data['labor_total'] = $labor;
        $data['total'] = max(0, $parts + $labor - $discount);

        return $data;
    }

    public function fields(): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'select', 'icon' => 'user', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'vehicle_id', 'label' => 'Veículo', 'type' => 'select', 'icon' => 'car', 'col' => 'col-md-6'],
            ['name' => 'service_order_id', 'label' => 'Ordem de serviço', 'type' => 'select', 'icon' => 'clipboard-list', 'col' => 'col-md-6'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'icon' => 'activity', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'budget_date', 'label' => 'Data', 'type' => 'date', 'icon' => 'calendar', 'col' => 'col-md-4'],
            ['name' => 'valid_until', 'label' => 'Validade', 'type' => 'date', 'icon' => 'calendar-event', 'col' => 'col-md-4'],
            ['name' => 'discount', 'label' => 'Desconto (R$)', 'type' => 'number', 'icon' => 'discount', 'min' => 0, 'step' => '0.01', 'col' => 'col-md-4'],
            ['name' => 'notes', 'label' => 'Observações', 'type' => 'textarea', 'icon' => 'notes'],
        ];
    }
}
