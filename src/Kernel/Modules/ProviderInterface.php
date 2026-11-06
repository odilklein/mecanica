<?php
// =============================================
// PROVIDER INTERFACE — contrato de um módulo.
// =============================================

declare(strict_types=1);

namespace Kernel\Modules;

use Kernel\Container\Container;
use Kernel\Http\Router;

interface ProviderInterface
{
    /**
     * Registra rotas, serviços e hooks do módulo no kernel.
     */
    public function register(Router $router, Container $container): void;
}
