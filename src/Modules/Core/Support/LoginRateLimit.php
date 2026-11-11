<?php
declare(strict_types=1);

namespace Modules\Core\Support;

use PDO;

/**
 * Rate limit de login por e-mail + IP.
 *
 * Conta falhas na janela de tempo (tabela login_tentativas) e bloqueia após
 * N tentativas. Configurável por LOGIN_MAX_TENTATIVAS e LOGIN_JANELA_MINUTOS.
 */
class LoginRateLimit
{
    public static function max(): int
    {
        $value = (int) env('LOGIN_MAX_TENTATIVAS', '5');
        return $value > 0 ? $value : 5;
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
        $window = self::windowMinutes();

        // Limpa registros antigos fora da janela.
        $pdo->prepare('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL ' . $window . ' MINUTE)')
            ->execute();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total, MAX(criado_em) AS ultima
               FROM login_tentativas
              WHERE email = :email AND ip = :ip
                AND criado_em >= (NOW() - INTERVAL ' . $window . ' MINUTE)'
        );
        $stmt->execute([':email' => $email, ':ip' => $ip]);
        $row = $stmt->fetch();

        $total = (int) ($row['total'] ?? 0);
        $blocked = $total >= $max;

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
