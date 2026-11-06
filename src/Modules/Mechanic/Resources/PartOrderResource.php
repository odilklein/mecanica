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

    public function indexView(): string
    {
        return 'mechanic::part_orders/index';
    }

    public function columns(): array
    {
        return [
            ['field' => 'order_number', 'label' => 'OS'],
            ['field' => 'client_name', 'label' => 'Cliente'],
            ['field' => 'supplier', 'label' => 'Fornecedor'],
            ['field' => 'status', 'label' => 'Status'],
            ['field' => 'expected_date', 'label' => 'Previsão'],
        ];
    }

    public function fillable(): array
    {
        return [
            'service_order_id', 'supplier', 'status', 'expected_date', 'notes',
            'created_by', 'received_by', 'received_at',
        ];
    }

    public function paginate(PDO $pdo, int $page, int $perPage, string $search): array
    {
        $page = max(1, $page);
        $params = [];
        $where = '1=1';

        if ($search !== '') {
            $where = '(so.number LIKE :s OR po.supplier LIKE :s OR c.name LIKE :s)';
            $params[':s'] = '%' . $search . '%';
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
            "SELECT po.*, so.number AS order_number, c.name AS client_name
             {$from} WHERE {$where} ORDER BY po.id DESC LIMIT {$perPage} OFFSET {$offset}"
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
        $orders = [];
        $sql = "SELECT so.id, so.number, c.name
                  FROM service_orders so
                  LEFT JOIN clients c ON c.id = so.client_id
                 WHERE so.status NOT IN ('delivered','cancelled')
                 ORDER BY so.number DESC";
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $orders[(int) $row['id']] = 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT)
                . ($row['name'] ? ' - ' . $row['name'] : '');
        }

        return [
            'service_order_id' => $orders,
            'status' => PartOrderStatus::STATUSES,
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
