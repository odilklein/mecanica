<?php
// =============================================
// CONEXÃO COM O BANCO DE DADOS
// =============================================

require_once __DIR__ . '/env.php';

$db_host = env('DB_HOST', 'localhost');
$db_port = env('DB_PORT', '3306');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');
$db_name = env('DB_NAME', 'mecanica');

$dsn = 'mysql:host=' . $db_host . ';port=' . $db_port . ';dbname=' . $db_name . ';charset=utf8mb4';

try {
    $conn = new PDO($dsn, $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fonte_txt = (defined('MECANICA_ENV_ARQUIVO') && MECANICA_ENV_ARQUIVO)
        ? MECANICA_ENV_ARQUIVO
        : 'nenhuma (usando variaveis de ambiente/padroes)';
    $detalhe = sprintf(
        '[%s] %s | host=%s port=%s db=%s user=%s | config=%s',
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $db_host,
        $db_port,
        $db_name,
        $db_user,
        $fonte_txt
    );
    @error_log($detalhe);
    $log_ok = @file_put_contents(__DIR__ . '/../error.log', $detalhe . PHP_EOL, FILE_APPEND) !== false;
    http_response_code(500);
    die($log_ok
        ? "Não foi possível conectar ao banco de dados. Detalhes gravados em error.log na raiz do projeto."
        : "Não foi possível conectar ao banco de dados. Verifique as credenciais do .env.");
}

// Função para escapar dados e evitar XSS
function limpar($dado) {
    return trim((string) $dado);
}

function esc($dado) {
    return htmlspecialchars((string) $dado, ENT_QUOTES, 'UTF-8');
}

// Função para formatar moeda
function formatar_moeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para validar CPF (aceita com ou sem máscara)
function validar_cpf($cpf) {
    $cpf = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int) $cpf[$t] !== $d) {
            return false;
        }
    }
    return true;
}

// Função para validar placa (formato antigo ABC-1234 ou Mercosul ABC1D23)
function validar_placa($placa) {
    $placa = strtoupper(trim((string) $placa));
    return (bool) preg_match('/^(?:[A-Z]{3}-\d{4}|[A-Z]{3}\d[A-Z0-9]\d{2})$/', $placa);
}

// Paleta padrão de cores de veículos (nome => hexadecimal)
function cores_veiculo(): array {
    return [
        'Branco' => '#f2f2f2',
        'Preto' => '#141414',
        'Prata' => '#c4c4c4',
        'Cinza' => '#7a7a7a',
        'Grafite' => '#424242',
        'Azul' => '#1e56a0',
        'Azul Escuro' => '#15346a',
        'Verde' => '#2e7d32',
        'Vermelho' => '#b3261e',
        'Vinho' => '#7b1e3f',
        'Laranja' => '#e65c00',
        'Amarelo' => '#f2c94c',
        'Bege' => '#e7d6b5',
        'Dourado' => '#c9a227',
        'Marrom' => '#795548',
        'Roxo' => '#5e35b1',
        'Rosa' => '#d6839f',
    ];
}

// Converte o valor salvo de "cor" em um hexadecimal renderizável.
// Aceita um nome da paleta (Ex: "Prata") ou um hex customizado (#b3261e).
function cor_para_hex(?string $cor): ?string {
    $cor = trim((string) $cor);
    if ($cor === '') {
        return null;
    }
    if (preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $cor)) {
        return strtoupper($cor);
    }
    $normalizado = mb_strtolower($cor);
    foreach (cores_veiculo() as $nome => $hex) {
        if (mb_strtolower($nome) === $normalizado) {
            return $hex;
        }
    }
    return null;
}
?>