<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Support\Notify;
use Modules\Mechanic\Resources\BudgetResource;
use Modules\Mechanic\Services\TicketService;
use Modules\Mechanic\Support\BudgetStatus;
use Psr\Http\Message\ServerRequestInterface;

class BudgetController extends CrudController
{
    protected function resource(): Resource
    {
        return new BudgetResource();
    }

    public function create(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $query = $request->getQueryParams();
        $defaults = [
            'client_id' => (int) ($query['cliente_id'] ?? 0),
            'vehicle_id' => (int) ($query['veiculo_id'] ?? 0),
            'service_order_id' => (int) ($query['os_id'] ?? 0),
        ];

        return $this->renderEditor($resource, null, [], $defaults);
    }

    public function edit(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        $budget = $this->findBudget((int) $id);
        if (!$budget) {
            $this->session->flash('error', 'Orçamento não encontrado.');
            return redirect($resource->path());
        }
        $items = $this->pdo->prepare('SELECT * FROM budget_items WHERE budget_id = :id ORDER BY id');
        $items->execute([':id' => (int) $id]);

        return $this->renderEditor($resource, $budget, $items->fetchAll());
    }

    public function save(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $body = $request->getParsedBody();
        $id = (int) ($body['id'] ?? 0);
        $clientId = (int) ($body['client_id'] ?? 0);
        $vehicleId = (int) ($body['vehicle_id'] ?? 0) ?: null;
        $serviceOrderId = (int) ($body['service_order_id'] ?? 0) ?: null;
        $validUntil = clean($body['valid_until'] ?? '') ?: null;
        $discount = max(0, (float) str_replace(',', '.', (string) ($body['discount'] ?? 0)));
        $notes = clean($body['notes'] ?? '');
        $items = json_decode((string) ($body['items_json'] ?? '[]'), true) ?: [];

        if ($clientId <= 0) {
            $this->session->flash('error', 'Selecione o cliente.');
            return redirect($resource->path() . ($id > 0 ? '/' . $id . '/edit' : '/create'));
        }

        if ($vehicleId !== null) {
            $vehicleCheck = $this->pdo->prepare(
                'SELECT 1 FROM vehicles WHERE id = ? AND client_id = ? AND is_active = 1'
            );
            $vehicleCheck->execute([$vehicleId, $clientId]);
            if (!$vehicleCheck->fetchColumn()) {
                $this->session->flash('error', 'Veículo inválido ou inativo para o cliente selecionado.');
                return redirect($resource->path() . ($id > 0 ? '/' . $id . '/edit' : '/create'));
            }
        }

        if ($serviceOrderId !== null) {
            $orderCheck = $this->pdo->prepare('SELECT 1 FROM service_orders WHERE id = ? AND client_id = ?');
            $orderCheck->execute([$serviceOrderId, $clientId]);
            if (!$orderCheck->fetchColumn()) {
                $this->session->flash('error', 'A OS selecionada não pertence a este cliente.');
                return redirect($resource->path() . ($id > 0 ? '/' . $id . '/edit' : '/create'));
            }
        }

        $isNew = $id <= 0;

        try {
            $this->pdo->beginTransaction();

            if ($isNew) {
                $number = (int) $this->pdo->query('SELECT COALESCE(MAX(number), 0) + 1 FROM budgets')->fetchColumn();
                $this->pdo->prepare(
                    'INSERT INTO budgets (number, client_id, vehicle_id, service_order_id, budget_date, valid_until, status, discount, notes, created_by)
                     VALUES (:num, :cl, :ve, :so, :bd, :vu, :st, :d, :n, :u)'
                )->execute([
                    ':num' => $number, ':cl' => $clientId, ':ve' => $vehicleId, ':so' => $serviceOrderId,
                    ':bd' => date('Y-m-d'), ':vu' => $validUntil, ':st' => 'open',
                    ':d' => $discount, ':n' => $notes, ':u' => $this->auth->id(),
                ]);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $this->pdo->prepare(
                    'UPDATE budgets SET client_id = :cl, vehicle_id = :ve, service_order_id = :so, valid_until = :vu, discount = :d, notes = :n WHERE id = :id'
                )->execute([':cl' => $clientId, ':ve' => $vehicleId, ':so' => $serviceOrderId, ':vu' => $validUntil, ':d' => $discount, ':n' => $notes, ':id' => $id]);
                $this->pdo->prepare('DELETE FROM budget_items WHERE budget_id = :id')->execute([':id' => $id]);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO budget_items (budget_id, type, description, quantity, unit_price, subtotal)
                 VALUES (:b, :t, :d, :q, :p, :s)'
            );
            foreach ($items as $item) {
                $desc = trim((string) ($item['description'] ?? ''));
                if ($desc === '') {
                    continue;
                }
                $type = ($item['type'] ?? 'part') === 'service' ? 'service' : 'part';
                $qty = max(0.01, (float) ($item['quantity'] ?? 1));
                $price = max(0, (float) ($item['unit_price'] ?? 0));
                $stmt->execute([
                    ':b' => $id, ':t' => $type, ':d' => mb_substr($desc, 0, 200),
                    ':q' => $qty, ':p' => $price, ':s' => round($qty * $price, 2),
                ]);
            }

            $this->recalcBudget($id);
            if ($serviceOrderId) {
                // Mantém o resumo automático do ticket da OS atualizado.
                (new TicketService($this->pdo))->ensureForOrder((int) $serviceOrderId);
            }
            record_audit($this->pdo, $this->auth->id(), 'budgets', $id, $isNew ? 'create' : 'update');
            $this->pdo->commit();

            $this->session->flash('success', $isNew ? 'Orçamento criado.' : 'Orçamento atualizado.');
            return redirect($resource->path() . '/' . $id);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Budget save: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível salvar o orçamento.');
            return redirect($resource->path() . ($isNew ? '/create' : '/' . $id . '/edit'));
        }
    }

