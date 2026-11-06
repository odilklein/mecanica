<?php
// =============================================
// CORE — módulo base (usuários, clientes, settings,
// auditoria, notificações). Aqui registramos a rota
// raiz de "olá mundo" da F1.
// =============================================

declare(strict_types=1);

namespace Modules\Core;

use Kernel\Container\Container;
use Kernel\Http\Router;
use Kernel\Modules\ProviderInterface;
use Nyholm\Psr7\Response;

class CoreProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        $router->get('/', function () use ($container) {
            $basePath = $container->get('basePath');
            $modules = $container->get(\Kernel\Modules\Registry::class);

            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                '<h1>Mecânica — Kernel modular</h1>'
                . '<p>Módulos carregados: ' . e(implode(', ', array_keys($modules->all()))) . '</p>'
            );
        });
    }
}
