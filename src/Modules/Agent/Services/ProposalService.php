<?php
// =============================================
// PROPOSAL SERVICE — o coração de segurança.
//
// O agente propõe; o domínio valida e monta o
// preview; um humano aprova; só então o sistema
// grava de verdade, com auditoria e idempotência.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use Kernel\Auth\Auth;
use Modules\Mechanic\Services\TicketService;
use Modules\Mechanic\Support\OrderAccess;
use Modules\Mechanic\Support\ServiceOrderStatus;
use PDO;
use RuntimeException;
use Throwable;

class ProposalService
{
    private PDO $pdo;
    private Auth $auth;

    private const TTL_MINUTES = 45;

    /** Papel exigido para aprovar cada tipo de proposta. */
    private const APPROVER_ROLES = [
        'propor_abrir_os' => ['admin', 'secretary', 'lead_mechanic'],
        'propor_pedido_pecas' => null,
        'propor_status_os' => ['admin', 'secretary', 'lead_mechanic'],
    ];

    public function __construct(PDO $pdo, Auth $auth)
    {
        $this->pdo = $pdo;
        $this->auth = $auth;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    public function create(
        int $sessionId,
        int $userId,
        string $tool,
        array $payload,
        array $preview,
        ?string $intent = null,
        bool $mergeItems = false
    ): array {
        $requires = self::APPROVER_ROLES[$tool] ?? null;
        if ($requires !== null && !$this->auth->hasRole($requires)) {
            throw new RuntimeException('Seu perfil não pode propor esta ação.');
        }

        // Dedupe só dentro do ciclo de vida da proposta: uma proposta já
        // decidida não impede pedir a mesma ação de novo.
        $fingerprint = hash('sha256', $userId . '|' . $tool . '|' . json_encode($payload));
        $existing = $this->findPendingByFingerprint($userId, $fingerprint);
        if ($existing) {
            return $existing;
        }

        $this->pdo->beginTransaction();
        try {
            // Pedido de peças: a mesma intenção (OS) acumula itens.
            if ($mergeItems && $intent !== null) {
                $current = $this->findPendingByIntent($userId, $intent);
                if ($current) {
                    $mergedPayload = $this->mergeItemPayload((array) $current['payload'], $payload);
                    $mergedPreview = $preview;
                    $mergedPreview['items'] = $mergedPayload['items'] ?? [];

                    $this->pdo->prepare(
                        'UPDATE agent_proposals
                            SET payload = :p, preview = :pv, fingerprint = :fp, expires_at = :exp
                          WHERE id = :id AND status = \'pending\''
                    )->execute([
                        ':p' => json_encode($mergedPayload, JSON_UNESCAPED_UNICODE),
                        ':pv' => json_encode($mergedPreview, JSON_UNESCAPED_UNICODE),
                        ':fp' => hash('sha256', $userId . '|' . $tool . '|' . json_encode($mergedPayload)),
                        ':exp' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
                        ':id' => (int) $current['id'],
                    ]);

                    $this->supersedeOthers($userId, (int) $current['id'], $intent);

                    $this->pdo->commit();

                    return $this->find((int) $current['id']) ?? [];
                }
            }

            $idempotency = hash('sha256', $fingerprint . '|' . bin2hex(random_bytes(8)));

            $this->pdo->prepare(
                'INSERT INTO agent_proposals
                    (session_id, user_id, tool_name, intent, fingerprint, payload, preview, requires_role, expires_at, idempotency_key)
                 VALUES (:s, :u, :t, :in, :fp, :p, :pv, :rr, :exp, :ik)'
            )->execute([
                ':s' => $sessionId > 0 ? $sessionId : null,
                ':u' => $userId,
                ':t' => $tool,
                ':in' => $intent,
                ':fp' => $fingerprint,
                ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':pv' => json_encode($preview, JSON_UNESCAPED_UNICODE),
                ':rr' => $requires !== null ? implode(',', $requires) : null,
                ':exp' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
                ':ik' => $idempotency,
            ]);

            $id = (int) $this->pdo->lastInsertId();

            // Uma nova proposta com a mesma intenção substitui a pendente anterior.
            if ($intent !== null) {
                $this->supersedeOthers($userId, $id, $intent);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        record_audit($this->pdo, $userId, 'agent_proposals', $id, 'propose', [
            'tool' => $tool,
            'intent' => $intent,
        ]);

        return $this->find($id) ?? [];
    }

    private function supersedeOthers(int $userId, int $keepId, string $intent): void
    {
        $this->pdo->prepare(
            "UPDATE agent_proposals
                SET status = 'superseded', decided_at = NOW(), superseded_by = :keep1
              WHERE user_id = :u AND intent = :i AND status = 'pending' AND id <> :keep2"
        )->execute([
            ':u' => $userId,
            ':i' => $intent,
            ':keep1' => $keepId,
            ':keep2' => $keepId,
        ]);
    }

    /**
     * Mescla itens por chave (sku ou descrição normalizada), com o novo
     * substituindo o anterior. Assim repetir o pedido não duplica linha.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergeItemPayload(array $base, array $incoming): array
    {
        $merged = [];
        foreach ([(array) ($base['items'] ?? []), (array) ($incoming['items'] ?? [])] as $list) {
            foreach ($list as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $merged[$this->itemKey($item)] = $item;
            }
        }

        $result = $incoming;
        $result['items'] = array_values($merged);

        return $result;
    }

    /** @param array<string, mixed> $item */
    private function itemKey(array $item): string
    {
        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku !== '') {
            return 'sku:' . mb_strtolower($sku);
        }

        return 'd:' . VehicleSpecService::normalize((string) ($item['description'] ?? ''));
    }

    /** @return array<string, mixed>|null */
    private function findPendingByIntent(int $userId, string $intent): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM agent_proposals
              WHERE user_id = :u AND intent = :i AND status = 'pending'
              ORDER BY id DESC
              LIMIT 1"
        );
        $stmt->execute([':u' => $userId, ':i' => $intent]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM agent_proposals WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Índice (id => estado atual) para reidratar os cartões do histórico.
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM agent_proposals WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        $index = [];
        foreach ($stmt->fetchAll() as $row) {
            $proposal = $this->hydrate($row);
            $index[(int) $proposal['id']] = [
                'id' => (int) $proposal['id'],
                'session_id' => $proposal['session_id'] !== null ? (int) $proposal['session_id'] : null,
                'status' => (string) $proposal['status'],
                'intent' => (string) ($proposal['intent'] ?? ''),
                'result' => $proposal['result'],
                'error' => $proposal['error'],
                'expires_at' => $proposal['expires_at'],
            ];
        }

        return $index;
    }

    /**
     * Pendentes visíveis ao usuário (as próprias ou todas, se admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pending(int $userId, bool $isAdmin, int $limit = 20): array
    {
        $this->expireOld();

        $limit = max(1, min(50, $limit));
        $sql = "SELECT * FROM agent_proposals
                 WHERE status = 'pending'
                   AND (user_id = :u" . ($isAdmin ? ' OR 1 = 1' : '') . ')
                 ORDER BY id DESC
                 LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $userId]);

        return array_map(fn (array $row): array => $this->hydrate($row), $stmt->fetchAll());
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(int $id, int $userId, bool $isAdmin): array
    {
        $proposal = $this->find($id);
        if (!$proposal) {
            throw new RuntimeException('Proposta não encontrada.');
        }
        if ($proposal['status'] !== 'pending') {
            throw new RuntimeException('Esta proposta já foi decidida.');
        }
        if ((int) $proposal['user_id'] !== $userId && !$isAdmin) {
            throw new RuntimeException('Você não pode decidir esta proposta.');
        }

        $this->pdo->prepare(
            "UPDATE agent_proposals
                SET status = 'rejected', approved_by = :by, decided_at = NOW()
              WHERE id = :id AND status = 'pending'"
        )->execute([':by' => $userId, ':id' => $id]);

        record_audit($this->pdo, $userId, 'agent_proposals', $id, 'reject', [
            'tool' => $proposal['tool_name'],
        ]);

        return ['ok' => true, 'message' => 'Proposta rejeitada.'];
    }

    /**
     * Aprova e aplica a proposta no domínio.
     *
     * @return array{ok: bool, message: string, url?: string, data?: array<string, mixed>}
     */
    public function apply(int $id, int $userId, bool $isAdmin): array
    {
        $proposal = $this->find($id);
        if (!$proposal) {
            throw new RuntimeException('Proposta não encontrada.');
        }
        if ($proposal['status'] !== 'pending') {
            throw new RuntimeException('Esta proposta já foi decidida.');
        }
        if (!empty($proposal['expires_at']) && strtotime((string) $proposal['expires_at']) < time()) {
            $this->markExpired($id);
            throw new RuntimeException('A proposta expirou. Peça novamente ao assistente.');
        }
        if ((int) $proposal['user_id'] !== $userId && !$isAdmin) {
            throw new RuntimeException('Somente quem solicitou (ou um administrador) pode aprovar.');
        }

        $required = trim((string) ($proposal['requires_role'] ?? ''));
        if ($required !== '') {
            $roles = array_filter(array_map('trim', explode(',', $required)));
            if (!$this->auth->hasRole($roles)) {
                throw new RuntimeException('Seu perfil não pode aprovar esta ação.');
            }
        }

        $payload = (array) $proposal['payload'];

        $this->pdo->beginTransaction();
        try {
            $result = match ((string) $proposal['tool_name']) {
                'propor_abrir_os' => $this->applyServiceOrder($payload, $userId),
                'propor_pedido_pecas' => $this->applyPartOrder($payload, $userId),
                'propor_status_os' => $this->applyStatusChange($payload, $userId),
                default => throw new RuntimeException('Tipo de proposta desconhecido.'),
            };

            $this->pdo->prepare(
                "UPDATE agent_proposals
                    SET status = 'applied', approved_by = :by, decided_at = NOW(),
                        applied_at = NOW(), result = :res, error = NULL
                  WHERE id = :id AND status = 'pending'"
            )->execute([
                ':by' => $userId,
                ':res' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ':id' => $id,
            ]);

            $this->pdo->commit();

            record_audit($this->pdo, $userId, 'agent_proposals', $id, 'approve', [
                'tool' => $proposal['tool_name'],
                'result' => $result,
            ]);

            return [
                'ok' => true,
                'message' => $result['message'],
                'url' => $result['url'] ?? null,
                'data' => $result,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            $this->pdo->prepare(
                "UPDATE agent_proposals SET status = 'failed', error = :err WHERE id = :id AND status = 'pending'"
            )->execute([
                ':err' => mb_substr($e->getMessage(), 0, 255),
                ':id' => $id,
            ]);

            throw $e;
        }
    }

    // =========================================================
    // APLICAÇÃO POR TIPO
    // =========================================================

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyServiceOrder(array $payload, int $userId): array
    {
        $clientId = (int) ($payload['client_id'] ?? 0);
        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);

        $client = $this->pdo->prepare('SELECT id, name FROM clients WHERE id = :id AND is_active = 1');
        $client->execute([':id' => $clientId]);
        $clientRow = $client->fetch();
        if (!$clientRow) {
            throw new RuntimeException('Cliente não encontrado ou inativo.');
        }

        $vehicle = $this->pdo->prepare(
            'SELECT id, plate, model FROM vehicles WHERE id = :id AND client_id = :c AND is_active = 1'
        );
        $vehicle->execute([':id' => $vehicleId, ':c' => $clientId]);
        $vehicleRow = $vehicle->fetch();
        if (!$vehicleRow) {
            throw new RuntimeException('O veículo não pertence a este cliente ou está inativo.');
        }

        $priority = (string) ($payload['priority'] ?? 'normal');
        if (!isset(ServiceOrderStatus::PRIORITIES[$priority])) {
            $priority = 'normal';
        }

        $number = (int) $this->pdo->query('SELECT COALESCE(MAX(number), 0) + 1 FROM service_orders')
            ->fetchColumn();

        $this->pdo->prepare(
            'INSERT INTO service_orders
                (number, client_id, vehicle_id, status, priority, initial_complaint, internal_notes,
                 diagnosis, entry_date, lead_mechanic_id, created_by)
             VALUES (:num, :cl, :ve, :st, :pr, :ic, :inotes, NULL, :ed, :lead, :cb)'
        )->execute([
            ':num' => $number,
            ':cl' => $clientId,
            ':ve' => $vehicleId,
            ':st' => 'received',
            ':pr' => $priority,
            ':ic' => mb_substr(trim((string) ($payload['initial_complaint'] ?? '')), 0, 5000),
            ':inotes' => mb_substr(trim((string) ($payload['internal_notes'] ?? '')), 0, 5000),
            ':ed' => date('Y-m-d'),
            ':lead' => $this->auth->hasRole(['lead_mechanic', 'mechanic']) ? $userId : null,
            ':cb' => $userId,
        ]);

        $orderId = (int) $this->pdo->lastInsertId();

        $taskStmt = $this->pdo->prepare(
            'INSERT INTO service_order_tasks (order_id, description, status, created_by)
             VALUES (:o, :d, :s, :u)'
        );
        foreach ((array) ($payload['tasks'] ?? []) as $task) {
            $description = is_array($task) ? trim((string) ($task['description'] ?? '')) : trim((string) $task);
            if ($description === '') {
                continue;
            }
            $taskStmt->execute([
                ':o' => $orderId,
                ':d' => mb_substr($description, 0, 255),
                ':s' => 'pending',
                ':u' => $userId,
            ]);
        }

        $parts = array_values(array_filter(
            (array) ($payload['parts'] ?? []),
            static fn ($part): bool => trim((string) (is_array($part) ? ($part['description'] ?? '') : $part)) !== ''
        ));

        if ($parts) {
            $this->pdo->prepare(
                "INSERT INTO part_orders (service_order_id, status, created_by) VALUES (:o, 'requested', :u)"
            )->execute([':o' => $orderId, ':u' => $userId]);
            $partOrderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO part_order_items (part_order_id, description, quantity)
                 VALUES (:p, :d, :q)'
            );
            foreach ($parts as $part) {
                $description = trim((string) (is_array($part) ? ($part['description'] ?? '') : $part));
                $quantity = is_array($part) ? max(1, (int) ($part['quantity'] ?? 1)) : 1;
                $itemStmt->execute([
                    ':p' => $partOrderId,
                    ':d' => mb_substr($description, 0, 200),
                    ':q' => $quantity,
                ]);
            }
        }

        $this->pdo->prepare(
            'INSERT INTO service_order_events (order_id, type, description, created_by)
             VALUES (:o, :t, :d, :u)'
        )->execute([
            ':o' => $orderId,
            ':t' => 'created',
            ':d' => 'OS criada pelo assistente (proposta aprovada).',
            ':u' => $userId,
        ]);

        (new TicketService($this->pdo))->ensureForOrder($orderId);

        $label = 'OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);

        return [
            'message' => $label . ' criada com sucesso.',
            'url' => '/service-orders/' . $orderId,
            'order_id' => $orderId,
            'number' => $number,
            'parts_created' => count($parts),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyPartOrder(array $payload, int $userId): array
    {
        $orderId = (int) ($payload['service_order_id'] ?? 0);
        if ($orderId <= 0) {
            throw new RuntimeException('OS inválida.');
        }

        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            throw new RuntimeException('Você não tem acesso a esta OS.');
        }

        $items = array_values(array_filter(
            (array) ($payload['items'] ?? []),
            static fn ($item): bool => trim((string) (is_array($item) ? ($item['description'] ?? '') : $item)) !== ''
        ));
        if (!$items) {
            throw new RuntimeException('O pedido não tem itens.');
        }

        $this->pdo->prepare(
            "INSERT INTO part_orders (service_order_id, status, notes, created_by)
             VALUES (:o, 'requested', :n, :u)"
        )->execute([
            ':o' => $orderId,
            ':n' => mb_substr(trim((string) ($payload['notes'] ?? '')), 0, 2000),
            ':u' => $userId,
        ]);
        $partOrderId = (int) $this->pdo->lastInsertId();

        $itemStmt = $this->pdo->prepare(
            'INSERT INTO part_order_items
                (part_order_id, description, quantity, sku, unit_price, supplier, product_url)
             VALUES (:p, :d, :q, :sku, :price, :sup, :url)'
        );
        foreach ($items as $item) {
            $description = trim((string) (is_array($item) ? ($item['description'] ?? '') : $item));
            $quantity = is_array($item) ? max(1, (int) ($item['quantity'] ?? 1)) : 1;
            $itemStmt->execute([
                ':p' => $partOrderId,
                ':d' => mb_substr($description, 0, 200),
                ':q' => $quantity,
                ':sku' => is_array($item) && trim((string) ($item['sku'] ?? '')) !== ''
                    ? mb_substr((string) $item['sku'], 0, 60)
                    : null,
                ':price' => is_array($item) && isset($item['unit_price']) && $item['unit_price'] !== null
                    ? (float) $item['unit_price']
                    : null,
                ':sup' => is_array($item) && trim((string) ($item['supplier'] ?? '')) !== ''
                    ? mb_substr((string) $item['supplier'], 0, 120)
                    : null,
                ':url' => is_array($item) && trim((string) ($item['product_url'] ?? '')) !== ''
                    ? mb_substr((string) $item['product_url'], 0, 400)
                    : null,
            ]);
        }

        (new TicketService($this->pdo))->ensureForOrder($orderId);

        return [
            'message' => 'Pedido de peças #' . $partOrderId . ' criado.',
            'url' => '/part-orders/' . $partOrderId,
            'part_order_id' => $partOrderId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyStatusChange(array $payload, int $userId): array
    {
        $orderId = (int) ($payload['service_order_id'] ?? 0);
        $from = (string) ($payload['from'] ?? '');
        $to = (string) ($payload['to'] ?? '');

        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            throw new RuntimeException('Você não tem acesso a esta OS.');
        }
        if (!isset(ServiceOrderStatus::STATUSES[$to])) {
            throw new RuntimeException('Status inválido.');
        }

        $stmt = $this->pdo->prepare('SELECT id, number, status FROM service_orders WHERE id = :id');
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('OS não encontrada.');
        }
        if ((string) $order['status'] !== $from) {
            throw new RuntimeException('O status da OS mudou desde a proposta. Peça novamente.');
        }
        if (!in_array($to, ServiceOrderStatus::next($from), true)) {
            throw new RuntimeException(
                'Transição não permitida: ' . ServiceOrderStatus::label($from) . ' → ' . ServiceOrderStatus::label($to)
            );
        }

        $this->pdo->prepare('UPDATE service_orders SET status = :st WHERE id = :id')
            ->execute([':st' => $to, ':id' => $orderId]);

        $this->pdo->prepare(
            'INSERT INTO service_order_events (order_id, type, description, created_by)
             VALUES (:o, :t, :d, :u)'
        )->execute([
            ':o' => $orderId,
            ':t' => 'status',
            ':d' => 'Status alterado de ' . ServiceOrderStatus::label($from) . ' para ' . ServiceOrderStatus::label($to) . ' (assistente).',
            ':u' => $userId,
        ]);

        return [
            'message' => 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)
                . ' agora está em "' . ServiceOrderStatus::label($to) . '".',
            'url' => '/service-orders/' . $orderId,
            'status' => $to,
        ];
    }

