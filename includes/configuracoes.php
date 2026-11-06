<?php
// =============================================
// CONFIGURAÇÕES (chave/valor) — TEMA, SEO E MÓDULOS
// -------------------------------------------------
// Leitura com cache estático (1 query por request) e escrita via
// upsert (INSERT ... ON DUPLICATE KEY UPDATE). Os valores vêm da
// tabela configuracoes e são editados pelo admin em
// pages/configuracoes/listar.php.
//
// Uso:
//   $cor = config_valor($conn, 'tema_cor_primaria', '#b8270f');
//   if (config_flag($conn, 'modulo_fipe')) { ... }
// =============================================

if (!function_exists('config_carregar')) {
    /**
     * Carrega todas as configurações num cache estático.
     * Tolerante a falhas (tabela ainda não migrada) — retorna vazio.
     */
    function config_carregar(PDO $conn): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                foreach ($conn->query('SELECT chave, valor FROM configuracoes') as $linha) {
                    $cache[$linha['chave']] = $linha['valor'];
                }
            } catch (Throwable $e) {
                $cache = [];
            }
        }
        return $cache;
    }
}

if (!function_exists('config_valor')) {
    /** Valor de uma chave, com fallback para $padrao quando ausente. */
    function config_valor(PDO $conn, string $chave, string $padrao = ''): string
    {
        $cache = config_carregar($conn);
        if (array_key_exists($chave, $cache) && $cache[$chave] !== null) {
            return (string) $cache[$chave];
        }
        return $padrao;
    }
}

if (!function_exists('config_flag')) {
    /** Chave booleana (bool/switch). Valores '1', 'true', 'on' = true. */
    function config_flag(PDO $conn, string $chave, bool $padrao = true): bool
    {
        $v = strtolower(trim(config_valor($conn, $chave, $padrao ? '1' : '0')));
        return in_array($v, ['1', 'true', 'on', 'sim', 'yes'], true);
    }
}

if (!function_exists('config_definir')) {
    /** Grava (upsert) uma chave. */
    function config_definir(PDO $conn, string $chave, ?string $valor): void
    {
        $stmt = $conn->prepare(
            'INSERT INTO configuracoes (grupo, chave, valor) VALUES (:grupo, :chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute([
            ':grupo' => 'manual',
            ':chave' => $chave,
            ':valor' => $valor,
        ]);
    }
}

if (!function_exists('config_grupo')) {
    /** Lista as configurações de um grupo, ordenadas (rotulo, tipo, valor, chave). */
    function config_grupo(PDO $conn, string $grupo): array
    {
        $stmt = $conn->prepare('SELECT chave, valor, tipo, rotulo, ajuda FROM configuracoes WHERE grupo = :grupo ORDER BY ordem, id');
        $stmt->execute([':grupo' => $grupo]);
        return $stmt->fetchAll();
    }
}

// =============================================
// CORES — utilitários para o tema editável
// =============================================

if (!function_exists('hex_para_rgb')) {
    /** '#rrggbb' ou '#rgb' -> [r, g, b] (0-255). null se inválido. */
    function hex_para_rgb(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            return [hexdec(str_repeat($hex[0], 2)), hexdec(str_repeat($hex[1], 2)), hexdec(str_repeat($hex[2], 2))];
        }
        return null;
    }
}

if (!function_exists('rgb_para_hex')) {
    function rgb_para_hex(int $r, int $g, int $b): string
    {
        $clamp = fn(int $v) => max(0, min(255, $v));
        return sprintf('#%02x%02x%02x', $clamp($r), $clamp($g), $clamp($b));
    }
}

if (!function_exists('hex_misturar_branco')) {
    /** Mistura uma cor com branco (0..1). pct=1 resulta em branco. */
    function hex_misturar_branco(string $hex, float $pct): string
    {
        $rgb = hex_para_rgb($hex);
        if ($rgb === null) {
            return '#ffffff';
        }
        return rgb_para_hex(
            (int) round($rgb[0] + (255 - $rgb[0]) * $pct),
            (int) round($rgb[1] + (255 - $rgb[1]) * $pct),
            (int) round($rgb[2] + (255 - $rgb[2]) * $pct)
        );
    }
}

