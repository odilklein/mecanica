<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Mechanic\Support\BudgetStatus;
use Modules\Mechanic\Support\OrderAccess;
use Modules\Mechanic\Support\PartOrderStatus;
use Modules\Mechanic\Support\ServiceOrderStatus;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Busca global do header (clientes, veículos, OS, orçamentos,
 * pedidos de peças e tickets), respeitando o papel do usuário.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function api(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['query' => '', 'groups' => []], 401);
        }

        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        $query = mb_substr($query, 0, 80);

        if (mb_strlen($query) < 2) {
            return json_response(['query' => $query, 'groups' => []]);
        }

        $like = '%' . addcslashes($query, '\\%_') . '%';
        $digits = preg_replace('/\D+/', '', $query) ?? '';
        $hasNumber = $digits !== '' && strlen($digits) <= 6;
        $number = $hasNumber ? (int) $digits : 0;

        $isAdmin = $this->auth->hasRole(['admin']);
        $canManage = $this->auth->hasRole(['admin', 'secretary', 'lead_mechanic']);

        $groups = [];
        $groups[] = $this->ordersGroup($like, $hasNumber, $number, $query);
        if ($canManage) {
            $groups[] = $this->clientsGroup($like);
            $groups[] = $this->vehiclesGroup($like);
            $groups[] = $this->budgetsGroup($like, $hasNumber, $number);
        }
        $groups[] = $this->partOrdersGroup($like, $hasNumber, $number);
        $groups[] = $this->ticketsGroup($like, $hasNumber, $number, (int) $this->auth->id(), $isAdmin);

        $groups = array_values(array_filter($groups, static fn (array $group): bool => $group['items'] !== []));

        return json_response(['query' => $query, 'groups' => $groups]);
    }

    /** @return array{key: string, label: string, items: array<int, array<string, string>>} */
    private function ordersGroup(string $like, bool $hasNumber, int $number, string $query): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');

        $sql = "SELECT so.id, so.number, so.status, c.name AS client, v.plate, v.model
                  FROM service_orders so
                  LEFT JOIN clients c ON c.id = so.client_id
                  LEFT JOIN vehicles v ON v.id = so.vehicle_id
                 WHERE (c.name LIKE :name OR v.plate LIKE :plate OR v.model LIKE :model
                        OR so.initial_complaint LIKE :complaint" . ($hasNumber ? ' OR so.number = :number' : '') . ')'
            . ($scopeSql !== '' ? ' AND ' . $scopeSql : '') . "
                 ORDER BY so.number DESC
                 LIMIT " . self::LIMIT;

        $params = [
            ':name' => $like,
            ':plate' => $like,
            ':model' => $like,
            ':complaint' => $like,
        ];
        if ($hasNumber) {
            $params[':number'] = $number;
        }
        $params = array_merge($params, $scopeParams);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $statusQuery = $this->statusFromQuery($query);
        $items = [];
        $seen = [];
        foreach ($stmt->fetchAll() as $row) {
            $seen[(int) $row['id']] = true;
            $status = (string) $row['status'];
            $subtitle = trim(($row['client'] ?: 'Sem cliente') . ($row['plate'] ? ' · ' . $row['plate'] : ''));
            $items[] = [
                'name' => 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT),
                'subtitle' => $subtitle,
                'url' => '/service-orders/' . (int) $row['id'],
                'icon' => 'tabler-clipboard-list',
                'badge' => ServiceOrderStatus::label($status),
                'badgeClass' => ServiceOrderStatus::badgeClass($status),
            ];
        }

        // Permite achar pelo nome do status em português (ex.: "entregue").
        if ($statusQuery !== null && count($items) < self::LIMIT) {
            $stmt = $this->pdo->prepare(
                'SELECT so.id, so.number, so.status, c.name AS client, v.plate
                   FROM service_orders so
                   LEFT JOIN clients c ON c.id = so.client_id
                   LEFT JOIN vehicles v ON v.id = so.vehicle_id
                  WHERE so.status = :status'
                . ($scopeSql !== '' ? ' AND ' . $scopeSql : '') . '
                  ORDER BY so.number DESC
                  LIMIT ' . (self::LIMIT - count($items))
            );
            $stmt->execute(array_merge([':status' => $statusQuery], $scopeParams));
            foreach ($stmt->fetchAll() as $row) {
                if (isset($seen[(int) $row['id']])) {
                    continue;
                }
                $items[] = [
                    'name' => 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT),
                    'subtitle' => trim(($row['client'] ?: 'Sem cliente') . ($row['plate'] ? ' · ' . $row['plate'] : '')),
                    'url' => '/service-orders/' . (int) $row['id'],
                    'icon' => 'tabler-clipboard-list',
                    'badge' => ServiceOrderStatus::label((string) $row['status']),
                    'badgeClass' => ServiceOrderStatus::badgeClass((string) $row['status']),
                ];
            }
        }

        return ['key' => 'orders', 'label' => 'Ordens de serviço', 'items' => $items];
    }

    /** @return array{key: string, label: string, items: array<int, array<string, string>>} */
    private function clientsGroup(string $like): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, phone, city, cpf
               FROM clients
              WHERE name LIKE :name OR cpf LIKE :cpf OR phone LIKE :phone OR email LIKE :email
              ORDER BY name
              LIMIT ' . self::LIMIT
        );
        $stmt->execute([':name' => $like, ':cpf' => $like, ':phone' => $like, ':email' => $like]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $details = array_filter([
                $row['city'] ?: null,
                $row['phone'] ?: null,
                $row['cpf'] ?: null,
            ]);
            $items[] = [
                'name' => (string) $row['name'],
                'subtitle' => implode(' · ', $details),
                'url' => '/clients/' . (int) $row['id'],
                'icon' => 'tabler-user',
                'badge' => 'Cliente',
                'badgeClass' => 'bg-label-primary',
            ];
        }

        return ['key' => 'clients', 'label' => 'Clientes', 'items' => $items];
    }

    /** @return array{key: string, label: string, items: array<int, array<string, string>>} */
    private function vehiclesGroup(string $like): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.plate, v.brand, v.model, v.year, c.name AS client
               FROM vehicles v
               LEFT JOIN clients c ON c.id = v.client_id
              WHERE v.plate LIKE :plate OR v.model LIKE :model OR v.brand LIKE :brand OR c.name LIKE :client
              ORDER BY v.plate
              LIMIT ' . self::LIMIT
        );
        $stmt->execute([':plate' => $like, ':model' => $like, ':brand' => $like, ':client' => $like]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $vehicle = trim(($row['brand'] ?: '') . ' ' . ($row['model'] ?: '') . ($row['year'] ? ' ' . $row['year'] : ''));
            $items[] = [
                'name' => (string) $row['plate'],
                'subtitle' => trim($vehicle . ($row['client'] ? ' · ' . $row['client'] : '')),
                'url' => '/vehicles/' . (int) $row['id'] . '/edit',
                'icon' => 'tabler-car',
                'badge' => 'Veículo',
                'badgeClass' => 'bg-label-info',
            ];
        }

        return ['key' => 'vehicles', 'label' => 'Veículos', 'items' => $items];
    }

    /** @return array{key: string, label: string, items: array<int, array<string, string>>} */
    private function budgetsGroup(string $like, bool $hasNumber, int $number): array
    {
        $sql = 'SELECT b.id, b.number, b.status, b.total, b.budget_date, c.name AS client
                  FROM budgets b
                  LEFT JOIN clients c ON c.id = b.client_id
                 WHERE (c.name LIKE :client' . ($hasNumber ? ' OR b.number = :number' : '') . ")
                 ORDER BY b.number DESC
                 LIMIT " . self::LIMIT;

        $params = [':client' => $like];
        if ($hasNumber) {
            $params[':number'] = $number;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $date = $row['budget_date'] ? date('d/m/Y', strtotime((string) $row['budget_date'])) : null;
            $subtitle = trim(($row['client'] ?: 'Sem cliente') . ($date ? ' · ' . $date : '') . ' · R$ ' . number_format((float) $row['total'], 2, ',', '.'));
            $items[] = [
                'name' => 'Orçamento #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT),
                'subtitle' => $subtitle,
                'url' => '/budgets/' . (int) $row['id'],
                'icon' => 'tabler-file-text',
                'badge' => BudgetStatus::label((string) $row['status']),
                'badgeClass' => BudgetStatus::badgeClass((string) $row['status']),
            ];
        }

        return ['key' => 'budgets', 'label' => 'Orçamentos', 'items' => $items];
    }

    /** @return array{key: string, label: string, items: array<int, array<string, string>>} */
    private function partOrdersGroup(string $like, bool $hasNumber, int $number): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');

        $sql = 'SELECT po.id, po.status, po.supplier, so.number, c.name AS client
                  FROM part_orders po
                  INNER JOIN service_orders so ON so.id = po.service_order_id
                  LEFT JOIN clients c ON c.id = so.client_id
                 WHERE (po.supplier LIKE :supplier OR c.name LIKE :client' . ($hasNumber ? ' OR so.number = :number' : '') . ')'
            . ($scopeSql !== '' ? ' AND ' . $scopeSql : '') . "
                 ORDER BY po.id DESC
                 LIMIT " . self::LIMIT;

        $params = [':supplier' => $like, ':client' => $like];
        if ($hasNumber) {
            $params[':number'] = $number;
        }
        $params = array_merge($params, $scopeParams);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $subtitle = 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT)
                . ($row['client'] ? ' · ' . $row['client'] : '');
            $items[] = [
                'name' => $row['supplier'] ?: 'Pedido de peças',
                'subtitle' => $subtitle,
                'url' => '/part-orders/' . (int) $row['id'] . '/edit',
                'icon' => 'tabler-truck',
                'badge' => PartOrderStatus::label((string) $row['status']),
                'badgeClass' => PartOrderStatus::badgeClass((string) $row['status']),
            ];
        }

        return ['key' => 'part_orders', 'label' => 'Pedidos de peças', 'items' => $items];
    }

    /** @return array{key: string, label: string, items: array<int, array<string, string>>} */
    private function ticketsGroup(string $like, bool $hasNumber, int $number, int $userId, bool $isAdmin): array
    {
        $sql = 'SELECT t.id, t.subject, t.status, so.number, c.name AS client
                  FROM tickets t
                  LEFT JOIN service_orders so ON so.id = t.service_order_id
                  LEFT JOIN clients c ON c.id = so.client_id
                 WHERE (t.subject LIKE :subject OR c.name LIKE :client' . ($hasNumber ? ' OR so.number = :number' : '') . ')
                   AND (:is_admin = 1 OR t.created_by = :uid_created OR t.assigned_to = :uid_assigned
                        OR so.created_by = :uid_os_created OR so.lead_mechanic_id = :uid_os_lead
                        OR EXISTS (SELECT 1 FROM service_order_responsibles r_p
                                    WHERE r_p.order_id = so.id AND r_p.user_id = :uid_os_resp))
                 ORDER BY t.updated_at DESC
                 LIMIT ' . self::LIMIT;

        $params = [
            ':subject' => $like,
            ':client' => $like,
            ':is_admin' => $isAdmin ? 1 : 0,
            ':uid_created' => $userId,
            ':uid_assigned' => $userId,
            ':uid_os_created' => $userId,
            ':uid_os_lead' => $userId,
            ':uid_os_resp' => $userId,
        ];
        if ($hasNumber) {
            $params[':number'] = $number;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'name' => (string) $row['subject'],
                'subtitle' => $row['number'] !== null
                    ? 'OS #' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT) . ($row['client'] ? ' · ' . $row['client'] : '')
                    : 'Tickets',
                'url' => '/tickets?ticket=' . (int) $row['id'],
                'icon' => 'tabler-messages',
                'badge' => ((string) $row['status']) === 'open' ? 'Aberto' : 'Encerrado',
                'badgeClass' => ((string) $row['status']) === 'open' ? 'bg-label-warning' : 'bg-label-secondary',
            ];
        }

        return ['key' => 'tickets', 'label' => 'Tickets', 'items' => $items];
    }

    /** Converte "entregue", "em análise" etc. para a chave do status. */
    private function statusFromQuery(string $query): ?string
    {
        $normalized = mb_strtolower(trim($query));
        foreach (ServiceOrderStatus::STATUSES as $key => $label) {
            if (mb_strtolower($label) === $normalized) {
                return $key;
            }
        }
        return null;
    }
}
