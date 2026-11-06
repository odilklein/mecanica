<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Resources\ClientResource;

class ClientController extends CrudController
{
    protected function resource(): Resource
    {
        return new ClientResource();
    }
}