    public function show(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $budget = $this->findBudget((int) $id);
        if (!$budget) {
            $this->session->flash('error', 'Orçamento não encontrado.');
            return redirect($resource->path());
        }

        $items = $this->pdo->prepare('SELECT * FROM budget_items WHERE budget_id = :id ORDER BY id');
        $items->execute([':id' => (int) $id]);

        return $this->render('mechanic::budgets/show', [
            'title' => 'Orçamento #' . str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT),
            'resource' => $resource,
            'budget' => $budget,
            'items' => $items->fetchAll(),
            'statuses' => BudgetStatus::STATUSES,
        ]);
    }

    public function updateStatus(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $status = (string) ($request->getParsedBody()['status'] ?? '');
        if (!isset(BudgetStatus::STATUSES[$status])) {
            $this->session->flash('error', 'Status inválido.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $budget = $this->findBudget((int) $id);
        if (!$budget) {
            $this->session->flash('error', 'Orçamento não encontrado.');
            return redirect($resource->path());
        }
        if ((string) $budget['status'] === $status) {
            $this->session->flash('success', 'O status já é "' . BudgetStatus::label($status) . '".');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $this->pdo->prepare('UPDATE budgets SET status = :s WHERE id = :id')
            ->execute([':s' => $status, ':id' => (int) $id]);
        record_audit($this->pdo, $this->auth->id(), 'budgets', (int) $id, 'status', ['status' => $status]);
        $this->notifyBudgetStatus($budget, $status);
        $this->session->flash('success', 'Status do orçamento atualizado para "' . BudgetStatus::label($status) . '".');
        return redirect($resource->path() . '/' . (int) $id);
    }

    /** Notifica a equipe da OS quando o orçamento muda de status. */
    private function notifyBudgetStatus(array $budget, string $status): void
    {
        $orderId = (int) ($budget['service_order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT number, created_by, lead_mechanic_id FROM service_orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return;
        }

        $ids = [(int) ($order['created_by'] ?? 0), (int) ($order['lead_mechanic_id'] ?? 0)];
        $resp = $this->pdo->prepare('SELECT user_id FROM service_order_responsibles WHERE order_id = ?');
        $resp->execute([$orderId]);
        foreach ($resp->fetchAll(\PDO::FETCH_COLUMN) as $userId) {
            $ids[] = (int) $userId;
        }

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
            'os_status',
            'Orçamento #' . str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT)
                . ' da OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)
                . ' foi ' . mb_strtolower(BudgetStatus::label($status)) . '.',
            '/budgets/' . (int) $budget['id'],
            $actor
        );
    }

    public function print(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $budget = $this->findBudget((int) $id);
        if (!$budget) {
            return redirect($resource->path());
        }
        $items = $this->pdo->prepare('SELECT * FROM budget_items WHERE budget_id = :id ORDER BY id');
        $items->execute([':id' => (int) $id]);

        return $this->view->render('mechanic::budgets/print', [
            'budget' => $budget,
            'items' => $items->fetchAll(),
        ]);
    }

    private function renderEditor(Resource $resource, ?array $budget, array $items, array $defaults = []): string
    {
        $clients = $this->pdo->query('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name')->fetchAll();
        $vehicles = $this->pdo->query('SELECT id, client_id, plate, model FROM vehicles WHERE is_active = 1 ORDER BY plate')->fetchAll();

        return $this->render('mechanic::budgets/editor', [
            'title' => $budget ? 'Editar orçamento' : 'Novo orçamento',
            'resource' => $resource,
            'budget' => $budget,
            'items' => $items,
            'defaults' => $defaults,
            'clients' => $clients,
            'vehicles' => $vehicles,
        ]);
    }

    private function recalcBudget(int $budgetId): void
    {
        $stmt = $this->pdo->prepare('SELECT type, COALESCE(SUM(subtotal), 0) AS s FROM budget_items WHERE budget_id = :id GROUP BY type');
        $stmt->execute([':id' => $budgetId]);

        $parts = 0.0;
        $labor = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            if ($row['type'] === 'part') {
                $parts = (float) $row['s'];
            } elseif ($row['type'] === 'service') {
                $labor = (float) $row['s'];
            }
        }

        $discountStmt = $this->pdo->prepare('SELECT discount FROM budgets WHERE id = :id');
        $discountStmt->execute([':id' => $budgetId]);
        $discount = (float) $discountStmt->fetchColumn();
        $total = max(0, $parts + $labor - $discount);

        $this->pdo->prepare('UPDATE budgets SET parts_total = :p, labor_total = :l, total = :t WHERE id = :id')
            ->execute([':p' => $parts, ':l' => $labor, ':t' => $total, ':id' => $budgetId]);
    }

    private function findBudget(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, c.name AS client_name, v.plate AS vehicle_plate, v.model AS vehicle_model,
                    so.number AS order_number
               FROM budgets b
               LEFT JOIN clients c ON c.id = b.client_id
               LEFT JOIN vehicles v ON v.id = b.vehicle_id
               LEFT JOIN service_orders so ON so.id = b.service_order_id
              WHERE b.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