    // =========================================================
    // INTERNOS
    // =========================================================

    /**
     * Formato do cartão usado pela interface.
     *
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    public static function card(array $proposal): array
    {
        $preview = (array) ($proposal['preview'] ?? []);

        return [
            'id' => (int) $proposal['id'],
            'tool_name' => (string) $proposal['tool_name'],
            'status' => (string) $proposal['status'],
            'intent' => (string) ($proposal['intent'] ?? ''),
            'expires_at' => (string) ($proposal['expires_at'] ?? ''),
            'title' => (string) ($preview['title'] ?? 'Proposta'),
            'subtitle' => (string) ($preview['subtitle'] ?? ''),
            'risk' => (string) ($preview['risk'] ?? 'low'),
            'approve_label' => (string) ($preview['approve_label'] ?? 'Aprovar'),
            'fields' => (array) ($preview['fields'] ?? []),
            'items' => (array) ($preview['items'] ?? []),
            'warnings' => (array) ($preview['warnings'] ?? []),
            'tasks' => (array) ($preview['tasks'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['payload'] = $row['payload'] ? json_decode((string) $row['payload'], true) : [];
        $row['preview'] = $row['preview'] ? json_decode((string) $row['preview'], true) : [];
        $row['result'] = $row['result'] ? json_decode((string) $row['result'], true) : null;

        return $row;
    }

    /** @return array<string, mixed>|null */
    private function findPendingByFingerprint(int $userId, string $fingerprint): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM agent_proposals
              WHERE user_id = :u AND fingerprint = :f AND status = 'pending'
              ORDER BY id DESC
              LIMIT 1"
        );
        $stmt->execute([':u' => $userId, ':f' => $fingerprint]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    private function expireOld(): void
    {
        $this->pdo->prepare(
            "UPDATE agent_proposals
                SET status = 'expired'
              WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()"
        )->execute();
    }

    private function markExpired(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE agent_proposals SET status = 'expired' WHERE id = :id AND status = 'pending'"
        )->execute([':id' => $id]);
    }
}