if (!function_exists('hex_escurecer')) {
    /** Escurece uma cor multiplicando os canais por (1 - pct). */
    function hex_escurecer(string $hex, float $pct): string
    {
        $rgb = hex_para_rgb($hex);
        if ($rgb === null) {
            return '#000000';
        }
        return rgb_para_hex(
            (int) round($rgb[0] * (1 - $pct)),
            (int) round($rgb[1] * (1 - $pct)),
            (int) round($rgb[2] * (1 - $pct))
        );
    }
}

if (!function_exists('tema_css_inline')) {
    /**
     * Gera o bloco <style> que sobrescreve os tokens da marca (cor primária)
     * no tema claro e escuro. Deve ser impresso DEPOIS de mecanica.css para
     * vencer a especificidade (mesmo seletor, ordem de documento).
     */
    function tema_css_inline(string $cor_clara, string $cor_escura): string
    {
        $clara = hex_para_rgb($cor_clara) ?: [184, 39, 15];
        $escura = hex_para_rgb($cor_escura) ?: [184, 39, 15];

        $light = [
            '--bs-primary' => rgb_para_hex($clara[0], $clara[1], $clara[2]),
            '--bs-primary-rgb' => implode(', ', $clara),
            '--bs-primary-contrast' => '#ffffff',
            '--bs-primary-bg-subtle' => hex_misturar_branco($cor_clara, 0.88),
            '--bs-primary-border-subtle' => hex_misturar_branco($cor_clara, 0.55),
            '--bs-primary-text-emphasis' => hex_escurecer($cor_clara, 0.25),
        ];

        $dark = [
            '--bs-primary' => rgb_para_hex($escura[0], $escura[1], $escura[2]),
            '--bs-primary-rgb' => implode(', ', $escura),
            '--bs-primary-contrast' => '#ffffff',
            '--bs-primary-bg-subtle' => sprintf('rgba(%d, %d, %d, 0.22)', $escura[0], $escura[1], $escura[2]),
            '--bs-primary-border-subtle' => sprintf('rgba(%d, %d, %d, 0.45)', $escura[0], $escura[1], $escura[2]),
            '--bs-primary-text-emphasis' => hex_misturar_branco($cor_escura, 0.60),
        ];

        $css = '';
        foreach ($light as $prop => $valor) {
            $css .= '    ' . $prop . ': ' . $valor . ";\n";
        }
        $bloco_light = ':root{' . "\n" . $css . '}';

        $css = '';
        foreach ($dark as $prop => $valor) {
            $css .= '    ' . $prop . ': ' . $valor . ";\n";
        }
        $bloco_dark = '[data-bs-theme="dark"]{' . "\n" . $css . '}';

        return '<style id="tema-config">' . "\n" . $bloco_light . "\n" . $bloco_dark . "\n</style>";
    }
}

if (!function_exists('config_conexao_segura')) {
    /**
     * Conexão independente e tolerante a falhas, para páginas fora do shell
     * (ex.: login) lerem configurações sem depender do conexao.php global.
     * Retorna null em qualquer falha (banco indisponível).
     */
    function config_conexao_segura(): ?PDO
    {
        try {
            $dsn = 'mysql:host=' . env('DB_HOST', 'localhost')
                . ';port=' . env('DB_PORT', '3306')
                . ';dbname=' . env('DB_NAME', 'mecanica')
                . ';charset=utf8mb4';
            $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('config_valor_seguro')) {
    /** Lê uma configuração de forma segura (sem quebrar se o banco estiver fora). */
    function config_valor_seguro(string $chave, string $padrao = ''): string
    {
        $pdo = config_conexao_segura();
        if ($pdo === null) {
            return $padrao;
        }
        try {
            return config_valor($pdo, $chave, $padrao);
        } catch (Throwable $e) {
            return $padrao;
        }
    }
}
