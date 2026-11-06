<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/configuracoes.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}
validar_csrf();

$todas = $conn->query('SELECT chave, tipo FROM configuracoes ORDER BY grupo, ordem, id')->fetchAll();

$salvos = 0;
$invalidas = 0;

foreach ($todas as $cfg) {
    $chave = $cfg['chave'];
    $tipo = $cfg['tipo'];

    if ($tipo === 'bool') {
        $valor = isset($_POST[$chave]) ? '1' : '0';
    } else {
        $valor = isset($_POST[$chave]) ? trim((string) $_POST[$chave]) : '';
        if ($tipo === 'cor') {
            // Cor: aceita apenas hex de 6 dígitos; caso contrário ignora (mantém anterior).
            if ($valor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
                $invalidas++;
                continue;
            }
        }
    }

    config_definir($conn, $chave, $valor);
    $salvos++;
}

try {
    registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'configuracao', null, 'Configurações do sistema atualizadas', ['alteracoes' => $salvos]);
    $_SESSION['mensagem'] = $salvos . ' configuração(ões) salva(s) com sucesso.';
    $_SESSION['mensagem_tipo'] = 'success';
    if ($invalidas > 0) {
        $_SESSION['mensagem'] .= ' ' . $invalidas . ' cor(es) inválida(s) foram ignoradas.';
        $_SESSION['mensagem_tipo'] = 'warning';
    }
} catch (Throwable $e) {
    error_log('Salvar configurações: ' . $e->getMessage());
    $_SESSION['mensagem'] = 'Não foi possível salvar as configurações.';
    $_SESSION['mensagem_tipo'] = 'danger';
}

header('Location: listar.php');
exit();
