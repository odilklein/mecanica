<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Mechanic\Resources\PartOrderResource;

class PartOrderController extends CrudController
{
    protected function resource(): Resource
    {
        return new PartOrderResource();
    }
}
