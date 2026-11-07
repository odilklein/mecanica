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
use Modules\Mechanic\Controllers\BudgetController;
use Modules\Mechanic\Controllers\PartOrderController;
use Modules\Mechanic\Controllers\ReportController;
use Modules\Mechanic\Controllers\ServiceOrderController;
use Modules\Mechanic\Controllers\TaskController;
use Modules\Mechanic\Controllers\VehicleController;

class MechanicProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        // Veículos (CRUD genérico + FIPE).
        crud_routes($router, '/vehicles', VehicleController::class);

        // API do catálogo FIPE (selects em cascata).
        $router->get('/api/fipe/models', [FipeController::class, 'models']);
        $router->get('/api/fipe/years', [FipeController::class, 'years']);
        $router->get('/api/fipe/price', [FipeController::class, 'price']);

        // Ordens de serviço (rotas específicas antes da genérica; bramus casa {id} com "/").
        $router->get('/service-orders', [ServiceOrderController::class, 'index']);
        $router->get('/service-orders/create', [ServiceOrderController::class, 'create']);
        $router->post('/service-orders', [ServiceOrderController::class, 'store']);
        $router->get('/service-orders/{id}/edit', [ServiceOrderController::class, 'edit']);
        $router->post('/service-orders/{id}/status', [ServiceOrderController::class, 'updateStatus']);
        $router->post('/service-orders/{id}/diagnosis', [ServiceOrderController::class, 'updateDiagnosis']);
        $router->post('/service-orders/{id}/responsible/{userId}/delete', [ServiceOrderController::class, 'removeResponsible']);
        $router->post('/service-orders/{id}/responsible', [ServiceOrderController::class, 'assignResponsible']);
        $router->post('/service-orders/{id}/task-status', [ServiceOrderController::class, 'updateTaskStatus']);
        $router->post('/service-orders/{id}/tasks', [ServiceOrderController::class, 'addTask']);
        $router->post('/service-orders/{id}/delivery', [ServiceOrderController::class, 'registerDelivery']);
        $router->post('/service-orders/{id}/delete', [ServiceOrderController::class, 'destroy']);
        $router->get('/service-orders/{id}', [ServiceOrderController::class, 'show']);
        $router->post('/service-orders/{id}', [ServiceOrderController::class, 'update']);

        // Pedidos de peças (CRUD genérico).
        crud_routes($router, '/part-orders', PartOrderController::class);

        // Orçamentos (específicas antes da genérica).
        $router->get('/budgets', [BudgetController::class, 'index']);
        $router->get('/budgets/create', [BudgetController::class, 'create']);
        $router->post('/budgets', [BudgetController::class, 'store']);
        $router->get('/budgets/{id}/edit', [BudgetController::class, 'edit']);
        $router->post('/budgets/{id}/items', [BudgetController::class, 'addItem']);
        $router->post('/budgets/{id}/delete', [BudgetController::class, 'destroy']);
        $router->get('/budgets/{id}', [BudgetController::class, 'show']);
        $router->post('/budgets/{id}', [BudgetController::class, 'update']);
        $router->post('/budget-items/{id}/delete', [BudgetController::class, 'removeItem']);

        // Relatórios.
        $router->get('/reports', [ReportController::class, 'index']);

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
        $menu->add('Operações', [
            'label' => 'Ordens de serviço',
            'icon' => 'clipboard-list',
            'url' => '/service-orders',
            'roles' => null,
        ]);
        $menu->add('Operações', [
            'label' => 'Pedidos de peças',
            'icon' => 'truck',
            'url' => '/part-orders',
            'roles' => null,
        ]);
        $menu->add('Documentos', [
            'label' => 'Orçamentos',
            'icon' => 'file-text',
            'url' => '/budgets',
            'roles' => ['admin', 'secretary', 'lead_mechanic'],
        ]);
        $menu->add('Documentos', [
            'label' => 'Relatórios',
            'icon' => 'chart-line',
            'url' => '/reports',
            'roles' => ['admin', 'secretary', 'lead_mechanic'],
        ]);
    }
}
