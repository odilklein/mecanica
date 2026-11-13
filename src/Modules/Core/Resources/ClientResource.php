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

    public function formTitle(bool $isEdit): string
    {
        return $isEdit ? 'Editar cliente' : 'Novo cliente';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function detailPath(): ?string
    {
        return '/clients';
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

    public function datatableSubtitle(): string
    {
        return 'Gerencie os clientes da oficina';
    }

    public function datatableNewLabel(): string
    {
        return 'Novo cliente';
    }

    public function datatableOrder(): array
    {
        return [[2, 'asc']];
    }

    /** A listagem não tem ações em massa: sem checkboxes. */
    public function selectable(): bool
    {
        return false;
    }

    public function datatableFilters(): array
    {
        return [
            ['field' => 'neighborhood', 'label' => 'Bairro'],
        ];
    }

    /**
     * A listagem mostra quantos veículos o cliente tem.
     *
     * @return array<int, array<string, mixed>>
     */
    public function datatableRows(\PDO $pdo): array
    {
        $sql = 'SELECT c.*, (
                    SELECT GROUP_CONCAT(v.plate ORDER BY v.plate SEPARATOR ",")
                      FROM vehicles v
                     WHERE v.client_id = c.id AND v.is_active = 1
                ) AS vehicle_plates
                  FROM clients c
                 ORDER BY c.name';

        return $pdo->query($sql)->fetchAll();
    }

    public function datatableColumns(): array
    {
        return [
            $this->dtControlColumn(),
            $this->dtIdColumn(),
            [
                'title' => 'Cliente',
                'data' => 'name',
                'render' => 'name',
                'secondary' => 'phone',
                'href' => $this->path() . '/{id}',
                'priority' => 1,
            ],
            ['title' => 'E-mail', 'data' => 'email', 'render' => 'text', 'priority' => 5],
            ['title' => 'Bairro', 'data' => 'neighborhood', 'render' => 'text', 'priority' => 6],
            ['title' => 'Veículos', 'data' => 'vehicles_label', 'render' => 'text', 'priority' => 4],
            $this->dtActionsColumn([
                ['label' => 'Detalhes', 'icon' => 'eye', 'href' => $this->path() . '/{id}'],
                ['label' => 'Editar', 'icon' => 'pencil', 'href' => $this->path() . '/{id}/edit', 'primary' => true],
            ]),
        ];
    }

    public function datatableRow(array $row): array
    {
        $plates = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($row['vehicle_plates'] ?? ''))
        )));

        if (!$plates) {
            $row['vehicles_label'] = '—';
        } else {
            $label = implode(' · ', array_slice($plates, 0, 2));
            if (count($plates) > 2) {
                $label .= ' +' . (count($plates) - 2);
            }
            $row['vehicles_label'] = $label;
        }

        return $row;
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'icon' => 'user', 'required' => true, 'max' => 120, 'col' => 'col-md-8', 'placeholder' => 'Ex.: Maria da Silva'],
            ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'icon' => 'id', 'mask' => 'cpf', 'validate' => 'cpf', 'unique' => true, 'max' => 14, 'col' => 'col-md-4', 'placeholder' => '000.000.000-00'],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'icon' => 'phone', 'mask' => 'phone', 'max' => 20, 'col' => 'col-md-4', 'placeholder' => '(11) 98888-7777'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'icon' => 'mail', 'validate' => 'email', 'max' => 120, 'col' => 'col-md-8', 'placeholder' => 'cliente@email.com'],
            ['name' => 'zip', 'label' => 'CEP', 'type' => 'text', 'icon' => 'map-pin', 'mask' => 'cep', 'max' => 10, 'col' => 'col-md-4', 'placeholder' => '00000-000', 'help' => 'Preenche o endereço automaticamente.'],
            ['name' => 'address', 'label' => 'Endereço', 'type' => 'text', 'icon' => 'road', 'max' => 255, 'col' => 'col-md-6', 'placeholder' => 'Rua'],
            ['name' => 'address_number', 'label' => 'Número', 'type' => 'text', 'icon' => 'hash', 'max' => 20, 'col' => 'col-md-2', 'placeholder' => 'Ex.: 123'],
            ['name' => 'neighborhood', 'label' => 'Bairro', 'type' => 'text', 'icon' => 'building-community', 'max' => 80, 'col' => 'col-md-4', 'placeholder' => 'Centro'],
            ['name' => 'city', 'label' => 'Cidade', 'type' => 'text', 'icon' => 'building', 'max' => 80, 'col' => 'col-md-6', 'placeholder' => 'São Paulo'],
            ['name' => 'state', 'label' => 'UF', 'type' => 'text', 'icon' => 'map', 'max' => 2, 'col' => 'col-md-2', 'placeholder' => 'SP'],
        ];
    }
}
