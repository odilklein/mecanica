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
            ['field' => 'phone', 'label' => 'Telefone'],
            ['field' => 'email', 'label' => 'E-mail'],
            ['field' => 'city', 'label' => 'Cidade'],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'icon' => 'user', 'required' => true, 'max' => 120, 'col' => 'col-md-8', 'placeholder' => 'Ex.: Maria da Silva'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 20, 'col' => 'col-md-4', 'placeholder' => '(11) 98888-7777'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'icon' => 'mail', 'validate' => 'email', 'max' => 120, 'col' => 'col-md-6', 'placeholder' => 'cliente@email.com'],
            ['name' => 'zip', 'label' => 'CEP', 'type' => 'text', 'icon' => 'map-pin', 'mask' => 'cep', 'max' => 10, 'col' => 'col-md-6', 'placeholder' => '00000-000', 'help' => 'Preenche o endereço automaticamente.'],
            ['name' => 'address', 'label' => 'Endereço', 'type' => 'text', 'icon' => 'road', 'max' => 255, 'col' => 'col-md-8', 'placeholder' => 'Rua, número, complemento'],
            ['name' => 'neighborhood', 'label' => 'Bairro', 'type' => 'text', 'icon' => 'building-community', 'max' => 80, 'col' => 'col-md-4', 'placeholder' => 'Centro'],
            ['name' => 'city', 'label' => 'Cidade', 'type' => 'text', 'icon' => 'building', 'max' => 80, 'col' => 'col-md-8', 'placeholder' => 'São Paulo'],
            ['name' => 'state', 'label' => 'UF', 'type' => 'text', 'icon' => 'map', 'max' => 2, 'col' => 'col-md-4', 'placeholder' => 'SP'],
        ];
    }
}
