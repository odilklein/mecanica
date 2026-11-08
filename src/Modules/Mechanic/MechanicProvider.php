<?php
// =============================================
// MECHANIC — vertical de negócio da oficina
// (veículos, OS, peças, orçamentos). Depende do Core.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic;

use Kernel\Container\Container;
use Kernel\Auth\Auth;
use Kernel\Http\Router;
use Kernel\Menu\Menu;
use Kernel\Modules\ProviderInterface;
use Modules\Mechanic\Controllers\FipeController;
use Modules\Mechanic\Controllers\BudgetController;
use Modules\Mechanic\Controllers\PartOrderController;
use Modules\Mechanic\Controllers\ReportController;
use Modules\Mechanic\Controllers\ServiceOrderController;
use Modules\Mechanic\Controllers\TaskController;
use Modules\Mechanic\Controllers\TicketController;
use Modules\Mechanic\Services\TicketService;
use PDO;
use Modules\Mechanic\Controllers\VehicleController;
use Modules\Mechanic\Controllers\PartsController;
use Modules\Mechanic\Controllers\PartController;

class MechanicProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        // Veículos (CRUD genérico + FIPE).
        crud_routes($router, '/vehicles', VehicleController::class);

        // Peças (CRUD simples + autocomplete).
        crud_routes($router, '/parts', PartController::class);

        // API do catálogo FIPE (selects em cascata).
        $router->get('/api/fipe/models', [FipeController::class, 'models']);
        $router->get('/api/fipe/years', [FipeController::class, 'years']);
        $router->get('/api/fipe/price', [FipeController::class, 'price']);

        // API do catálogo de peças (Canal da Peça + cache local).
        $router->get('/api/parts/autocomplete', [PartsController::class, 'autocomplete']);
        $router->get('/api/parts/search', [PartsController::class, 'search']);
        $router->get('/api/parts/vehicles', [PartsController::class, 'vehicles']);

        // Ordens de serviço (rotas específicas antes da genérica; bramus casa {id} com "/").
        $router->get('/service-orders/datatable', [ServiceOrderController::class, 'datatable']);
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
        $router->get('/service-orders/{id}/ticket', [TicketController::class, 'forOrder']);
        $router->get('/service-orders/{id}', [ServiceOrderController::class, 'show']);
        $router->post('/service-orders/{id}', [ServiceOrderController::class, 'update']);

        // Pedidos de peças (CRUD genérico).
        crud_routes($router, '/part-orders', PartOrderController::class);

        // Orçamentos (específicas antes da genérica).
        $router->get('/budgets/datatable', [BudgetController::class, 'datatable']);
        $router->get('/budgets', [BudgetController::class, 'index']);
        $router->get('/budgets/create', [BudgetController::class, 'create']);
        $router->post('/budgets/save', [BudgetController::class, 'save']);
        $router->get('/budgets/{id}/edit', [BudgetController::class, 'edit']);
        $router->get('/budgets/{id}/print', [BudgetController::class, 'print']);
        $router->post('/budgets/{id}/delete', [BudgetController::class, 'destroy']);
        $router->get('/budgets/{id}', [BudgetController::class, 'show']);

        // Tickets (central de atendimento).
        $router->get('/tickets/list', [TicketController::class, 'list']);
        $router->get('/tickets', [TicketController::class, 'index']);
        $router->post('/tickets', [TicketController::class, 'store']);
        $router->post('/tickets/{id}/messages', [TicketController::class, 'sendMessage']);
        $router->post('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
        $router->get('/tickets/{id}', [TicketController::class, 'show']);

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
        $menu->add('Cadastros', [
            'label' => 'Peças',
            'icon' => 'package',
            'url' => '/parts',
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
        $menu->add('Atendimento', [
            'label' => 'Tickets',
            'icon' => 'messages',
            'url' => '/tickets',
            'roles' => null,
            'children' => $this->ticketMenu($container),
        ]);
    }

    /**
     * Submenu da aba Tickets: atalhos fixos + conversas em aberto
     * visíveis para o usuário atual.
     *
     * @return array<int, array<string, string>>
     */
    private function ticketMenu(Container $container): array
    {
        $children = [
            ['label' => 'Todos os tickets', 'url' => '/tickets'],
            ['label' => 'Não lidos', 'url' => '/tickets?scope=unread'],
            ['label' => 'Encerrados', 'url' => '/tickets?status=closed'],
        ];

        try {
            /** @var Auth $auth */
            $auth = $container->get(Auth::class);
            if (!$auth->check()) {
                return $children;
            }

            $service = new TicketService($container->get(PDO::class));
            $tickets = $service->visibleTickets(
                (int) $auth->id(),
                $auth->hasRole(['admin']),
                4,
                'open'
            );

            foreach ($tickets as $ticket) {
                $label = mb_strimwidth((string) $ticket['subject'], 0, 34, '…');
                if ((int) ($ticket['unread'] ?? 0) > 0) {
                    $label .= ' •';
                }
                $children[] = [
                    'label' => $label,
                    'url' => '/tickets?ticket=' . (int) $ticket['id'],
                ];
            }
        } catch (\Throwable $e) {
            // Sem banco ou tabela ainda: mantém apenas os atalhos.
        }

        return $children;
    }
}
