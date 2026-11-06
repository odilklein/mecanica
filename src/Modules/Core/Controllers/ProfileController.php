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

class ProfileController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function show(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }

        $stmt = $this->pdo->prepare('SELECT id, name, email, role, phone, job_title FROM users WHERE id = :id');
        $stmt->execute([':id' => $this->auth->id()]);

        return $this->render('core::profile/index', [
            'title' => 'Meus dados',
            'profile' => $stmt->fetch(),
        ]);
    }

    public function update(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/profile')) {
            return $response;
        }

        $body = $request->getParsedBody();
        $name = clean($body['name'] ?? '');
        $email = strtolower(clean($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $confirm = (string) ($body['password_confirmation'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Informe um nome e um e-mail válidos.');
            return redirect('/profile');
        }

        $dup = $this->pdo->prepare('SELECT id FROM users WHERE email = :e AND id <> :id');
        $dup->execute([':e' => $email, ':id' => $this->auth->id()]);
        if ($dup->fetchColumn()) {
            $this->session->flash('error', 'Este e-mail já está em uso por outro usuário.');
            return redirect('/profile');
        }

        if ($password !== '') {
            if (strlen($password) < 8) {
                $this->session->flash('error', 'A nova senha deve ter pelo menos 8 caracteres.');
                return redirect('/profile');
            }
            if ($password !== $confirm) {
                $this->session->flash('error', 'A confirmação da senha não confere.');
                return redirect('/profile');
            }
        }

        if ($password !== '') {
            $this->pdo->prepare('UPDATE users SET name = :n, email = :e, password = :p WHERE id = :id')
                ->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $this->auth->id()]);
        } else {
            $this->pdo->prepare('UPDATE users SET name = :n, email = :e WHERE id = :id')
                ->execute([':n' => $name, ':e' => $email, ':id' => $this->auth->id()]);
        }

        $this->session->set('auth_user_name', $name);
        record_audit($this->pdo, $this->auth->id(), 'users', $this->auth->id(), 'update', ['self' => true]);
        $this->session->flash('success', 'Dados atualizados com sucesso.');
        return redirect('/profile');
    }
}
