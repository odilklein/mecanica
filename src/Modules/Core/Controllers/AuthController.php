<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

require_once dirname(__DIR__, 4) . '/includes/login_rate_limit.php';
require_once dirname(__DIR__, 4) . '/includes/turnstile.php';

class AuthController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function showLogin(ServerRequestInterface $request)
    {
        if ($this->auth->check()) {
            return redirect('/');
        }

        return $this->render('core::auth/login', [
            'error' => $request->getQueryParams()['error'] ?? null,
            'title' => 'Login',
            'turnstileEnabled' => turnstile_habilitado(),
            'turnstileSiteKey' => turnstile_site_key(),
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
        $ip = login_rate_limit_ip();

        // Rate limit por e-mail + IP (força-bruta).
        $limite = login_rate_limit_verificar($this->pdo, $email, $ip);
        if ($limite['bloqueado']) {
            return redirect('/login?error=' . urlencode(
                'Muitas tentativas de login. Tente novamente em ' . (int) $limite['minutos_restantes'] . ' min.'
            ));
        }

        // Anti-bot (Cloudflare Turnstile). Sem as chaves configuradas,
        // a verificação é ignorada e o login segue normalmente.
        $turnstile = turnstile_verificar($body['cf-turnstile-response'] ?? null, 'login');
        if (!$turnstile['ok']) {
            login_rate_limit_registrar_falha($this->pdo, $email, $ip);
            error_log('Turnstile (login) recusado: ' . $turnstile['motivo']
                . (isset($turnstile['detalhe']) ? ' [' . $turnstile['detalhe'] . ']' : ''));
            $msg = ($turnstile['codigo'] ?? '') === 'token_ausente'
                ? 'Confirme a verificação de segurança do formulário antes de entrar.'
                : 'Falha na verificação de segurança. Tente novamente.';
            return redirect('/login?error=' . urlencode($msg));
        }

        if ($this->auth->attempt($email, $password)) {
            login_rate_limit_limpar($this->pdo, $email, $ip);
            record_audit($this->pdo, (int) $this->auth->id(), 'auth', (int) $this->auth->id(), 'login');
            return redirect('/');
        }

        login_rate_limit_registrar_falha($this->pdo, $email, $ip);
        return redirect('/login?error=' . urlencode('E-mail ou senha incorretos.'));
    }

    public function logout(ServerRequestInterface $request)
    {
        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return redirect('/');
        }

        record_audit($this->pdo, (int) $this->auth->id(), 'auth', (int) $this->auth->id(), 'logout');
        $this->auth->logout();
        return redirect('/login');
    }
}
