<?php
declare(strict_types=1);

namespace Modules\Core\Resources;

use Kernel\Crud\Resource;

class UserResource extends Resource
{
    public function key(): string
    {
        return 'users';
    }

    public function table(): string
    {
        return 'users';
    }

    public function labels(): array
    {
        return ['singular' => 'Funcionário', 'plural' => 'Funcionários'];
    }

    public function icon(): string
    {
        return 'users-group';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'name', 'label' => 'Nome'],
            ['field' => 'email', 'label' => 'E-mail'],
            ['field' => 'role', 'label' => 'Perfil', 'format' => static fn ($v) => roleLabel($v)],
            ['field' => 'job_title', 'label' => 'Cargo'],
            ['field' => 'is_active', 'label' => 'Situação', 'format' => static fn ($v) => $v ? 'Ativo' : 'Inativo'],
        ];
    }

    public function selectOptions(\PDO $pdo, array $data = []): array
    {
        return [
            'role' => [
                'admin' => 'Administrador',
                'secretary' => 'Secretaria',
                'lead_mechanic' => 'Mecânico-chefe',
                'mechanic' => 'Mecânico',
            ],
        ];
    }

    public function prepareData(array $data, ?int $ignoreId, \PDO $pdo): array
    {
        if (array_key_exists('password', $data)) {
            if ($data['password'] === '' || $data['password'] === null) {
                // Em edição, senha vazia mantém a atual.
                unset($data['password']);
            } else {
                $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            }
        }
        return $data;
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'icon' => 'user', 'required' => true, 'max' => 100, 'col' => 'col-md-6'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'icon' => 'mail', 'required' => true, 'validate' => 'email', 'unique' => true, 'max' => 120, 'col' => 'col-md-6'],
            ['name' => 'role', 'label' => 'Perfil', 'type' => 'select', 'icon' => 'shield', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'password', 'label' => 'Senha', 'type' => 'password', 'icon' => 'lock', 'required_on_create' => true, 'help' => 'Na edição, deixe em branco para manter a senha atual.', 'col' => 'col-md-6'],
            ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'icon' => 'id', 'mask' => 'cpf', 'validate' => 'cpf', 'unique' => true, 'max' => 14, 'col' => 'col-md-4'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 20, 'col' => 'col-md-4'],
            ['name' => 'hire_date', 'label' => 'Admissão', 'type' => 'date', 'icon' => 'calendar', 'col' => 'col-md-4'],
            ['name' => 'job_title', 'label' => 'Cargo', 'type' => 'text', 'icon' => 'briefcase', 'max' => 100, 'col' => 'col-md-6'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'boolean', 'icon' => 'toggle-left', 'default' => 1],
        ];
    }
}
