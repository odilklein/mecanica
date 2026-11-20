<?php
// =============================================
// TICKETS — regras de acesso, listagem, mensagens
// e leitura por usuário.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Services;

use PDO;

class TicketService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Tickets visíveis para o usuário, com última mensagem e não lidas.
     *
     * @return array<int, array>
     */
    public function visibleTickets(
        int $userId,
        bool $isAdmin,
        int $limit = 50,
        ?string $status = null,
        string $search = '',
        int $offset = 0
    ): array {
        $where = ['(:is_admin = 1 OR t.created_by = :uid_created OR t.assigned_to = :uid_assigned
                    OR EXISTS (
                        SELECT 1 FROM service_orders so_p
                         WHERE so_p.id = t.service_order_id
                           AND (so_p.created_by = :uid_os_created
                                OR so_p.lead_mechanic_id = :uid_os_lead
                                OR EXISTS (SELECT 1 FROM service_order_responsibles r_p
                                            WHERE r_p.order_id = so_p.id AND r_p.user_id = :uid_os_resp)))
                   )'];
        $params = [
            ':is_admin' => $isAdmin ? 1 : 0,
            ':uid_created' => $userId,
            ':uid_assigned' => $userId,
            ':uid_read' => $userId,
            ':uid_join' => $userId,
            ':uid_os_created' => $userId,
            ':uid_os_lead' => $userId,
            ':uid_os_resp' => $userId,
        ];

        if ($status !== null && $status !== '') {
            $where[] = 't.status = :status';
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where[] = '(t.subject LIKE :search_subject OR c.name LIKE :search_client OR so.number LIKE :search_number)';
            $params[':search_subject'] = '%' . $search . '%';
            $params[':search_client'] = '%' . $search . '%';
            $params[':search_number'] = '%' . $search . '%';
        }

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT t.*, so.number AS order_number, c.name AS client_name,
                       creator.name AS creator_name, assigned.name AS assigned_name,
                       (SELECT m.message FROM ticket_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                       (SELECT m.created_at FROM ticket_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
                       (SELECT COUNT(*) FROM ticket_messages m
                         WHERE m.ticket_id = t.id AND m.user_id <> :uid_read
                           AND (r.last_read_message_id IS NULL OR m.id > r.last_read_message_id)) AS unread
                  FROM tickets t
                  LEFT JOIN service_orders so ON so.id = t.service_order_id
                  LEFT JOIN clients c ON c.id = so.client_id
                  LEFT JOIN users creator ON creator.id = t.created_by
                  LEFT JOIN users assigned ON assigned.id = t.assigned_to
                  LEFT JOIN ticket_reads r ON r.ticket_id = t.id AND r.user_id = :uid_join
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY COALESCE(last_message_at, t.created_at) DESC, t.id DESC
                 LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Contagens das abas do chat para o usuário atual.
     *
     * "Abertos" e "Encerrados" são totais; "Não lidos"/"Lidos" consideram
     * apenas tickets abertos, para o mesmo conjunto exibido na lista.
     *
     * @return array{open:int, unread:int, read:int, closed:int}
     */
    public function tabCounts(int $userId, bool $isAdmin): array
    {
        $sql = <<<'SQL'
            SELECT
                COALESCE(SUM(t.status = 'open'), 0) AS open_total,
                COALESCE(SUM(t.status = 'closed'), 0) AS closed_total,
                COALESCE(SUM(t.status = 'open' AND COALESCE(unread.total, 0) > 0), 0) AS unread_total,
                COALESCE(SUM(t.status = 'open' AND COALESCE(unread.total, 0) = 0), 0) AS read_total
              FROM tickets t
              LEFT JOIN service_orders so ON so.id = t.service_order_id
              LEFT JOIN (
                    SELECT m.ticket_id, COUNT(*) AS total
                      FROM ticket_messages m
                      LEFT JOIN ticket_reads r ON r.ticket_id = m.ticket_id AND r.user_id = :uid_read
                     WHERE m.user_id <> :uid_read_own
                       AND (r.last_read_message_id IS NULL OR m.id > r.last_read_message_id)
                     GROUP BY m.ticket_id
              ) unread ON unread.ticket_id = t.id
             WHERE (:is_admin = 1 OR t.created_by = :uid_created OR t.assigned_to = :uid_assigned
                    OR EXISTS (
                        SELECT 1 FROM service_orders so_p
                         WHERE so_p.id = t.service_order_id
                           AND (so_p.created_by = :uid_os_created
                                OR so_p.lead_mechanic_id = :uid_os_lead
                                OR EXISTS (SELECT 1 FROM service_order_responsibles r_p
                                            WHERE r_p.order_id = so_p.id AND r_p.user_id = :uid_os_resp)))
                   )
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':is_admin' => $isAdmin ? 1 : 0,
            ':uid_read' => $userId,
            ':uid_read_own' => $userId,
            ':uid_created' => $userId,
            ':uid_assigned' => $userId,
            ':uid_os_created' => $userId,
            ':uid_os_lead' => $userId,
            ':uid_os_resp' => $userId,
        ]);
        $row = $stmt->fetch() ?: [];

        return [
            'open' => (int) ($row['open_total'] ?? 0),
            'unread' => (int) ($row['unread_total'] ?? 0),
            'read' => (int) ($row['read_total'] ?? 0),
            'closed' => (int) ($row['closed_total'] ?? 0),
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, so.number AS order_number, c.name AS client_name,
                    creator.name AS creator_name, assigned.name AS assigned_name
               FROM tickets t
               LEFT JOIN service_orders so ON so.id = t.service_order_id
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN users creator ON creator.id = t.created_by
               LEFT JOIN users assigned ON assigned.id = t.assigned_to
              WHERE t.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE service_order_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function canAccess(array $ticket, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin
            || (int) $ticket['created_by'] === $userId
            || (int) ($ticket['assigned_to'] ?? 0) === $userId) {
            return true;
        }

        // Participantes da OS também acessam a conversa vinculada.
        $orderId = (int) ($ticket['service_order_id'] ?? 0);
        if ($orderId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM service_orders so
                  WHERE so.id = :oid
                    AND (so.created_by = :uid_created OR so.lead_mechanic_id = :uid_lead
                         OR EXISTS (SELECT 1 FROM service_order_responsibles r
                                     WHERE r.order_id = so.id AND r.user_id = :uid_resp))
                  LIMIT 1'
            );
            $stmt->execute([
                ':oid' => $orderId,
                ':uid_created' => $userId,
                ':uid_lead' => $userId,
                ':uid_resp' => $userId,
            ]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    public function canAccessOrder(array $order, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ((int) ($order['created_by'] ?? 0) === $userId) {
            return true;
        }
        if ((int) ($order['lead_mechanic_id'] ?? 0) === $userId) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM service_order_responsibles WHERE order_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $order['id'], $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * IDs dos participantes do ticket: criador/responsável do ticket,
     * criador/responsável da OS e mecânicos designados.
     *
     * @return int[]
     */
    public function participants(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.created_by, t.assigned_to, t.service_order_id,
                    so.created_by AS os_created_by, so.lead_mechanic_id
               FROM tickets t
               LEFT JOIN service_orders so ON so.id = t.service_order_id
              WHERE t.id = ?'
        );
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }

        $ids = [
            (int) ($row['created_by'] ?? 0),
            (int) ($row['assigned_to'] ?? 0),
            (int) ($row['os_created_by'] ?? 0),
            (int) ($row['lead_mechanic_id'] ?? 0),
        ];

        $orderId = (int) ($row['service_order_id'] ?? 0);
        if ($orderId > 0) {
            $resp = $this->pdo->prepare('SELECT user_id FROM service_order_responsibles WHERE order_id = ?');
            $resp->execute([$orderId]);
            foreach ($resp->fetchAll(PDO::FETCH_COLUMN) as $userId) {
                $ids[] = (int) $userId;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $uid): bool => $uid > 0)));
    }

    /**
     * Garante o ticket da OS e retorna o id.
     *
     * @param array{subject?: string, assigned_to?: int|null} $overrides
     *        Personaliza o assunto e o responsável na criação.
     */
    public function ensureForOrder(int $orderId, array $overrides = []): int
    {
        $existing = $this->findByOrder($orderId);
        if ($existing) {
            $this->ensureOrderSummary((int) $existing['id'], $orderId);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT so.id, so.number, so.created_by, so.lead_mechanic_id, c.name AS client_name
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
              WHERE so.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new \RuntimeException('OS não encontrada para o ticket.');
        }

        $osLabel = 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT);
        $defaultSubject = $osLabel . ' - ' . ($order['client_name'] ?: 'Cliente');

        $subject = trim((string) ($overrides['subject'] ?? ''));
        if ($subject === '') {
            $subject = $defaultSubject;
        } elseif (!str_starts_with($subject, 'OS #')) {
            // Mantém o vínculo visível mesmo quando o assunto é personalizado.
            $subject = $osLabel . ' - ' . $subject;
        }

        $assignedTo = (int) ($overrides['assigned_to'] ?? 0)
            ?: ($order['lead_mechanic_id'] ? (int) $order['lead_mechanic_id'] : null);

        $this->pdo->prepare(
            'INSERT INTO tickets (subject, service_order_id, created_by, assigned_to, status)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($subject, 0, 200),
            $orderId,
            (int) ($order['created_by'] ?: 1),
            $assignedTo,
            'open',
        ]);

        $ticketId = (int) $this->pdo->lastInsertId();
        $this->ensureOrderSummary($ticketId, $orderId);

        return $ticketId;
    }

    /**
     * Insere uma única mensagem de sistema com o resumo da OS
     * (veículo, queixa, peças e serviços) na conversa do ticket.
     */
    public function ensureOrderSummary(int $ticketId, int $orderId): void
    {
        $existing = $this->pdo->prepare(
            "SELECT id, message FROM ticket_messages WHERE ticket_id = ? AND kind = 'system' ORDER BY id ASC LIMIT 1"
        );
        $existing->execute([$ticketId]);
        $message = $existing->fetch();

        $summary = $this->buildOrderSummary($orderId);
        if ($summary === '') {
            return;
        }

        if ($message) {
            // Mantém o resumo atualizado (ex.: orçamento criado depois da OS).
            if ((string) $message['message'] !== $summary) {
                $this->pdo->prepare('UPDATE ticket_messages SET message = ? WHERE id = ?')
                    ->execute([$summary, (int) $message['id']]);
            }
            return;
        }

        $author = $this->pdo->prepare('SELECT created_by FROM tickets WHERE id = ?');
        $author->execute([$ticketId]);
        $userId = (int) ($author->fetchColumn() ?: 1);

        $this->pdo->prepare(
            "INSERT INTO ticket_messages (ticket_id, user_id, message, kind) VALUES (?, ?, ?, 'system')"
        )->execute([$ticketId, $userId, $summary]);
    }

    /** Monta o texto do resumo automático da OS. */
    public function buildOrderSummary(int $orderId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT so.number, so.initial_complaint, so.internal_notes,
                    c.name AS client, v.plate, v.brand, v.model, v.year, v.color, v.current_km
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return '';
        }

        $lines = [];
        $lines[] = 'Resumo da OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT);

        $vehicle = trim(implode(' ', array_filter([
            $order['brand'] ?: null,
            $order['model'] ?: null,
            $order['year'] ? (string) $order['year'] : null,
        ])));
        $vehicleLine = array_filter([
            $vehicle ?: null,
            $order['plate'] ? 'Placa ' . $order['plate'] : null,
            $order['color'] ?: null,
            $order['current_km'] !== null ? number_format((int) $order['current_km'], 0, ',', '.') . ' km' : null,
        ]);
        if ($vehicleLine) {
            $lines[] = 'Veículo: ' . implode(' · ', $vehicleLine);
        }
        if (!empty($order['client'])) {
            $lines[] = 'Cliente: ' . $order['client'];
        }
        if (trim((string) $order['initial_complaint']) !== '') {
            $lines[] = 'Queixa do cliente: ' . trim((string) $order['initial_complaint']);
        }

        // Orçamento mais recente da OS: peças + serviços.
        $budget = $this->pdo->prepare('SELECT id FROM budgets WHERE service_order_id = ? ORDER BY id DESC LIMIT 1');
        $budget->execute([$orderId]);
        $budgetId = (int) $budget->fetchColumn();
        if ($budgetId > 0) {
            $items = $this->pdo->prepare(
                "SELECT type, description, quantity, unit_price, subtotal
                   FROM budget_items WHERE budget_id = ? ORDER BY type, id"
            );
            $items->execute([$budgetId]);
            $parts = [];
            $services = [];
            foreach ($items->fetchAll() as $item) {
                $label = '• ' . $item['description'] . ' ×' . rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')
                    . ' — R$ ' . number_format((float) $item['subtotal'], 2, ',', '.');
                if ($item['type'] === 'part') {
                    $parts[] = $label;
                } else {
                    $services[] = $label;
                }
            }
            if ($parts) {
                $lines[] = 'Peças necessárias:';
                $lines = array_merge($lines, array_slice($parts, 0, 8));
            }
            if ($services) {
                $lines[] = 'Serviços:';
                $lines = array_merge($lines, array_slice($services, 0, 6));
            }
        }

        // Pedidos de peças já registrados (fornecedor/status).
        $partOrders = $this->pdo->prepare(
            'SELECT po.supplier, po.status,
                    GROUP_CONCAT(CONCAT(i.description, " ×", i.quantity) SEPARATOR ", ") AS items
               FROM part_orders po
               LEFT JOIN part_order_items i ON i.part_order_id = po.id
              WHERE po.service_order_id = ?
              GROUP BY po.id, po.supplier, po.status
              ORDER BY po.id DESC
              LIMIT 3'
        );
        $partOrders->execute([$orderId]);
        $orders = $partOrders->fetchAll();
        if ($orders) {
            $lines[] = 'Pedidos de peças:';
            $statusLabels = [
                'requested' => 'Solicitado', 'validated' => 'Validado', 'ordered' => 'Pedido',
                'on_the_way' => 'A caminho', 'received' => 'Recebido', 'cancelled' => 'Cancelado',
            ];
            foreach ($orders as $po) {
                $lines[] = '• ' . ($po['supplier'] ?: 'Sem fornecedor') . ' · '
                    . ($statusLabels[$po['status']] ?? $po['status'])
                    . ($po['items'] ? ' · ' . $po['items'] : ' · sem itens');
            }
        }

        if (trim((string) $order['internal_notes']) !== '') {
            $lines[] = 'Observações internas: ' . trim((string) $order['internal_notes']);
        }

        return mb_substr(implode("\n", $lines), 0, 4000);
    }

    /** Dados da OS para o painel de contexto do ticket. */
    public function orderContext(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT so.*, c.id AS client_record_id, c.name AS client_name, c.phone AS client_phone, c.email AS client_email,
                    v.id AS vehicle_record_id, v.plate, v.brand, v.model, v.year, v.color, v.current_km
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $budget = $this->pdo->prepare('SELECT id, number, status, total FROM budgets WHERE service_order_id = ? ORDER BY id DESC LIMIT 1');
        $budget->execute([$orderId]);
        $latestBudget = $budget->fetch() ?: null;

        $items = [];
        if ($latestBudget) {
            $it = $this->pdo->prepare(
                "SELECT type, description, quantity, unit_price, subtotal
                   FROM budget_items WHERE budget_id = ? ORDER BY type, id"
            );
            $it->execute([(int) $latestBudget['id']]);
            $items = $it->fetchAll();
        }

        $partOrders = $this->pdo->prepare(
            'SELECT po.id, po.supplier, po.status, po.expected_date,
                    (SELECT GROUP_CONCAT(CONCAT(i.description, " ×", i.quantity) SEPARATOR ", ")
                       FROM part_order_items i WHERE i.part_order_id = po.id) AS items
               FROM part_orders po
              WHERE po.service_order_id = ?
              ORDER BY po.id DESC'
        );
        $partOrders->execute([$orderId]);

        $tasks = $this->pdo->prepare(
            'SELECT t.*, u.name AS assigned_name
               FROM service_order_tasks t
               LEFT JOIN users u ON u.id = t.assigned_to
              WHERE t.order_id = ?
              ORDER BY t.id'
        );
        $tasks->execute([$orderId]);

        return [
            'order' => $order,
            'budget' => $latestBudget,
            'items' => $items,
            'part_orders' => $partOrders->fetchAll(),
            'tasks' => $tasks->fetchAll(),
        ];
    }


    public function create(string $subject, ?int $orderId, ?int $assignedTo, int $createdBy): int
    {
        $this->pdo->prepare(
            'INSERT INTO tickets (subject, service_order_id, created_by, assigned_to, status)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            mb_substr(trim($subject), 0, 200),
            $orderId ?: null,
            $createdBy,
            $assignedTo ?: null,
            'open',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array> */
    public function messages(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.ticket_id, m.user_id, m.message, m.kind, m.created_at,
                    u.name AS user_name, u.role AS user_role
               FROM ticket_messages m
               JOIN users u ON u.id = m.user_id
              WHERE m.ticket_id = ?
              ORDER BY m.id ASC'
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function send(int $ticketId, int $userId, string $message, string $kind = 'message'): array
    {
        $message = mb_substr(trim($message), 0, 2000);
        $kind = $kind === 'system' ? 'system' : 'message';
        $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, user_id, message, kind) VALUES (?, ?, ?, ?)'
        )->execute([$ticketId, $userId, $message, $kind]);

        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.ticket_id, m.user_id, m.message, m.kind, m.created_at,
                    u.name AS user_name, u.role AS user_role
               FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public function markRead(int $ticketId, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO ticket_reads (ticket_id, user_id, last_read_at, last_read_message_id)
             VALUES (?, ?, NOW(), (SELECT COALESCE(MAX(id), 0) FROM ticket_messages WHERE ticket_id = ?))
             ON DUPLICATE KEY UPDATE
                last_read_at = NOW(),
                last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id))'
        )->execute([$ticketId, $userId, $ticketId]);
    }

    public function updateStatus(int $ticketId, string $status): void
    {
        if (!in_array($status, ['open', 'closed'], true)) {
            return;
        }
        $this->pdo->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$status, $ticketId]);
    }
}
