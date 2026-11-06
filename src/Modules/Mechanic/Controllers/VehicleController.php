<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Mechanic\Resources\VehicleResource;

class VehicleController extends CrudController
{
    protected function resource(): Resource
    {
        return new VehicleResource();
    }
}
