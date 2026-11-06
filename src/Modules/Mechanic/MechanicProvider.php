<?php
// =============================================
// MECHANIC — vertical de negócio da oficina
// (veículos, OS, peças, orçamentos, relatórios).
// Depende do módulo Core.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic;

use Kernel\Container\Container;
use Kernel\Http\Router;
use Kernel\Modules\ProviderInterface;
use Nyholm\Psr7\Response;

class MechanicProvider implements ProviderInterface
{
    public function register(Router $router, Container $container): void
    {
        $router->get('/mechanic', function () {
            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                '<h1>Oficina</h1><p>Módulo <strong>Mechanic</strong> registrado.</p>'
            );
        });
    }
}
