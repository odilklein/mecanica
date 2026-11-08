<?php
// =============================================
// MENU — registro de itens de navegação.
// Módulos adicionam itens; o layout renderiza
// apenas os permitidos ao papel do usuário.
// Suporta subitens (children) para submenus.
// =============================================

declare(strict_types=1);

namespace Kernel\Menu;

use Kernel\Auth\Auth;

class Menu
{
    /**
     * Ordem preferencial das seções na sidebar. Módulos podem registrar
     * seções em qualquer momento; a exibição segue esta ordem. Seções
     * desconhecidas aparecem depois, preservando a ordem de registro.
     */
    private const SECTION_ORDER = [
        'Principal' => 10,
        'Cadastros' => 20,
        'Operações' => 30,
        'Documentos' => 40,
        'Atendimento' => 50,
        'Sistema' => 90,
    ];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $sections = [];

    /**
     * @param array{label: string, icon?: string, url?: string, roles?: array<string>|null, children?: array<int, array<string, mixed>>} $item
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
                $filtered = $this->filterItem($item, $auth);
                if ($filtered !== null) {
                    $visible[] = $filtered;
                }
            }
            if ($visible) {
                $result[$section] = $visible;
            }
        }

        // Ordena as seções conforme a hierarquia visual definida acima.
        // A ordenação do PHP é estável: empates mantêm a ordem de registro.
        uksort($result, static function (string $a, string $b): int {
            return (self::SECTION_ORDER[$a] ?? 999) <=> (self::SECTION_ORDER[$b] ?? 999);
        });

        return $result;
    }

    /**
     * Filtra um item pelo papel do usuário, recursivamente nos subitens.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function filterItem(array $item, Auth $auth): ?array
    {
        $roles = $item['roles'] ?? null;
        if ($roles !== null && !$auth->hasRole($roles)) {
            return null;
        }

        if (array_key_exists('children', $item)) {
            $children = [];
            foreach ($item['children'] as $child) {
                $filtered = $this->filterItem($child, $auth);
                if ($filtered !== null) {
                    $children[] = $filtered;
                }
            }
            $item['children'] = $children;

            // Item sem url nem subitens visíveis não deve aparecer.
            if (!$children && empty($item['url'])) {
                return null;
            }
        }

        return $item;
    }
}
