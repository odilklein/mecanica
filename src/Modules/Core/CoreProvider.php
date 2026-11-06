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
    }
}
