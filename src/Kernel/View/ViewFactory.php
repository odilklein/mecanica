<?php
// =============================================
// VIEW FACTORY — renderizador de templates (Plates).
// Registra a pasta de views do kernel e de cada módulo.
// O layout recebe o conteúdo da view + os mesmos dados
// (usuário, CSRF, menu, etc.) via renderWithLayout().
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

        // Views do kernel (layouts, crud genérico).
        $this->engine->addFolder('kernel', $basePath . '/src/Kernel/views');

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

    /**
     * Renderiza a view e a envolve no layout, repassando os mesmos dados.
     * O conteúdo da view fica disponível no layout como $content.
     */
    public function renderWithLayout(string $layout, string $template, array $data = []): string
    {
        $content = $this->engine->render($template, $data);

        return $this->engine->render($layout, array_merge($data, ['content' => $content]));
    }
}
