<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Mechanic\Resources\VehicleResource;
use Psr\Http\Message\ServerRequestInterface;

class VehicleController extends CrudController
{
    protected function resource(): Resource
    {
        return new VehicleResource();
    }

    /** Veículos não são excluídos: o correto é desativar o cadastro. */
    public function destroy(ServerRequestInterface $request, $id)
    {
        $this->session->flash('error', 'Não é possível excluir veículos. Desative o cadastro.');
        return redirect('/vehicles');
    }
}
