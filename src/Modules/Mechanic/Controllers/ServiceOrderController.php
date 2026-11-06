<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Support\Notify;
use Modules\Mechanic\Resources\ServiceOrderResource;
use Modules\Mechanic\Support\ServiceOrderStatus;
use Psr\Http\Message\ServerRequestInterface;

class ServiceOrderController extends CrudController
{
    protected function resource(): Resource
    {
        return new ServiceOrderResource();
    }

    /** Página de detalhes: dados, status e tarefas. */
    public function show(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }

        $tasks = $this->pdo->prepare('SELECT * FROM service_order_tasks WHERE order_id = :id ORDER BY id');
        $tasks->execute([':id' => (int) $id]);

        return $this->render('mechanic::service_orders/show', [
            'title' => 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT),
            'resource' => $resource,
            'order' => $order,
            'tasks' => $tasks->fetchAll(),
            'statuses' => ServiceOrderStatus::STATUSES,
        ]);
    }

    /** Altera o status da OS. */
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
        if (!isset(ServiceOrderStatus::STATUSES[$status])) {
            $this->session->flash('error', 'Status inválido.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $completion = $status === 'delivered' ? date('Y-m-d') : null;
        $stmt = $this->pdo->prepare('UPDATE service_orders SET status = :s, completion_date = :c WHERE id = :id');
        $stmt->execute([':s' => $status, ':c' => $completion, ':id' => (int) $id]);

        $order = $this->findOrder((int) $id);
        if ($order) {
            $recipients = array_filter([
                (int) ($order['lead_mechanic_id'] ?? 0),
                (int) ($order['created_by'] ?? 0),
                (int) ($this->auth->id() ?? 0),
            ]);
            Notify::to(
                $this->pdo,
                $recipients,
                'os_status',
                'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)
                    . ' mudou para "' . ServiceOrderStatus::label($status) . '".',
                '/service-orders/' . (int) $id
            );
        }

        $this->session->flash('success', 'Status atualizado para "' . ServiceOrderStatus::label($status) . '".');
        return redirect($resource->path() . '/' . (int) $id);
    }

    private function findOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT so.*, c.name AS client_name, c.phone AS client_phone,
                    v.plate AS vehicle_plate, v.model AS vehicle_model, v.brand AS vehicle_brand,
                    u.name AS lead_mechanic
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
               LEFT JOIN users u ON u.id = so.lead_mechanic_id
              WHERE so.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
