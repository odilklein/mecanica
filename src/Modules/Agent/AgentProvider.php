<?php
// =============================================
// AGENT — assistente de IA integrado ao sistema.
//
// As ferramentas vivem aqui, no domínio PHP, e não
// em um serviço externo. O modelo apenas escolhe
// quais chamar; a autorização é sempre do sistema.
// =============================================

declare(strict_types=1);

namespace Modules\Agent;

use Kernel\Container\Container;
use Kernel\Http\Router;
use Kernel\Menu\Menu;
use Kernel\Modules\ProviderInterface;
use Modules\Agent\Controllers\AssistantController;

class AgentProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        // Página do chat.
        $router->get('/assistant', [AssistantController::class, 'index']);

        // Turno do agente com streaming (SSE).
        $router->post('/assistant/stream', [AssistantController::class, 'stream']);

        // Sessões (histórico do chat).
        $router->post('/assistant/sessions', [AssistantController::class, 'newSession']);
        $router->get('/assistant/sessions/{id}', [AssistantController::class, 'history']);
        $router->post('/assistant/sessions/{id}/archive', [AssistantController::class, 'archiveSession']);

        // Anexos (upload, download com checagem de dono e remoção).
        $router->post('/assistant/attachments', [AssistantController::class, 'uploadAttachment']);
        $router->get('/assistant/attachments/{id}', [AssistantController::class, 'downloadAttachment']);
        $router->post('/assistant/attachments/{id}/delete', [AssistantController::class, 'deleteAttachment']);

        // Preenchimento de formulário a partir de texto/voz.
        $router->post('/assistant/prefill', [AssistantController::class, 'prefill']);

        // Texto assistido: queixa/laudo e mensagem para o cliente.
        $router->post('/assistant/enhance', [AssistantController::class, 'enhance']);
        $router->post('/assistant/customer-message', [AssistantController::class, 'customerMessage']);

        // Escolha de uma oferta de peça (token assinado pelo servidor).
        $router->post('/assistant/parts/select', [AssistantController::class, 'selectPart']);

        // Propostas pendentes (aprovação humana).
        $router->get('/assistant/proposals', [AssistantController::class, 'proposals']);
        $router->post('/assistant/proposals/{id}/apply', [AssistantController::class, 'applyProposal']);
        $router->post('/assistant/proposals/{id}/reject', [AssistantController::class, 'rejectProposal']);

        $this->menu($container);
    }

    private function menu(Container $container): void
    {
        /** @var Menu $menu */
        $menu = $container->get(Menu::class);

        $menu->add('Atendimento', [
            'label' => 'Assistente',
            'icon' => 'sparkles',
            'url' => '/assistant',
            'roles' => null,
        ]);
    }
}
