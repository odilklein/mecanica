<?php
// =============================================
// KERNEL — orquestra o ciclo de vida da aplicação
// (container, módulos e roteador). Núcleo reutilizável.
// =============================================

declare(strict_types=1);

namespace Kernel;

use Kernel\Container\Container;
use Kernel\Auth\Auth;
use Kernel\Database\Connection;
use Kernel\Http\Router;
use Kernel\Http\Session;
use Kernel\Modules\Registry;
use Kernel\View\ViewFactory;
use PDO;

class Kernel
{
    private string $basePath;
    private Container $container;
    private Router $router;
    private Registry $registry;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Prepara o container, o roteador e registra os módulos.
     */
    public function boot(): void
    {
        // Sessão precisa estar ativa antes de qualquer serviço que a use.
        $session = new Session();
        $session->start();

        $this->container = new Container();
        $this->router = new Router($this->container);

        // Serviços/instâncias compartilhadas.
        $this->container->set('basePath', $this->basePath);
        $this->container->set(Container::class, $this->container);
        $this->container->set(Router::class, $this->router);
        $this->container->set(Session::class, $session);

        // Conexão PDO sob demanda (lazy: só abre quando um módulo pedir).
        $this->container->bind(PDO::class, static fn () => Connection::fromEnv());

        // Auth e View (resolvidos via autowiring do container).
        $this->container->bind(Auth::class, fn () => new Auth($session, $this->container->get(PDO::class)));
        $this->container->bind(ViewFactory::class, fn () => new ViewFactory($this->basePath));

        // Descobre e registra os módulos (Core, Mechanic, ...).
        $this->registry = new Registry($this->container, $this->basePath);
        $this->container->set(Registry::class, $this->registry);
        $this->registry->discover();
        $this->registry->register();
    }

    /**
     * Despacha a requisição HTTP corrente.
     */
    public function run(): void
    {
        $this->router->run();
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
