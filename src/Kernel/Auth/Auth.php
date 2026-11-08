<?php
// =============================================
// AUTH — autenticação e autorização (RBAC).
// =============================================

declare(strict_types=1);

namespace Kernel\Auth;

use Kernel\Http\Session;
use PDO;

class Auth
{
    private Session $session;
    private PDO $pdo;

    public function __construct(Session $session, PDO $pdo)
    {
        $this->session = $session;
        $this->pdo = $pdo;
    }

    /**
     * Tenta autenticar com email/senha. Retorna true em sucesso.
     */
    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $this->session->regenerate();
            $this->session->set('auth_user_id', (int) $user['id']);
            $this->session->set('auth_user_name', (string) $user['name']);
            $this->session->set('auth_user_email', (string) $user['email']);
            $this->session->set('auth_user_role', (string) $user['role']);
            return true;
        }

        return false;
    }

    public function check(): bool
    {
        return $this->session->has('auth_user_id');
    }

    /** @return array{id: int, name: string, email: string, role: string}|null */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        // Sessões criadas antes da inclusão do e-mail buscam o dado uma vez
        // e passam a carregá-lo na sessão (evita consulta a cada request).
        $email = $this->session->get('auth_user_email');
        if ($email === null) {
            $email = '';
            try {
                $stmt = $this->pdo->prepare('SELECT email FROM users WHERE id = :id');
                $stmt->execute([':id' => (int) $this->session->get('auth_user_id')]);
                $email = (string) ($stmt->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                // Sem banco disponível: o painel segue sem e-mail.
            }
            $this->session->set('auth_user_email', $email);
        }

        return [
            'id' => (int) $this->session->get('auth_user_id'),
            'name' => (string) $this->session->get('auth_user_name'),
            'email' => (string) $email,
            'role' => (string) $this->session->get('auth_user_role'),
        ];
    }

    public function id(): ?int
    {
        return $this->check() ? (int) $this->session->get('auth_user_id') : null;
    }

    public function role(): ?string
    {
        return $this->check() ? (string) $this->session->get('auth_user_role') : null;
    }

    /**
     * Verifica se o usuário tem um dos papéis informados.
     */
    public function hasRole(array|string $roles): bool
    {
        return in_array($this->role(), (array) $roles, true);
    }

    /**
     * Aborta com 403 se o usuário não tiver o papel exigido.
     */
    public function requireRole(array|string $roles): void
    {
        if (!$this->hasRole($roles)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
    }

    public function logout(): void
    {
        $this->session->remove('auth_user_id');
        $this->session->remove('auth_user_name');
        $this->session->remove('auth_user_email');
        $this->session->remove('auth_user_role');
    }
}
