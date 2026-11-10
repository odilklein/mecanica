<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Support\Notify;
use Modules\Mechanic\Resources\PartOrderResource;
use Modules\Mechanic\Services\TicketService;
use Modules\Mechanic\Support\OrderAccess;
use Modules\Mechanic\Support\PartOrderStatus;
use Psr\Http\Message\ServerRequestInterface;

class PartOrderController extends CrudController
{
    protected function resource(): Resource
    {
        return new PartOrderResource();
    }

    /** DataTables: mecânicos veem apenas pedidos das OS em que participam. */
    public function datatable(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }
        if ($resource->roles() && !$this->auth->hasRole($resource->roles())) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $scopeUserId = OrderAccess::isMechanicOnly($this->auth) ? (int) $this->auth->id() : null;
        $data = [];
        foreach ($resource->paginate($this->pdo, 1, 10000, '', $scopeUserId)['items'] as $row) {
            $row = $resource->datatableRow($row);
            unset($row['password'], $row['remember_token']);
            $data[] = $row;
        }

        return json_response(['data' => $data]);
    }

    /** Bloqueia mecânicos em pedidos de OS que não participam. */
    private function guardPartOrderAccess(array $partOrder): ?\Psr\Http\Message\ResponseInterface
    {
        if (!OrderAccess::isMechanicOnly($this->auth)) {
            return null;
        }
        $orderId = (int) ($partOrder['service_order_id'] ?? 0);
        if ($orderId > 0 && OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return null;
        }
        $this->session->flash('error', 'Você não tem acesso a este pedido de peças.');
        return redirect('/part-orders');
    }

    public function create(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        $query = $request->getQueryParams();
        $canManageStatus = $this->auth->hasRole(['admin', 'secretary', 'lead_mechanic']);

        return $this->render('mechanic::part_orders/form', [
            'title' => 'Novo pedido de peça',
            'resource' => $resource,
            'record' => null,
            'old' => ['service_order_id' => (int) ($query['os_id'] ?? 0)],
            'errors' => [],
            'selectOptions' => $resource->selectOptions(
                $this->pdo,
                [],
                OrderAccess::isMechanicOnly($this->auth) ? (int) $this->auth->id() : null,
                $canManageStatus
            ),
            'items' => [],
            'canManageStatus' => $canManageStatus,
            'canManageSuppliers' => $canManageStatus,
            'pageScripts' => ['/js/part-order-items.js'],
        ]);
    }

    public function store(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $body = $request->getParsedBody();
        $orderId = (int) ($body['service_order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->session->flash('error', 'Selecione a OS.');
            return redirect($resource->path() . '/create');
        }

        if (OrderAccess::isMechanicOnly($this->auth)
            && !OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            $this->session->flash('error', 'Você não tem acesso a esta OS.');
            return redirect($resource->path() . '/create');
        }

        $isMechanicOnly = OrderAccess::isMechanicOnly($this->auth);

        $status = $isMechanicOnly ? 'requested' : (string) ($body['status'] ?? 'requested');
        if (!isset(PartOrderStatus::STATUSES[$status])) {
            $status = 'requested';
        }
        $supplier = mb_substr(clean($body['supplier'] ?? ''), 0, 160);
        if ($isMechanicOnly && $supplier !== '' && !$this->supplierIsActive($supplier)) {
            $this->session->flash('error', 'Selecione um fornecedor cadastrado.');
            return redirect($resource->path() . '/create');
        }
        if (!$isMechanicOnly) {
            $this->registerSupplier($supplier);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO part_orders (service_order_id, supplier, status, expected_date, notes, created_by, received_by, received_at)
                 VALUES (:o, :s, :st, :ed, :n, :cb, :rb, :ra)'
            );
            $received = $status === 'received';
            $stmt->execute([
                ':o' => $orderId,
                ':s' => $supplier,
                ':st' => $status,
                ':ed' => clean($body['expected_date'] ?? '') ?: null,
                ':n' => clean($body['notes'] ?? ''),
                ':cb' => $this->auth->id(),
                ':rb' => $received ? $this->auth->id() : null,
                ':ra' => $received ? date('Y-m-d H:i:s') : null,
            ]);
            $partOrderId = (int) $this->pdo->lastInsertId();
            $this->saveItems($partOrderId, $this->parseItems($body['items_json'] ?? '[]'));
            $this->pdo->commit();

            $this->refreshTicket($orderId);
            $this->notifyPartOrder(
                $orderId,
                'Pedido de peça criado' . ($supplier !== '' ? ' — ' . $supplier : '')
                    . ' (' . PartOrderStatus::label($status) . ')',
                $partOrderId
            );

            $this->session->flash('success', 'Pedido de peça criado.');
            return redirect($resource->path());
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('PartOrder store: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível criar o pedido.');
            return redirect($resource->path() . '/create');
        }
    }

    public function edit(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        $record = $this->findOrder((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Pedido não encontrado.');
            return redirect($resource->path());
        }
        if ($response = $this->guardPartOrderAccess($record)) {
            return $response;
        }

        $canManageStatus = $this->auth->hasRole(['admin', 'secretary', 'lead_mechanic']);

        return $this->render('mechanic::part_orders/form', [
            'title' => 'Editar pedido de peça',
            'resource' => $resource,
            'record' => $record,
            'old' => $record,
            'errors' => [],
            'selectOptions' => $resource->selectOptions(
                $this->pdo,
                $record,
                OrderAccess::isMechanicOnly($this->auth) ? (int) $this->auth->id() : null,
                $canManageStatus
            ),
            'items' => $this->itemsData((int) $id),
            'canManageStatus' => $canManageStatus,
            'canManageSuppliers' => $canManageStatus,
            'pageScripts' => ['/js/part-order-items.js'],
        ]);
    }

    public function update(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $oldRecord = $this->findOrder((int) $id);
        if (!$oldRecord) {
            $this->session->flash('error', 'Pedido não encontrado.');
            return redirect($resource->path());
        }
        if ($response = $this->guardPartOrderAccess($oldRecord)) {
            return $response;
        }
        $oldStatus = (string) ($oldRecord['status'] ?? 'requested');
        $oldOrderId = (int) ($oldRecord['service_order_id'] ?? 0);

        $body = $request->getParsedBody();
        $newOrderId = (int) ($body['service_order_id'] ?? 0);
        if (OrderAccess::isMechanicOnly($this->auth)
            && $newOrderId > 0
            && !OrderAccess::canAccessOrderId($this->pdo, $this->auth, $newOrderId)) {
            $this->session->flash('error', 'Você não tem acesso a esta OS.');
            return redirect($resource->path() . '/' . (int) $id . '/edit');
        }
        $status = (string) ($body['status'] ?? 'requested');
        if (!isset(PartOrderStatus::STATUSES[$status])) {
            $status = 'requested';
        }
        $supplier = mb_substr(clean($body['supplier'] ?? ''), 0, 160);
        $this->registerSupplier($supplier);
        // O campo "Observações" saiu do formulário: preserva o valor atual
        // quando ele não é enviado.
        $notes = array_key_exists('notes', $body)
            ? clean($body['notes'] ?? '')
            : (string) ($oldRecord['notes'] ?? '');
        $received = $status === 'received';

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE part_orders SET service_order_id = :o, supplier = :s, status = :st, expected_date = :ed, notes = :n,
                        received_by = :rb, received_at = :ra WHERE id = :id'
            )->execute([
                ':o' => $newOrderId,
                ':s' => $supplier,
                ':st' => $status,
                ':ed' => clean($body['expected_date'] ?? '') ?: null,
                ':n' => $notes,
                ':rb' => $received ? $this->auth->id() : null,
                ':ra' => $received ? date('Y-m-d H:i:s') : null,
                ':id' => (int) $id,
            ]);
            $this->pdo->prepare('DELETE FROM part_order_items WHERE part_order_id = :id')->execute([':id' => (int) $id]);
            $this->saveItems((int) $id, $this->parseItems($body['items_json'] ?? '[]'));
            $this->pdo->commit();

            foreach (array_unique(array_filter([$oldOrderId, $newOrderId])) as $osId) {
                $this->refreshTicket((int) $osId);
            }

            if ($oldStatus !== $status) {
                $this->notifyPartOrder(
                    $newOrderId,
                    'Pedido de peça: ' . PartOrderStatus::label($oldStatus) . ' → ' . PartOrderStatus::label($status),
                    (int) $id
                );
            }

            $this->session->flash('success', 'Pedido atualizado.');
            return redirect($resource->path());
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('PartOrder update: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível atualizar o pedido.');
            return redirect($resource->path() . '/' . (int) $id . '/edit');
        }
    }

    public function destroy(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }

        $record = $this->findOrder((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Pedido não encontrado.');
            return redirect('/part-orders');
        }
        if ($response = $this->guardPartOrderAccess($record)) {
            return $response;
        }

        return parent::destroy($request, $id);
    }

    /** Cadastra automaticamente um fornecedor digitado (nome novo). */
    private function registerSupplier(string $name): void
    {
        if ($name === '') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO suppliers (name) VALUES (:name)');
            $stmt->execute([':name' => $name]);
        } catch (\Throwable $e) {
            // Não impede o pedido caso a tabela ainda não exista.
            error_log('PartOrder supplier: ' . $e->getMessage());
        }
    }

    /** Confere se o fornecedor existe e está ativo (mecânico só usa cadastrados). */
    private function supplierIsActive(string $name): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM suppliers WHERE name = :name AND is_active = 1');
            $stmt->execute([':name' => $name]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Converte o JSON de itens em linhas normalizadas. */
    private function parseItems(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $productUrl = trim((string) ($item['product_url'] ?? ''));
            if ($productUrl !== '') {
                $scheme = strtolower((string) parse_url($productUrl, PHP_URL_SCHEME));
                if (!filter_var($productUrl, FILTER_VALIDATE_URL)
                    || !in_array($scheme, ['http', 'https'], true)) {
                    $productUrl = '';
                }
            }
            $items[] = [
                'description' => mb_substr($description, 0, 200),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'sku' => trim((string) ($item['sku'] ?? '')) ?: null,
                'unit_price' => isset($item['unit_price']) && $item['unit_price'] !== ''
                    ? (float) $item['unit_price']
                    : null,
                'supplier' => trim((string) ($item['supplier'] ?? '')) ?: null,
                'product_url' => $productUrl !== '' ? mb_substr($productUrl, 0, 500) : null,
                'catalog_notes' => trim((string) ($item['catalog_notes'] ?? '')) ?: null,
            ];
        }
        return $items;
    }

    private function saveItems(int $partOrderId, array $items): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO part_order_items
                (part_order_id, description, quantity, sku, unit_price, supplier, product_url, catalog_notes)
             VALUES (:p, :d, :q, :sku, :price, :supplier, :url, :notes)'
        );
        foreach ($items as $item) {
            $stmt->execute([
                ':p' => $partOrderId,
                ':d' => $item['description'],
                ':q' => $item['quantity'],
                ':sku' => $item['sku'] ?? null,
                ':price' => $item['unit_price'] ?? null,
                ':supplier' => $item['supplier'] ?? null,
                ':url' => $item['product_url'] ?? null,
                ':notes' => $item['catalog_notes'] ?? null,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsData(int $partOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT description, quantity, sku, unit_price, supplier, product_url, catalog_notes
               FROM part_order_items WHERE part_order_id = :id ORDER BY id'
        );
        $stmt->execute([':id' => $partOrderId]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $description = (string) $row['description'];

            // Busca os dados atuais do catálogo para o card ficar completo
            // (imagem, marca, código e categoria), como no wizard da OS.
            $catalog = null;
            if ($sku !== '') {
                $lookup = $this->pdo->prepare(
                    'SELECT name, brand, part_code, category, image, price
                       FROM parts WHERE cdp_sku = :sku ORDER BY id DESC LIMIT 1'
                );
                $lookup->execute([':sku' => $sku]);
                $catalog = $lookup->fetch() ?: null;
            }
            if (!$catalog) {
                $lookup = $this->pdo->prepare(
                    'SELECT name, brand, part_code, category, image, price
                       FROM parts WHERE name = :name ORDER BY id DESC LIMIT 1'
                );
                $lookup->execute([':name' => $description]);
                $catalog = $lookup->fetch() ?: null;
            }

            $items[] = [
                'description' => $description,
                'quantity' => (int) $row['quantity'],
                'sku' => $row['sku'],
                'unit_price' => $row['unit_price'] !== null
                    ? (float) $row['unit_price']
                    : ($catalog && $catalog['price'] !== null ? (float) $catalog['price'] : null),
                'supplier' => $row['supplier'],
                'product_url' => $row['product_url'],
                'catalog_notes' => $row['catalog_notes'],
                'brand' => (string) ($catalog['brand'] ?? ''),
                'part_code' => (string) ($catalog['part_code'] ?? ''),
                'category' => (string) ($catalog['category'] ?? ''),
                'image' => (string) ($catalog['image'] ?? ''),
            ];
        }

        return $items;
    }

    /** Atualiza o resumo automático do ticket vinculado à OS. */
    private function refreshTicket(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        try {
            (new TicketService($this->pdo))->ensureForOrder($orderId);
        } catch (\Throwable $e) {
            error_log('PartOrder ticket summary: ' . $e->getMessage());
        }
    }

    /** Notifica os participantes da OS sobre o pedido de peças. */
    private function notifyPartOrder(int $orderId, string $action, int $partOrderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT number, created_by, lead_mechanic_id FROM service_orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return;
        }

        $ids = [
            (int) ($order['created_by'] ?? 0),
            (int) ($order['lead_mechanic_id'] ?? 0),
        ];
        $resp = $this->pdo->prepare('SELECT user_id FROM service_order_responsibles WHERE order_id = ?');
        $resp->execute([$orderId]);
        foreach ($resp->fetchAll(\PDO::FETCH_COLUMN) as $userId) {
            $ids[] = (int) $userId;
        }

        // Quem criou o pedido também acompanha as mudanças de status.
        $creator = $this->pdo->prepare('SELECT created_by FROM part_orders WHERE id = ?');
        $creator->execute([$partOrderId]);
        $ids[] = (int) ($creator->fetchColumn() ?: 0);

        $actor = (int) $this->auth->id();
        $recipients = array_values(array_unique(array_filter(
            $ids,
            static fn (int $uid): bool => $uid > 0 && $uid !== $actor
        )));
        if (!$recipients) {
            return;
        }

        Notify::to(
            $this->pdo,
            $recipients,
            'part',
            'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . ' — ' . $action . '.',
            '/part-orders/' . $partOrderId . '/edit',
            $actor
        );
    }

    private function findOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM part_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
