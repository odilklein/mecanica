<?php
declare(strict_types=1);

namespace Modules\Core\Repositories;

use Kernel\Database\Repository;

class ClientRepository extends Repository
{
    protected string $table = 'clients';
    protected string $searchable = 'name';
    protected string $orderBy = 'id';
    protected string $orderDirection = 'desc';

    protected array $fillable = [
        'name', 'cpf', 'phone', 'email', 'address', 'city', 'notes', 'is_active',
    ];
}
