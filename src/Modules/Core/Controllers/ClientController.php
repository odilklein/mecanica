<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Resources\ClientResource;
use Psr\Http\Message\ServerRequestInterface;

class ClientController extends CrudController
{
    protected function resource(): Resource
    {
        return new ClientResource();
    }

    public function show(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id');
        $stmt->execute([':id' => (int) $id]);
        $client = $stmt->fetch();
        if (!$client) {
            $this->session->flash('error', 'Cliente não encontrado.');
            return redirect($resource->path());
        }

        $vehicles = $this->pdo->prepare('SELECT * FROM vehicles WHERE client_id = :id ORDER BY brand, model');
        $vehicles->execute([':id' => (int) $id]);

        $orders = $this->pdo->prepare(
            'SELECT so.id, so.number, so.status, so.entry_date, so.initial_complaint, v.plate, v.model
               FROM service_orders so
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.client_id = :id
              ORDER BY so.number DESC'
        );
        $orders->execute([':id' => (int) $id]);

        return $this->render('core::clients/show', [
            'title' => $client['name'],
            'resource' => $resource,
            'client' => $client,
            'vehicles' => $vehicles->fetchAll(),
            'orders' => $orders->fetchAll(),
        ]);
    }

    /** Clientes não são excluídos: o correto é desativar o cadastro. */
    public function destroy(ServerRequestInterface $request, $id)
    {
        $this->session->flash('error', 'Não é possível excluir clientes. Desative o cadastro.');
        return redirect('/clients');
    }
}
