<?php
declare(strict_types=1);

namespace Modules\Core\Resources;

use Kernel\Crud\Resource;

class ClientResource extends Resource
{
    public function key(): string
    {
        return 'clients';
    }

    public function table(): string
    {
        return 'clients';
    }

    public function labels(): array
    {
        return ['singular' => 'Cliente', 'plural' => 'Clientes'];
    }

    public function icon(): string
    {
        return 'users';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'name', 'label' => 'Nome'],
            ['field' => 'cpf', 'label' => 'CPF'],
            ['field' => 'phone', 'label' => 'Telefone'],
            ['field' => 'email', 'label' => 'E-mail'],
            ['field' => 'city', 'label' => 'Cidade'],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'icon' => 'user', 'required' => true, 'max' => 120, 'col' => 'col-md-8'],
            ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'icon' => 'id', 'mask' => 'cpf', 'validate' => 'cpf', 'unique' => true, 'max' => 14, 'col' => 'col-md-4'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 20, 'col' => 'col-md-6'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'icon' => 'mail', 'validate' => 'email', 'max' => 120, 'col' => 'col-md-6'],
            ['name' => 'address', 'label' => 'Endereço', 'type' => 'text', 'icon' => 'map-pin', 'max' => 255, 'col' => 'col-md-8'],
            ['name' => 'city', 'label' => 'Cidade', 'type' => 'text', 'icon' => 'building', 'max' => 80, 'col' => 'col-md-4'],
            ['name' => 'notes', 'label' => 'Observações', 'type' => 'textarea', 'icon' => 'notes'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'boolean', 'icon' => 'toggle-left', 'default' => 1],
        ];
    }
}
