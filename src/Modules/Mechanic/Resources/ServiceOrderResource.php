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

    public function createRoles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function formView(): string
    {
        return 'mechanic::service_orders/form';
    }

    public function datatableSubtitle(): string
    {
        return 'Acompanhe o trabalho da oficina';
    }

    public function datatableNewLabel(): string
    {
        return 'Nova OS';
    }

    /** A listagem não tem ações em massa: sem checkboxes. */
    public function selectable(): bool
    {
        return false;
    }

    public function formScripts(): array
    {
        return ['/js/service-order-form.js'];
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'status', 'label' => 'Status', 'options' => ServiceOrderStatus::STATUSES],
            ['field' => 'priority', 'label' => 'Prioridade', 'options' => ServiceOrderStatus::PRIORITIES],
            ['field' => 'lead_mechanic', 'label' => 'Responsável'],
        ];
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

    public function datatableColumns(): array
    {
        $statuses = $this->dtBadges(
            ServiceOrderStatus::STATUSES,
            static fn (string $status): string => ServiceOrderStatus::badgeClass($status)
        );
        $priorities = $this->dtBadges(
            ServiceOrderStatus::PRIORITIES,
            static fn (string $priority): string => ServiceOrderStatus::priorityBadge($priority)
        );

        return [
            $this->dtControlColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'OS',
                'data' => 'number_label',
                'render' => 'link',
                'href' => $this->path() . '/{id}',
                'priority' => 1,
            ],
            ['title' => 'Cliente', 'data' => 'client_name', 'render' => 'text', 'priority' => 5],
            ['title' => 'Veículo', 'data' => 'vehicle_label', 'render' => 'text', 'priority' => 6],
            ['title' => 'Responsável', 'data' => 'lead_mechanic', 'render' => 'text', 'priority' => 6],
            ['title' => 'Status', 'data' => 'status', 'render' => 'badge', 'badge' => $statuses, 'priority' => 4],
            ['title' => 'Prioridade', 'data' => 'priority', 'render' => 'badge', 'badge' => $priorities, 'priority' => 3],
            ['title' => 'Entrada', 'data' => 'entry_date', 'render' => 'date', 'priority' => 7],
            $this->dtActionsColumn([
                ['label' => 'Detalhes', 'icon' => 'eye', 'href' => $this->path() . '/{id}'],
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true, 'roles' => ['admin', 'secretary', 'lead_mechanic']],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['number_label'] = '#' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT);
        $row['vehicle_label'] = trim(
            ($row['vehicle_plate'] ?? '') . (($row['vehicle_model'] ?? '') ? ' · ' . $row['vehicle_model'] : '')
        );
        return $row;
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
    public function paginate(PDO $pdo, int $page, int $perPage, string $search, ?int $scopeUserId = null): array
    {
        $page = max(1, $page);
        $params = [];
        $where = '1=1';

        if ($search !== '') {
            $where = "CONCAT_WS(' ', so.number, c.name, v.plate, v.model) LIKE :s";
            $params[':s'] = '%' . $search . '%';
        }

        if ($scopeUserId !== null) {
            $where .= " AND (so.created_by = :scope_uid_created OR so.lead_mechanic_id = :scope_uid_lead
                OR EXISTS (SELECT 1 FROM service_order_responsibles sr
                            WHERE sr.order_id = so.id AND sr.user_id = :scope_uid_resp))";
            $params[':scope_uid_created'] = $scopeUserId;
            $params[':scope_uid_lead'] = $scopeUserId;
            $params[':scope_uid_resp'] = $scopeUserId;
        }

        $from = 'FROM service_orders so
                 LEFT JOIN clients c ON c.id = so.client_id
                 LEFT JOIN vehicles v ON v.id = so.vehicle_id
                 LEFT JOIN users lm ON lm.id = so.lead_mechanic_id';

        $count = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT so.*, c.name AS client_name, v.plate AS vehicle_plate, v.model AS vehicle_model,
                    lm.name AS lead_mechanic
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
        foreach ($pdo->query('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name')->fetchAll() as $row) {
            $clients[(int) $row['id']] = $row['name'];
        }

        // Todos os veículos ativos + mapa cliente → veículo. O formulário de
        // edição filtra no navegador quando o cliente muda.
        $vehicles = [];
        $vehicleClients = [];
        foreach ($pdo->query('SELECT id, client_id, plate, model FROM vehicles WHERE is_active = 1 ORDER BY plate')->fetchAll() as $row) {
            $vehicleId = (int) $row['id'];
            $vehicles[$vehicleId] = trim(($row['plate'] ?? '') . ' - ' . ($row['model'] ?? ''));
            $vehicleClients[$vehicleId] = (int) ($row['client_id'] ?? 0);
        }

        $mechanics = [];
        foreach ($pdo->query("SELECT id, name FROM users WHERE is_active = 1 AND role IN ('lead_mechanic','mechanic') ORDER BY name")->fetchAll() as $row) {
            $mechanics[(int) $row['id']] = $row['name'];
        }

        // Na edição, só o status atual e os próximos válidos. "Entregue" é
        // concluído exclusivamente pelo registro de entrega/retirada.
        $currentStatus = (string) ($data['status'] ?? '');
        if ($currentStatus !== '' && isset(ServiceOrderStatus::STATUSES[$currentStatus])) {
            $statuses = [];
            foreach (array_unique(array_merge([$currentStatus], ServiceOrderStatus::next($currentStatus))) as $status) {
                // "Entregue" nunca é escolhido na edição, exceto se já for o
                // status atual (aí aparece somente para leitura).
                if ($status === 'delivered' && $status !== $currentStatus) {
                    continue;
                }
                $statuses[$status] = ServiceOrderStatus::STATUSES[$status];
            }
        } else {
            $statuses = ServiceOrderStatus::STATUSES;
        }

        return [
            'client_id' => $clients,
            'vehicle_id' => $vehicles,
            'vehicle_clients' => $vehicleClients,
            'lead_mechanic_id' => $mechanics,
            'status' => $statuses,
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
