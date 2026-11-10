<?php
declare(strict_types=1);

namespace Modules\Mechanic\Resources;

use Kernel\Crud\Resource;
use Modules\Mechanic\Support\PartOrderStatus;
use PDO;

class PartOrderResource extends Resource
{
    public function key(): string
    {
        return 'part-orders';
    }

    public function table(): string
    {
        return 'part_orders';
    }

    public function labels(): array
    {
        return ['singular' => 'Pedido de peça', 'plural' => 'Pedidos de peças'];
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic', 'mechanic'];
    }

    public function formView(): string
    {
        return 'mechanic::part_orders/form';
    }

    public function datatableSubtitle(): string
    {
        return 'Acompanhe as peças solicitadas nas ordens de serviço';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo pedido';
    }

    public function columns(): array
    {
        return [
            ['field' => 'order_number', 'label' => 'OS'],
            ['field' => 'client_name', 'label' => 'Cliente'],
            ['field' => 'items', 'label' => 'Itens'],
            ['field' => 'supplier', 'label' => 'Fornecedor'],
            ['field' => 'status', 'label' => 'Status'],
            ['field' => 'expected_date', 'label' => 'Previsão'],
        ];
    }

    public function datatableColumns(): array
    {
        $statuses = $this->dtBadges(
            PartOrderStatus::STATUSES,
            static fn (string $status): string => PartOrderStatus::badgeClass($status)
        );

        return [
            $this->dtControlColumn(),
            $this->dtSelectColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'OS',
                'data' => 'order_number_label',
                'render' => 'link',
                'href' => '/service-orders/{service_order_id}',
                'priority' => 1,
            ],
            ['title' => 'Cliente', 'data' => 'client_name', 'render' => 'text', 'priority' => 5],
            ['title' => 'Itens', 'data' => 'items', 'render' => 'text', 'priority' => 6],
            ['title' => 'Fornecedor', 'data' => 'supplier', 'render' => 'text', 'priority' => 7],
            ['title' => 'Status', 'data' => 'status', 'render' => 'badge', 'badge' => $statuses, 'priority' => 4],
            ['title' => 'Previsão', 'data' => 'expected_date', 'render' => 'date', 'priority' => 8],
            $this->dtActionsColumn([
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true, 'roles' => ['admin', 'secretary', 'lead_mechanic']],
                ['label' => 'Excluir', 'icon' => 'trash', 'delete' => true, 'roles' => ['admin', 'secretary', 'lead_mechanic']],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['order_number_label'] = !empty($row['order_number'])
            ? '#' . str_pad((string) $row['order_number'], 4, '0', STR_PAD_LEFT)
            : '—';
        return $row;
    }

    public function fillable(): array
    {
        return [
            'service_order_id', 'supplier', 'status', 'expected_date', 'notes',
            'created_by', 'received_by', 'received_at',
        ];
    }

    public function paginate(PDO $pdo, int $page, int $perPage, string $search, ?int $scopeUserId = null): array
    {
        $page = max(1, $page);
        $params = [];
        $where = '1=1';

        if ($search !== '') {
            $where = '(so.number LIKE :s OR po.supplier LIKE :s OR c.name LIKE :s)';
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

        $from = 'FROM part_orders po
                 LEFT JOIN service_orders so ON so.id = po.service_order_id
                 LEFT JOIN clients c ON c.id = so.client_id';

        $count = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT po.*, so.number AS order_number, c.name AS client_name,
                    GROUP_CONCAT(CONCAT(i.description, ' ×', i.quantity) SEPARATOR ', ') AS items
             {$from}
             LEFT JOIN part_order_items i ON i.part_order_id = po.id
             WHERE {$where} GROUP BY po.id ORDER BY po.id DESC LIMIT {$perPage} OFFSET {$offset}"
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

    public function selectOptions(
        PDO $pdo,
        array $data = [],
        ?int $scopeUserId = null,
        bool $canManageStatus = true
    ): array {
        $orders = [];
        $sql = "SELECT so.id, so.number, c.name
                  FROM service_orders so
                  LEFT JOIN clients c ON c.id = so.client_id
                 WHERE so.status NOT IN ('delivered','cancelled')";
        $params = [];
        if ($scopeUserId !== null) {
            $sql .= " AND (so.created_by = :scope_uid_created OR so.lead_mechanic_id = :scope_uid_lead
                        OR EXISTS (SELECT 1 FROM service_order_responsibles sr
                                    WHERE sr.order_id = so.id AND sr.user_id = :scope_uid_resp))";
            $params[':scope_uid_created'] = $scopeUserId;
            $params[':scope_uid_lead'] = $scopeUserId;
            $params[':scope_uid_resp'] = $scopeUserId;
        }
        $sql .= ' ORDER BY so.number DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $orders[(int) $row['id']] = 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT)
                . ($row['name'] ? ' - ' . $row['name'] : '');
        }

        $suppliers = [];
        $currentSupplier = (string) ($data['supplier'] ?? '');
        try {
            foreach ($pdo->query('SELECT name FROM suppliers WHERE is_active = 1 ORDER BY name')->fetchAll() as $row) {
                $suppliers[(string) $row['name']] = (string) $row['name'];
            }
        } catch (\Throwable $e) {
            // Tabela ainda não migrada: mantém apenas o valor atual.
        }
        if ($currentSupplier !== '' && !isset($suppliers[$currentSupplier])) {
            $suppliers[$currentSupplier] = $currentSupplier . ' (legado)';
        }

        // Mecânico só pode solicitar a peça: o status inicial é sempre
        // "Solicitado" e os demais ficam com a gestão da oficina.
        $statuses = $canManageStatus
            ? PartOrderStatus::STATUSES
            : ['requested' => PartOrderStatus::STATUSES['requested']];

        return [
            'service_order_id' => $orders,
            'supplier' => $suppliers,
            'status' => $statuses,
        ];
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'supplier', 'label' => 'Fornecedor'],
        ];
    }

    public function prepareData(array $data, ?int $ignoreId, PDO $pdo): array
    {
        $userId = $_SESSION['auth_user_id'] ?? null;

        if ($ignoreId === null) {
            $data['created_by'] = $userId;
        }

        if (($data['status'] ?? '') === 'received') {
            $data['received_at'] = date('Y-m-d H:i:s');
            $data['received_by'] = $userId;
        } else {
            $data['received_at'] = null;
            $data['received_by'] = null;
        }

        return $data;
    }

    public function fields(): array
    {
        return [
            ['name' => 'service_order_id', 'label' => 'Ordem de serviço', 'type' => 'select', 'icon' => 'clipboard-list', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'supplier', 'label' => 'Fornecedor', 'type' => 'text', 'icon' => 'building-store', 'max' => 120, 'col' => 'col-md-6', 'placeholder' => 'Ex.: Auto Peças Silva'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'icon' => 'activity', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'expected_date', 'label' => 'Previsão de entrega', 'type' => 'date', 'icon' => 'calendar', 'col' => 'col-md-4'],
            ['name' => 'notes', 'label' => 'Observações', 'type' => 'textarea', 'icon' => 'notes', 'placeholder' => 'Ex.: peça original, prazo do fornecedor...'],
        ];
    }
}
