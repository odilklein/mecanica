<?php
// =============================================
// CONTROLLER BASE — serviços comuns, render de
// views (com menu/usuário/CSRF) e guards (auth,
// papel e CSRF).
// =============================================

declare(strict_types=1);

namespace Kernel\Http;

use Kernel\Auth\Auth;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
    protected Auth $auth;
    protected Session $session;
    protected ViewFactory $view;
    protected Menu $menu;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->view = $view;
        $this->menu = $menu;
    }

    /**
     * Renderiza uma view já com os dados compartilhados (usuário, CSRF, menu)
     * e a envolve no layout informado.
     */
    protected function render(string $template, array $data = [], string $layout = 'kernel::layouts/app'): string
    {
        return $this->view->renderWithLayout($layout, $template, array_merge([
            'user' => $this->auth->user(),
            'csrf' => $this->session->csrfToken(),
            'menu' => $this->menu->items($this->auth),
        ], $data));
    }

    /** Redireciona para o login se não autenticado. */
    protected function guardAuth(): ?ResponseInterface
    {
        return $this->auth->check() ? null : redirect('/login');
    }

    /** Exige um dos papéis; senão volta ao dashboard com aviso. */
    protected function guardRoles(array $roles): ?ResponseInterface
    {
        if (!$this->auth->check()) {
            return redirect('/login');
        }
        if ($roles && !$this->auth->hasRole($roles)) {
            $this->session->flash('error', 'Acesso não autorizado.');
            return redirect('/');
        }
        return null;
    }

    /** Valida o token CSRF de um POST. */
    protected function guardCsrf(ServerRequestInterface $request, string $fallback = '/'): ?ResponseInterface
    {
        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            $this->session->flash('error', 'Sessão expirada. Tente novamente.');
            return redirect($fallback);
        }
        return null;
    }
}
