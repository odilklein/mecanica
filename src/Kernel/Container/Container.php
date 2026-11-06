<?php
// =============================================
// CONTAINER — wrapper PSR-11 sobre league/container.
// =============================================

declare(strict_types=1);

namespace Kernel\Container;

use League\Container\Container as LeagueContainer;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private LeagueContainer $container;

    public function __construct()
    {
        $this->container = new LeagueContainer();
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Registra um valor já instanciado (compartilhado).
     */
    public function set(string $id, mixed $value): void
    {
        $this->container->addShared($id, static fn () => $value);
    }

    /**
     * Registra uma factory (closure) resolvida sob demanda.
     */
    public function bind(string $id, callable $factory): void
    {
        $this->container->add($id, $factory);
    }
}
