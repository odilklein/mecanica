<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Mechanic\Resources\BudgetResource;
use Modules\Mechanic\Support\BudgetStatus;
use Psr\Http\Message\ServerRequestInterface;

class BudgetController extends CrudController
{
    protected function resource(): Resource
    {
        return new BudgetResource();
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

    public function addItem(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $description = clean($body['description'] ?? '');
        if ($description !== '') {
            $type = ($body['type'] ?? 'part') === 'service' ? 'service' : 'part';
            $qty = (float) str_replace(',', '.', (string) ($body['quantity'] ?? 1));
            $price = (float) str_replace(',', '.', (string) ($body['unit_price'] ?? 0));
            $subtotal = round($qty * $price, 2);

            $this->pdo->prepare(
                'INSERT INTO budget_items (budget_id, type, description, quantity, unit_price, subtotal)
                 VALUES (:b, :t, :d, :q, :p, :s)'
            )->execute([
                ':b' => (int) $id,
                ':t' => $type,
                ':d' => mb_substr($description, 0, 200),
                ':q' => $qty > 0 ? $qty : 1,
                ':p' => $price,
                ':s' => $subtotal,
            ]);

            $this->recalcBudget((int) $id);
            $this->session->flash('success', 'Item adicionado.');
        }

        return redirect($resource->path() . '/' . (int) $id);
    }

    public function removeItem(ServerRequestInterface $request, $itemId)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $stmt = $this->pdo->prepare('SELECT budget_id FROM budget_items WHERE id = :id');
        $stmt->execute([':id' => (int) $itemId]);
        $budgetId = (int) $stmt->fetchColumn();

        if ($budgetId > 0) {
            if ($response = $this->guardCsrf($request, $resource->path() . '/' . $budgetId)) {
                return $response;
            }
            $this->pdo->prepare('DELETE FROM budget_items WHERE id = :id')->execute([':id' => (int) $itemId]);
            $this->recalcBudget($budgetId);
            $this->session->flash('success', 'Item removido.');
            return redirect($resource->path() . '/' . $budgetId);
        }

        return redirect($resource->path());
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
