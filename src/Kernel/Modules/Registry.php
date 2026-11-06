<?php
// =============================================
// MODULE REGISTRY — descobre os módulos em src/Modules
// a partir do module.json, resolve dependências e
// registra os providers na ordem correta.
// =============================================

declare(strict_types=1);

namespace Kernel\Modules;

use Kernel\Container\Container;
use Kernel\Http\Router;

class Registry
{
    private Container $container;
    private string $basePath;

    /** @var array<string, array{name: string, version: string, dependencies: string[], path: string}> */
    private array $modules = [];

    public function __construct(Container $container, string $basePath)
    {
        $this->container = $container;
        $this->basePath = $basePath;
    }

    /**
     * Varre src/Modules/* e lê o module.json de cada pasta.
     */
    public function discover(): void
    {
        $dir = $this->basePath . '/src/Modules';
        $folders = glob($dir . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($folders as $moduleDir) {
            $manifestFile = $moduleDir . '/module.json';
            if (!is_file($manifestFile)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                continue;
            }

            $name = (string) ($manifest['name'] ?? strtolower(basename($moduleDir)));
            $this->modules[$name] = [
                'name' => $name,
                'version' => (string) ($manifest['version'] ?? '1.0.0'),
                'dependencies' => (array) ($manifest['dependencies'] ?? []),
                'path' => $moduleDir,
            ];
        }
    }

    /**
     * Instancia e registra os providers na ordem de dependência.
     */
    public function register(): void
    {
        $router = $this->container->get(Router::class);

        foreach ($this->sorted() as $name) {
            $providerClass = $this->providerClass($name);

            if (!class_exists($providerClass)) {
                continue;
            }

            $provider = new $providerClass();
            if ($provider instanceof ProviderInterface) {
                $provider->register($router, $this->container);
            }
        }
    }

    /** @return array<string, array{name: string, version: string, dependencies: string[], path: string}> */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Ordenação topológica simples (dependências antes dos dependentes).
     *
     * @return string[]
     */
    private function sorted(): array
    {
        $resolved = [];

        $resolve = function (string $name) use (&$resolve, &$resolved): void {
            if (isset($resolved[$name])) {
                return;
            }
            foreach ($this->modules[$name]['dependencies'] ?? [] as $dep) {
                if (isset($this->modules[$dep])) {
                    $resolve($dep);
                }
            }
            $resolved[$name] = true;
        };

        foreach (array_keys($this->modules) as $name) {
            $resolve($name);
        }

        return array_keys($resolved);
    }

    /**
     * Converte o nome do módulo na classe do provider:
     * "core"     -> Modules\Core\CoreProvider
     * "mechanic" -> Modules\Mechanic\MechanicProvider
     */
    private function providerClass(string $name): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        return 'Modules\\' . $studly . '\\' . $studly . 'Provider';
    }
}
