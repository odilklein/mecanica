<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Mechanic\Resources\PartOrderResource;
use Psr\Http\Message\ServerRequestInterface;

class PartOrderController extends CrudController
{
    protected function resource(): Resource
    {
        return new PartOrderResource();
    }

    public function create(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        $query = $request->getQueryParams();

        return $this->render('mechanic::part_orders/form', [
            'title' => 'Novo pedido de peça',
            'resource' => $resource,
            'record' => null,
            'old' => ['service_order_id' => (int) ($query['os_id'] ?? 0)],
            'errors' => [],
            'selectOptions' => $resource->selectOptions($this->pdo, []),
            'items' => [],
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

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO part_orders (service_order_id, supplier, status, expected_date, notes, created_by, received_by, received_at)
                 VALUES (:o, :s, :st, :ed, :n, :cb, :rb, :ra)'
            );
            $received = ($body['status'] ?? '') === 'received';
            $stmt->execute([
                ':o' => $orderId,
                ':s' => clean($body['supplier'] ?? ''),
                ':st' => clean($body['status'] ?? 'requested'),
                ':ed' => clean($body['expected_date'] ?? '') ?: null,
                ':n' => clean($body['notes'] ?? ''),
                ':cb' => $this->auth->id(),
                ':rb' => $received ? $this->auth->id() : null,
                ':ra' => $received ? date('Y-m-d H:i:s') : null,
            ]);
            $partOrderId = (int) $this->pdo->lastInsertId();
            $this->saveItems($partOrderId, $this->parseItems($body['items_json'] ?? '[]'));
            $this->pdo->commit();

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
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        $record = $this->findOrder((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Pedido não encontrado.');
            return redirect($resource->path());
        }

        return $this->render('mechanic::part_orders/form', [
            'title' => 'Editar pedido de peça',
            'resource' => $resource,
            'record' => $record,
            'old' => $record,
            'errors' => [],
            'selectOptions' => $resource->selectOptions($this->pdo, $record),
            'items' => $this->itemsData((int) $id),
            'pageScripts' => ['/js/part-order-items.js'],
        ]);
    }

    public function update(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $body = $request->getParsedBody();
        $received = ($body['status'] ?? '') === 'received';

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE part_orders SET service_order_id = :o, supplier = :s, status = :st, expected_date = :ed, notes = :n,
                        received_by = :rb, received_at = :ra WHERE id = :id'
            )->execute([
                ':o' => (int) ($body['service_order_id'] ?? 0),
                ':s' => clean($body['supplier'] ?? ''),
                ':st' => clean($body['status'] ?? 'requested'),
                ':ed' => clean($body['expected_date'] ?? '') ?: null,
                ':n' => clean($body['notes'] ?? ''),
                ':rb' => $received ? $this->auth->id() : null,
                ':ra' => $received ? date('Y-m-d H:i:s') : null,
                ':id' => (int) $id,
            ]);
            $this->pdo->prepare('DELETE FROM part_order_items WHERE part_order_id = :id')->execute([':id' => (int) $id]);
            $this->saveItems((int) $id, $this->parseItems($body['items_json'] ?? '[]'));
            $this->pdo->commit();

            $this->session->flash('success', 'Pedido atualizado.');
            return redirect($resource->path());
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('PartOrder update: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível atualizar o pedido.');
            return redirect($resource->path() . '/' . (int) $id . '/edit');
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
            $items[] = [
                'description' => mb_substr($description, 0, 200),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            ];
        }
        return $items;
    }

    private function saveItems(int $partOrderId, array $items): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO part_order_items (part_order_id, description, quantity) VALUES (:p, :d, :q)');
        foreach ($items as $item) {
            $stmt->execute([
                ':p' => $partOrderId,
                ':d' => $item['description'],
                ':q' => $item['quantity'],
            ]);
        }
    }

    /** @return array<int, array{description:string, quantity:int}> */
    private function itemsData(int $partOrderId): array
    {
        $stmt = $this->pdo->prepare('SELECT description, quantity FROM part_order_items WHERE part_order_id = :id ORDER BY id');
        $stmt->execute([':id' => $partOrderId]);

        return array_map(static function (array $row): array {
            return [
                'description' => (string) $row['description'],
                'quantity' => (int) $row['quantity'],
            ];
        }, $stmt->fetchAll());
    }

    private function findOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM part_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
