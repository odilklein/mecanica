<?php
// =============================================
// MENU — registro de itens de navegação.
// Módulos adicionam itens; o layout renderiza
// apenas os permitidos ao papel do usuário.
// =============================================

declare(strict_types=1);

namespace Kernel\Menu;

use Kernel\Auth\Auth;

class Menu
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $sections = [];

    /**
     * @param array{label: string, icon?: string, url: string, roles?: array<string>|null} $item
     */
    public function add(string $section, array $item): void
    {
        $this->sections[$section][] = $item;
    }

    /**
     * Retorna as seções/itens visíveis para o usuário autenticado.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function items(Auth $auth): array
    {
        $result = [];

        foreach ($this->sections as $section => $items) {
            $visible = [];
            foreach ($items as $item) {
                $roles = $item['roles'] ?? null;
                if ($roles === null || $auth->hasRole($roles)) {
                    $visible[] = $item;
                }
            }
            if ($visible) {
                $result[$section] = $visible;
            }
        }

        return $result;
    }
}
