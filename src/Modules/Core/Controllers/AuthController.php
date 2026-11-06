<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Session;
use Kernel\View\ViewFactory;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    private Auth $auth;
    private Session $session;
    private ViewFactory $view;

    public function __construct(Auth $auth, Session $session, ViewFactory $view)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->view = $view;
    }

    public function showLogin(ServerRequestInterface $request)
    {
        if ($this->auth->check()) {
            return redirect('/');
        }

        return $this->view->render('core::auth/login', [
            'error' => $request->getQueryParams()['error'] ?? null,
            'csrf' => $this->session->csrfToken(),
        ]);
    }

    public function login(ServerRequestInterface $request)
    {
        $body = $request->getParsedBody();

        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return redirect('/login?error=' . urlencode('Sessão expirada. Tente novamente.'));
        }

        $email = clean($body['email'] ?? '');
        $password = (string) ($body['password'] ?? '');

        if ($this->auth->attempt($email, $password)) {
            return redirect('/');
        }

        return redirect('/login?error=' . urlencode('Email ou senha incorretos.'));
    }

    public function logout(ServerRequestInterface $request)
    {
        $this->auth->logout();
        return redirect('/login');
    }
}
