<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Resources\UserResource;

class UserController extends CrudController
{
    protected function resource(): Resource
    {
        return new UserResource();
    }
}
