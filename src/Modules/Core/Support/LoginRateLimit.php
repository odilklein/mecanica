<?php
declare(strict_types=1);

namespace Modules\Core\Support;

use PDO;

/**
 * Rate limit de login por e-mail + IP, com limites adicionais por IP e por
 * conta. Conta falhas na janela de tempo (tabela login_tentativas) e bloqueia
 * ao atingir qualquer um dos limites.
 *
 * Configurável por:
 *   LOGIN_MAX_TENTATIVAS (par e-mail+IP, padrão 5)
 *   LOGIN_MAX_IP         (mesmo IP, qualquer e-mail, padrão 20)
 *   LOGIN_MAX_EMAIL      (mesma conta, qualquer IP, padrão 10)
 *   LOGIN_JANELA_MINUTOS (janela, padrão 15)
 */
class LoginRateLimit
{
    public static function max(): int
    {
        $value = (int) env('LOGIN_MAX_TENTATIVAS', '5');
        return $value > 0 ? $value : 5;
    }

    /** Limite de falhas por IP (qualquer conta) — barra password spraying. */
    public static function maxIp(): int
    {
        $value = (int) env('LOGIN_MAX_IP', '20');
        return $value > 0 ? $value : 20;
    }

    /** Limite de falhas por conta (qualquer IP) — barra ataque distribuído. */
    public static function maxEmail(): int
    {
        $value = (int) env('LOGIN_MAX_EMAIL', '10');
        return $value > 0 ? $value : 10;
    }

    public static function windowMinutes(): int
    {
        $value = (int) env('LOGIN_JANELA_MINUTOS', '15');
        return $value > 0 ? $value : 15;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * @return array{bloqueado: bool, tentativas: int, max: int, segundos_restantes: int, minutos_restantes: int}
     */
    public static function check(PDO $pdo, string $email, string $ip): array
    {
        $max = self::max();
        $maxIp = self::maxIp();
        $maxEmail = self::maxEmail();
        $window = self::windowMinutes();

        // Limpa registros antigos fora da janela.
        $pdo->prepare('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL ' . $window . ' MINUTE)')
            ->execute();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total,
                    MAX(criado_em) AS ultima,
                    SUM(CASE WHEN ip = :ip THEN 1 ELSE 0 END) AS total_ip,
                    SUM(CASE WHEN email = :email THEN 1 ELSE 0 END) AS total_email
               FROM login_tentativas
              WHERE criado_em >= (NOW() - INTERVAL ' . $window . ' MINUTE)'
        );
        $stmt->execute([':email' => $email, ':ip' => $ip]);
        $row = $stmt->fetch();

        $total = (int) ($row['total'] ?? 0);
        $totalIp = (int) ($row['total_ip'] ?? 0);
        $totalEmail = (int) ($row['total_email'] ?? 0);

        $blocked = $total >= $max || $totalIp >= $maxIp || $totalEmail >= $maxEmail;

        $seconds = 0;
        if ($blocked && !empty($row['ultima'])) {
            $seconds = max(0, $window * 60 - (time() - strtotime((string) $row['ultima'])));
        }

        return [
            'bloqueado' => $blocked,
            'tentativas' => $total,
            'max' => $max,
            'segundos_restantes' => $seconds,
            'minutos_restantes' => (int) ceil($seconds / 60),
        ];
    }

    /** Registra uma tentativa com falha e aplica um backoff crescente. */
    public static function registerFailure(PDO $pdo, string $email, string $ip): void
    {
        $pdo->prepare('INSERT INTO login_tentativas (email, ip) VALUES (:email, :ip)')
            ->execute([':email' => $email, ':ip' => $ip]);

        // Backoff suave: atraso crescente por falha (máx ~2.5s).
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_tentativas WHERE email = :email AND ip = :ip');
        $stmt->execute([':email' => $email, ':ip' => $ip]);
        $total = (int) $stmt->fetchColumn();

        $delay = (int) min(2500000, $total * 500000);
        if ($delay > 0) {
            usleep($delay);
        }
    }

    /** Remove o histórico de tentativas de (e-mail + IP) após sucesso. */
    public static function clear(PDO $pdo, string $email, string $ip): void
    {
        $pdo->prepare('DELETE FROM login_tentativas WHERE email = :email AND ip = :ip')
            ->execute([':email' => $email, ':ip' => $ip]);
    }
}
