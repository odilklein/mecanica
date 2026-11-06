<?php
// =============================================
// CORE — módulo base (usuários, clientes, settings,
// auditoria, notificações). Registra autenticação e
// o dashboard.
// =============================================

declare(strict_types=1);

namespace Modules\Core;

use Kernel\Container\Container;
use Kernel\Http\Router;
use Kernel\Modules\ProviderInterface;
use Modules\Core\Controllers\AuthController;
use Modules\Core\Controllers\ClientController;
use Modules\Core\Controllers\DashboardController;

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

        // Clientes (CRUD base).
        $router->get('/clients', [ClientController::class, 'index']);
        $router->get('/clients/create', [ClientController::class, 'create']);
        $router->post('/clients', [ClientController::class, 'store']);
        $router->get('/clients/{id}/edit', [ClientController::class, 'edit']);
        $router->post('/clients/{id}', [ClientController::class, 'update']);
        $router->post('/clients/{id}/delete', [ClientController::class, 'destroy']);
    }
}
