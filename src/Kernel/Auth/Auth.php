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
    /** Tempo máximo de inatividade (minutos) quando SESSION_IDLE_TIMEOUT não está definido. */
    private const DEFAULT_IDLE_MINUTES = 120;

    private Session $session;
    private PDO $pdo;

    /** Evita mais de uma revalidação no banco por request. */
    private bool $validated = false;

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
            // Atualiza o hash se o algoritmo/custo padrão mudou.
            if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
                $this->pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                    ->execute([
                        ':p' => password_hash($password, PASSWORD_DEFAULT),
                        ':id' => (int) $user['id'],
                    ]);
            }

            $this->session->regenerate();
            $this->session->set('auth_user_id', (int) $user['id']);
            $this->session->set('auth_user_name', (string) $user['name']);
            $this->session->set('auth_user_email', (string) $user['email']);
            $this->session->set('auth_user_role', (string) $user['role']);
            $this->session->set('auth_last_seen', time());
            $this->validated = true;
            return true;
        }

        return false;
    }

    /**
     * Sessão válida? Aplica timeout de inatividade e revalida o usuário
     * no banco (is_active e papel) uma vez por request.
     */
    public function check(): bool
    {
        if (!$this->session->has('auth_user_id')) {
            return false;
        }

        $idleMinutes = (int) env('SESSION_IDLE_TIMEOUT', (string) self::DEFAULT_IDLE_MINUTES);
        $lastSeen = $this->session->get('auth_last_seen');

        if ($idleMinutes > 0 && $lastSeen !== null && (time() - (int) $lastSeen) > ($idleMinutes * 60)) {
            $this->logout();
            return false;
        }

        $this->session->set('auth_last_seen', time());

        if ($this->validated) {
            return true;
        }
        $this->validated = true;

        try {
            $stmt = $this->pdo->prepare('SELECT name, email, role, is_active FROM users WHERE id = :id');
            $stmt->execute([':id' => (int) $this->session->get('auth_user_id')]);
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            // Sem banco disponível: mantém a sessão para não derrubar o painel.
            return true;
        }

        if (!$user || (int) $user['is_active'] !== 1) {
            $this->logout();
            return false;
        }

        // Reflete no ato rebaixamento/promoção e edição de perfil.
        if ((string) $user['role'] !== (string) $this->session->get('auth_user_role')) {
            $this->session->set('auth_user_role', (string) $user['role']);
        }
        if ((string) $user['name'] !== (string) $this->session->get('auth_user_name')) {
            $this->session->set('auth_user_name', (string) $user['name']);
        }
        if ((string) $user['email'] !== (string) $this->session->get('auth_user_email')) {
            $this->session->set('auth_user_email', (string) $user['email']);
        }

        return true;
    }

    /** @return array{id: int, name: string, email: string, role: string}|null */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return [
            'id' => (int) $this->session->get('auth_user_id'),
            'name' => (string) $this->session->get('auth_user_name'),
            'email' => (string) $this->session->get('auth_user_email'),
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

    /**
     * Encerra a sessão por completo (dados + cookie + ID).
     */
    public function logout(): void
    {
        $this->validated = false;
        $this->session->destroy();
    }
}
