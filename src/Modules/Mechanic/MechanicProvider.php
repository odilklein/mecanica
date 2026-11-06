<?php
// =============================================
// MECHANIC — vertical de negócio da oficina
// (veículos, OS, peças, orçamentos). Depende do Core.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic;

use Kernel\Container\Container;
use Kernel\Http\Router;
use Kernel\Menu\Menu;
use Kernel\Modules\ProviderInterface;
use Modules\Mechanic\Controllers\FipeController;
use Modules\Mechanic\Controllers\VehicleController;

class MechanicProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        // Veículos (CRUD genérico).
        crud_routes($router, '/vehicles', VehicleController::class);

        // API do catálogo FIPE (selects em cascata).
        $router->get('/api/fipe/models', [FipeController::class, 'models']);
        $router->get('/api/fipe/years', [FipeController::class, 'years']);
        $router->get('/api/fipe/price', [FipeController::class, 'price']);

        $this->menu($container);
    }

    private function menu(Container $container): void
    {
        /** @var Menu $menu */
        $menu = $container->get(Menu::class);

        $menu->add('Cadastros', [
            'label' => 'Veículos',
            'icon' => 'car',
            'url' => '/vehicles',
            'roles' => ['admin', 'secretary', 'lead_mechanic'],
        ]);
    }
}
