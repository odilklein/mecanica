<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Core\Support\LoginRateLimit;
use Modules\Core\Support\Turnstile;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

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
            'turnstileEnabled' => Turnstile::enabled(),
            'turnstileSiteKey' => Turnstile::siteKey(),
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
        $ip = LoginRateLimit::ip();

        // Rate limit por e-mail + IP (força-bruta).
        $limite = LoginRateLimit::check($this->pdo, $email, $ip);
        if ($limite['bloqueado']) {
            return redirect('/login?error=' . urlencode(
                'Muitas tentativas de login. Tente novamente em ' . (int) $limite['minutos_restantes'] . ' min.'
            ));
        }

        // Anti-bot (Cloudflare Turnstile). Sem as chaves configuradas,
        // a verificação é ignorada e o login segue normalmente.
        $turnstile = Turnstile::verify($body['cf-turnstile-response'] ?? null, 'login');
        if (!$turnstile['ok']) {
            LoginRateLimit::registerFailure($this->pdo, $email, $ip);
            error_log('Turnstile (login) recusado: ' . $turnstile['motivo']
                . (isset($turnstile['detalhe']) ? ' [' . $turnstile['detalhe'] . ']' : ''));
            $msg = ($turnstile['codigo'] ?? '') === 'token_ausente'
                ? 'Confirme a verificação de segurança do formulário antes de entrar.'
                : 'Falha na verificação de segurança. Tente novamente.';
            return redirect('/login?error=' . urlencode($msg));
        }

        if ($this->auth->attempt($email, $password)) {
            LoginRateLimit::clear($this->pdo, $email, $ip);
            record_audit($this->pdo, (int) $this->auth->id(), 'auth', (int) $this->auth->id(), 'login');
            return redirect('/');
        }

        LoginRateLimit::registerFailure($this->pdo, $email, $ip);
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
