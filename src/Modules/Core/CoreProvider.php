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
use Modules\Core\Controllers\AuditController;
use Modules\Core\Controllers\ClientController;
use Modules\Core\Controllers\DashboardController;
use Modules\Core\Controllers\NotificationController;
use Modules\Core\Controllers\ProfileController;
use Modules\Core\Controllers\SettingsController;
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
        $router->get('/clients/{id}', [ClientController::class, 'show']);

        // Funcionários (usuários).
        $router->get('/users/datatable', [UserController::class, 'datatable']);
        crud_routes($router, '/users', UserController::class);

        // Auditoria (somente leitura).
        $router->get('/audit', [AuditController::class, 'index']);

        // Notificações.
        $router->get('/notifications', [NotificationController::class, 'index']);
        $router->post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        $router->post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        $router->get('/api/notifications', [NotificationController::class, 'api']);

        // Meu perfil.
        $router->get('/profile', [ProfileController::class, 'show']);
        $router->post('/profile', [ProfileController::class, 'update']);

        // Configurações (admin).
        $router->get('/settings', [SettingsController::class, 'index']);
        $router->post('/settings', [SettingsController::class, 'update']);

        $this->menu($container);
    }

    private function menu(Container $container): void
    {
        /** @var Menu $menu */
        $menu = $container->get(Menu::class);

        $menu->add('Principal', ['label' => 'Dashboard', 'icon' => 'smart-home', 'url' => '/']);
        $menu->add('Principal', ['label' => 'Notificações', 'icon' => 'bell', 'url' => '/notifications']);
        $menu->add('Cadastros', ['label' => 'Clientes', 'icon' => 'users', 'url' => '/clients', 'roles' => ['admin', 'secretary', 'lead_mechanic']]);
        $menu->add('Cadastros', ['label' => 'Funcionários', 'icon' => 'users-group', 'url' => '/users', 'roles' => ['admin', 'secretary', 'lead_mechanic']]);
        $menu->add('Sistema', ['label' => 'Configurações', 'icon' => 'settings', 'url' => '/settings', 'roles' => ['admin']]);
        $menu->add('Sistema', ['label' => 'Auditoria', 'icon' => 'history', 'url' => '/audit', 'roles' => ['admin']]);
    }
}
