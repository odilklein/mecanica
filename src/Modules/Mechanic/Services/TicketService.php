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
        string $search = ''
    ): array {
        $where = ['(:is_admin = 1 OR t.created_by = :uid_created OR t.assigned_to = :uid_assigned)'];
        $params = [
            ':is_admin' => $isAdmin ? 1 : 0,
            ':uid_created' => $userId,
            ':uid_assigned' => $userId,
            ':uid_read' => $userId,
            ':uid_join' => $userId,
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
        $sql = 'SELECT t.*, so.number AS order_number, c.name AS client_name,
                       creator.name AS creator_name, assigned.name AS assigned_name,
                       (SELECT m.message FROM ticket_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                       (SELECT m.created_at FROM ticket_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
                       (SELECT COUNT(*) FROM ticket_messages m
                         WHERE m.ticket_id = t.id AND m.user_id <> :uid_read
                           AND (r.last_read_at IS NULL OR m.created_at > r.last_read_at)) AS unread
                  FROM tickets t
                  LEFT JOIN service_orders so ON so.id = t.service_order_id
                  LEFT JOIN clients c ON c.id = so.client_id
                  LEFT JOIN users creator ON creator.id = t.created_by
                  LEFT JOIN users assigned ON assigned.id = t.assigned_to
                  LEFT JOIN ticket_reads r ON r.ticket_id = t.id AND r.user_id = :uid_join
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY COALESCE(last_message_at, t.created_at) DESC, t.id DESC
                 LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
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
        return $isAdmin
            || (int) $ticket['created_by'] === $userId
            || (int) ($ticket['assigned_to'] ?? 0) === $userId;
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

    /** Garante o ticket da OS e retorna o id. */
    public function ensureForOrder(int $orderId): int
    {
        $existing = $this->findByOrder($orderId);
        if ($existing) {
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

        $subject = 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)
            . ' - ' . ($order['client_name'] ?: 'Cliente');

        $this->pdo->prepare(
            'INSERT INTO tickets (subject, service_order_id, created_by, assigned_to, status)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($subject, 0, 200),
            $orderId,
            (int) ($order['created_by'] ?: 1),
            $order['lead_mechanic_id'] ?: null,
            'open',
        ]);

        return (int) $this->pdo->lastInsertId();
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
            'SELECT m.id, m.ticket_id, m.user_id, m.message, m.created_at,
                    u.name AS user_name, u.role AS user_role
               FROM ticket_messages m
               JOIN users u ON u.id = m.user_id
              WHERE m.ticket_id = ?
              ORDER BY m.id ASC'
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function send(int $ticketId, int $userId, string $message): array
    {
        $message = mb_substr(trim($message), 0, 2000);
        $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, user_id, message) VALUES (?, ?, ?)'
        )->execute([$ticketId, $userId, $message]);

        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.ticket_id, m.user_id, m.message, m.created_at,
                    u.name AS user_name, u.role AS user_role
               FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public function markRead(int $ticketId, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO ticket_reads (ticket_id, user_id, last_read_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_read_at = NOW()'
        )->execute([$ticketId, $userId]);
    }

    public function updateStatus(int $ticketId, string $status): void
    {
        if (!in_array($status, ['open', 'closed'], true)) {
            return;
        }
        $this->pdo->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$status, $ticketId]);
    }
}
