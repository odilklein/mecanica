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

    public function formTitle(bool $isEdit): string
    {
        return $isEdit ? 'Editar funcionário' : 'Novo funcionário';
    }

    public function icon(): string
    {
        return 'users-group';
    }

    public function roles(): array
    {
        return ['admin'];
    }

    public function datatableSubtitle(): string
    {
        return 'Gerencie os funcionários da oficina';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo funcionário';
    }

    /** A listagem não tem ações em massa: sem checkboxes. */
    public function selectable(): bool
    {
        return false;
    }

    public function datatableOrder(): array
    {
        return [[2, 'asc']];
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'role_label', 'label' => 'Perfil', 'options' => [
                'admin' => 'Administrador',
                'secretary' => 'Secretaria',
                'lead_mechanic' => 'Mecânico-chefe',
                'mechanic' => 'Mecânico',
            ]],
            ['field' => 'status', 'label' => 'Situação', 'options' => [
                1 => 'Ativo',
                0 => 'Inativo',
            ]],
        ];
    }

    public function datatableColumns(): array
    {
        return [
            $this->dtControlColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Nome',
                'data' => 'name',
                'render' => 'name',
                'secondary' => 'post',
                'priority' => 1,
            ],
            ['title' => 'E-mail', 'data' => 'email', 'render' => 'text', 'priority' => 5],
            ['title' => 'Admissão', 'data' => 'hire_date', 'render' => 'date', 'priority' => 6],
            ['title' => 'Salário', 'data' => 'salary', 'render' => 'money', 'priority' => 7],
            [
                'title' => 'Situação',
                'data' => 'status',
                'render' => 'badge',
                'badge' => [
                    1 => ['label' => 'Ativo', 'class' => 'bg-label-success'],
                    0 => ['label' => 'Inativo', 'class' => 'bg-label-secondary'],
                ],
                'priority' => 4,
            ],
            $this->dtActionsColumn([
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $row['post'] = $row['job_title'] ?: roleLabel($row['role']);
        $row['role_label'] = (string) $row['role'];
        $row['status'] = (int) $row['is_active'];
        return $row;
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
            ['name' => 'role', 'label' => 'Perfil', 'type' => 'select', 'icon' => 'shield', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'salary', 'label' => 'Salário', 'type' => 'number', 'icon' => 'currency-real', 'min' => 0, 'step' => '0.01', 'col' => 'col-md-4'],
            ['name' => 'hire_date', 'label' => 'Admissão', 'type' => 'date', 'icon' => 'calendar', 'col' => 'col-md-4'],
            ['name' => 'password', 'label' => 'Senha', 'type' => 'password', 'icon' => 'lock', 'required_on_create' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'help' => 'Mínimo de 8 caracteres. Na edição, deixe em branco para manter a senha atual.', 'col' => 'col-md-6'],
            ['name' => 'job_title', 'label' => 'Cargo', 'type' => 'text', 'icon' => 'briefcase', 'max' => 100, 'col' => 'col-md-6'],
            ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'icon' => 'id', 'mask' => 'cpf', 'validate' => 'cpf', 'unique' => true, 'max' => 14, 'col' => 'col-md-4'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 20, 'col' => 'col-md-4'],
            ['name' => 'is_active', 'label' => 'Funcionário ativo', 'type' => 'boolean', 'icon' => 'toggle-right', 'default' => 1, 'col' => 'col-md-4'],
        ];
    }
}
