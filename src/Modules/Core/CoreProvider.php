<?php
// =============================================
// CORE — módulo base: autenticação, dashboard,
// clientes e o menu do sistema.
// =============================================

declare(strict_types=1);

namespace Modules\Core;

use Kernel\Container\Container;
use Kernel\Http\Router;
use Kernel\Menu\Menu;
use Kernel\Modules\ProviderInterface;
use Modules\Core\Controllers\AuthController;
use Modules\Core\Controllers\ClientController;
use Modules\Core\Controllers\DashboardController;
use Modules\Core\Controllers\UserController;

class CoreProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        // Autenticação.
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);
        $router->post('/logout', [AuthController::class, 'logout']);

        // Dashboard (protegido).
        $router->get('/', [DashboardController::class, 'index']);

        // Clientes (CRUD genérico).
        crud_routes($router, '/clients', ClientController::class);

        // Funcionários (usuários).
        crud_routes($router, '/users', UserController::class);

        $this->menu($container);
    }

    private function menu(Container $container): void
    {
        /** @var Menu $menu */
        $menu = $container->get(Menu::class);

        $menu->add('Principal', ['label' => 'Dashboard', 'icon' => 'smart-home', 'url' => '/']);
        $menu->add('Cadastros', ['label' => 'Clientes', 'icon' => 'users', 'url' => '/clients', 'roles' => ['admin', 'secretary', 'lead_mechanic']]);
        $menu->add('Cadastros', ['label' => 'Funcionários', 'icon' => 'users-group', 'url' => '/users', 'roles' => ['admin', 'secretary', 'lead_mechanic']]);
        $menu->add('Sistema', ['label' => 'Configurações', 'icon' => 'settings', 'url' => '#', 'roles' => ['admin']]);
        $menu->add('Sistema', ['label' => 'Auditoria', 'icon' => 'history', 'url' => '#', 'roles' => ['admin']]);
    }
}
