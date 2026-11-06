<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Http\Controller;
use Psr\Http\Message\ServerRequestInterface;

class AuthController extends Controller
{
    public function showLogin(ServerRequestInterface $request)
    {
        if ($this->auth->check()) {
            return redirect('/');
        }

        return $this->render('core::auth/login', [
            'error' => $request->getQueryParams()['error'] ?? null,
            'title' => 'Login',
        ], 'kernel::layouts/auth');
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

        return redirect('/login?error=' . urlencode('E-mail ou senha incorretos.'));
    }

    public function logout(ServerRequestInterface $request)
    {
        $this->auth->logout();
        return redirect('/login');
    }
}
