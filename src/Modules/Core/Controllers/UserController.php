<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Resources\UserResource;
use Psr\Http\Message\ServerRequestInterface;

class UserController extends CrudController
{
    protected function resource(): Resource
    {
        return new UserResource();
    }

    /** Funcionários não são excluídos: o correto é desativar o cadastro. */
    public function destroy(ServerRequestInterface $request, $id)
    {
        $this->session->flash('error', 'Não é possível excluir funcionários. Desative o cadastro.');
        return redirect('/users');
    }
}
