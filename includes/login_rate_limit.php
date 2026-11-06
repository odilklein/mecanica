<?php
// =============================================
// RATE LIMIT DE LOGIN — bloqueio por email + IP
// -------------------------------------------------
// Protege contra força-bruta mesmo que o Turnstile seja driblado.
// Conta as falhas na janela de tempo (tabela login_tentativas) e
// bloqueia após N tentativas.
//
// Configurável por env (opcional):
//   LOGIN_MAX_TENTATIVAS  (padrão 5)
//   LOGIN_JANELA_MINUTOS  (padrão 15)
// =============================================

if (!function_exists('login_rate_limit_max')) {
    function login_rate_limit_max(): int
    {
        $v = (int) env('LOGIN_MAX_TENTATIVAS', '5');
        return $v > 0 ? $v : 5;
    }
}

if (!function_exists('login_rate_limit_janela')) {
    function login_rate_limit_janela(): int
    {
        $v = (int) env('LOGIN_JANELA_MINUTOS', '15');
        return $v > 0 ? $v : 15;
    }
}

if (!function_exists('login_rate_limit_ip')) {
    function login_rate_limit_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}

if (!function_exists('login_rate_limit_verificar')) {
    /**
     * Verifica se (email + IP) está bloqueado.
     * Retorna ['bloqueado' => bool, 'tentativas' => int, 'max' => int,
     *          'segundos_restantes' => int, 'minutos_restantes' => int].
     */
    function login_rate_limit_verificar(PDO $conn, string $email, string $ip): array
    {
        $max = login_rate_limit_max();
        $janela = login_rate_limit_janela();

        // Limpa registros antigos fora da janela.
        $conn->prepare('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL :min MINUTE)')
            ->execute([':min' => $janela]);

        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS total, MAX(criado_em) AS ultima
               FROM login_tentativas
              WHERE email = :email AND ip = :ip AND criado_em >= (NOW() - INTERVAL :min MINUTE)'
        );
        $stmt->execute([':email' => $email, ':ip' => $ip, ':min' => $janela]);
        $linha = $stmt->fetch();

        $total = (int) ($linha['total'] ?? 0);
        $bloqueado = $total >= $max;

        $segundos = 0;
        if ($bloqueado && !empty($linha['ultima'])) {
            $segundos = max(0, $janela * 60 - (time() - strtotime($linha['ultima'])));
        }

        return [
            'bloqueado' => $bloqueado,
            'tentativas' => $total,
            'max' => $max,
            'segundos_restantes' => $segundos,
            'minutos_restantes' => (int) ceil($segundos / 60),
        ];
    }
}

if (!function_exists('login_rate_limit_registrar_falha')) {
    /** Registra uma tentativa com falha e aplica um backoff crescente. */
    function login_rate_limit_registrar_falha(PDO $conn, string $email, string $ip): void
    {
        $conn->prepare('INSERT INTO login_tentativas (email, ip) VALUES (:email, :ip)')
            ->execute([':email' => $email, ':ip' => $ip]);

        // Backoff suave: atraso crescente por falha (máx ~2.5s).
        $stmt = $conn->prepare('SELECT COUNT(*) FROM login_tentativas WHERE email = :email AND ip = :ip');
        $stmt->execute([':email' => $email, ':ip' => $ip]);
        $total = (int) $stmt->fetchColumn();

        $atraso = (int) min(2500000, $total * 500000); // 0.5s por falha
        if ($atraso > 0) {
            usleep($atraso);
        }
    }
}

if (!function_exists('login_rate_limit_limpar')) {
    /** Remove o histórico de tentativas de (email + IP) após sucesso. */
    function login_rate_limit_limpar(PDO $conn, string $email, string $ip): void
    {
        $conn->prepare('DELETE FROM login_tentativas WHERE email = :email AND ip = :ip')
            ->execute([':email' => $email, ':ip' => $ip]);
    }
}
