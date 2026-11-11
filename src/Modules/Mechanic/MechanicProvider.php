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
use Modules\Mechanic\Controllers\SupplierController;
use Modules\Mechanic\Controllers\PartCategoryController;

class MechanicProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        // Veículos (CRUD genérico + FIPE).
        crud_routes($router, '/vehicles', VehicleController::class);

        // Peças — CRM completo (catálogo, categorias, estoque e preços).
        $router->get('/parts/datatable', [PartController::class, 'datatable']);
        $router->get('/parts/export', [PartController::class, 'export']);
        $router->get('/parts/create', [PartController::class, 'create']);
        $router->post('/parts/bulk-delete', [PartController::class, 'bulkDelete']);
        $router->post('/parts/sync', [PartController::class, 'sync']);
        $router->get('/parts', [PartController::class, 'index']);
        $router->post('/parts', [PartController::class, 'store']);

        // Categorias (antes das rotas /parts/{id}).
        $router->get('/parts/categories', [PartCategoryController::class, 'index']);
        $router->post('/parts/categories', [PartCategoryController::class, 'store']);
        $router->post('/parts/categories/{id}/delete', [PartCategoryController::class, 'destroy']);
        $router->post('/parts/categories/{id}', [PartCategoryController::class, 'update']);

        $router->get('/parts/{id}/edit', [PartController::class, 'edit']);
        $router->post('/parts/{id}/delete', [PartController::class, 'destroy']);
        $router->get('/parts/{id}', [PartController::class, 'show']);
        $router->post('/parts/{id}', [PartController::class, 'update']);

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
        $router->post('/service-orders/{id}/internal-notes', [ServiceOrderController::class, 'updateInternalNotes']);
        $router->post('/service-orders/{id}/responsible/{userId}/delete', [ServiceOrderController::class, 'removeResponsible']);
        $router->post('/service-orders/{id}/responsible', [ServiceOrderController::class, 'assignResponsible']);
        $router->post('/service-orders/{id}/task-status', [ServiceOrderController::class, 'updateTaskStatus']);
        $router->post('/service-orders/{id}/task-update', [ServiceOrderController::class, 'updateTask']);
        $router->post('/service-orders/{id}/tasks', [ServiceOrderController::class, 'addTask']);
        $router->post('/service-orders/{id}/delivery', [ServiceOrderController::class, 'registerDelivery']);
        $router->post('/service-orders/{id}/delete', [ServiceOrderController::class, 'destroy']);
        $router->get('/service-orders/{id}/ticket', [TicketController::class, 'forOrder']);
        $router->get('/service-orders/{id}', [ServiceOrderController::class, 'show']);
        $router->post('/service-orders/{id}', [ServiceOrderController::class, 'update']);

        // Pedidos de peças (CRUD genérico).
        crud_routes($router, '/part-orders', PartOrderController::class);
        $router->get('/part-orders/{id}', [PartOrderController::class, 'show']);

        // Fornecedores de peças.
        crud_routes($router, '/suppliers', SupplierController::class);

        // Orçamentos (específicas antes da genérica).
        $router->get('/budgets/datatable', [BudgetController::class, 'datatable']);
        $router->get('/budgets', [BudgetController::class, 'index']);
        $router->get('/budgets/create', [BudgetController::class, 'create']);
        $router->post('/budgets/save', [BudgetController::class, 'save']);
        $router->get('/budgets/{id}/edit', [BudgetController::class, 'edit']);
        $router->get('/budgets/{id}/print', [BudgetController::class, 'print']);
        $router->post('/budgets/{id}/delete', [BudgetController::class, 'destroy']);
        $router->post('/budgets/{id}/status', [BudgetController::class, 'updateStatus']);
        $router->get('/budgets/{id}', [BudgetController::class, 'show']);

        // Tickets (central de atendimento).
        $router->get('/tickets/list', [TicketController::class, 'list']);
        $router->get('/tickets', [TicketController::class, 'index']);
        $router->post('/tickets', [TicketController::class, 'store']);
        $router->post('/tickets/{id}/messages', [TicketController::class, 'sendMessage']);
        $router->post('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
        $router->post('/tickets/{id}/assign', [TicketController::class, 'assign']);
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
            'children' => [
                ['label' => 'Catálogo de peças', 'url' => '/parts'],
                ['label' => 'Nova peça', 'url' => '/parts/create'],
                ['label' => 'Categorias', 'url' => '/parts/categories'],
                ['label' => 'Fornecedores', 'url' => '/suppliers'],
            ],
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
            'open' => true,
            'menu_key' => 'tickets',
            'children' => $this->ticketMenu($container),
        ]);
    }

    /**
     * Submenu da aba Tickets: até 6 conversas em aberto visíveis para o
     * usuário atual, no estilo de histórico de chat. Quando há mais,
     * exibe o atalho "Ver todos os tickets".
     *
     * @return array<int, array<string, mixed>>
     */
    private function ticketMenu(Container $container): array
    {
        try {
            /** @var Auth $auth */
            $auth = $container->get(Auth::class);
            if (!$auth->check()) {
                return [];
            }

            $service = new TicketService($container->get(PDO::class));
            // Busca 7 para saber se existe mais de 6 sem uma consulta extra.
            $tickets = $service->visibleTickets(
                (int) $auth->id(),
                $auth->hasRole(['admin']),
                7,
                'open'
            );
        } catch (\Throwable $e) {
            return [];
        }

        $children = [];
        foreach (array_slice($tickets, 0, 6) as $ticket) {
            $subject = trim((string) ($ticket['subject'] ?? ''));
            $meta = '';
            if (preg_match('/^OS\s*#(\d+)\s*[-–—]\s*(.+)$/u', $subject, $m)) {
                $subject = trim($m[2]);
                $meta = '#' . $m[1];
            }
            if ($subject === '') {
                $subject = 'Ticket #' . (int) $ticket['id'];
            }

            $children[] = [
                'label' => mb_strimwidth($subject, 0, 30, '…'),
                'url' => '/tickets?ticket=' . (int) $ticket['id'],
                'class' => 'menu-ticket',
                'meta' => $meta,
                'unread' => (int) ($ticket['unread'] ?? 0),
            ];
        }

        if (count($tickets) > 6) {
            $children[] = [
                'label' => 'Ver todos os tickets',
                'url' => '/tickets',
                'class' => 'menu-ticket-all',
            ];
        }

        return $children;
    }
}
