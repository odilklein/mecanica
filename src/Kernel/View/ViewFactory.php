<?php
// =============================================
// VIEW FACTORY — renderizador de templates (Plates).
// Registra a pasta de views de cada módulo.
// =============================================

declare(strict_types=1);

namespace Kernel\View;

use League\Plates\Engine;

class ViewFactory
{
    private Engine $engine;

    public function __construct(string $basePath)
    {
        $this->engine = new Engine();

        // Cada módulo expõe suas views como "nome-do-modulo::template".
        foreach (glob($basePath . '/src/Modules/*/views', GLOB_ONLYDIR) ?: [] as $dir) {
            $module = strtolower((string) basename(dirname($dir)));
            $this->engine->addFolder($module, $dir);
        }
    }

    public function render(string $template, array $data = []): string
    {
        return $this->engine->render($template, $data);
    }
}
