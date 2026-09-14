<?php
// =============================================
// AJUDA PARA NOTIFICAÇÕES
// Insere notificações por usuários ou perfis
// =============================================
if (!isset($conn)) {
    require_once __DIR__ . '/../config/conexao.php';
}

function notificar_usuarios(PDO $conn, array $usuario_ids, ?int $os_id, string $tipo, string $mensagem): void
{
    $usuarios = array_values(array_unique(array_filter(array_map('intval', $usuario_ids))));
    if (!$usuarios) {
        return;
    }
    $ins = $conn->prepare('INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:uid, :os_id, :tipo, :msg)');
    foreach ($usuarios as $uid) {
        $ins->execute([':uid' => $uid, ':os_id' => $os_id, ':tipo' => $tipo, ':msg' => $mensagem]);
    }
}

function notificar_tipos(PDO $conn, array $tipos, ?int $os_id, string $tipo, string $mensagem, ?int $ignorar_usuario = null): void
{
    $tipos = array_values(array_filter($tipos));
    if (!$tipos) {
        return;
    }
    $pl = implode(',', array_fill(0, count($tipos), '?'));
    $st = $conn->prepare("SELECT id FROM usuarios WHERE ativo = 1 AND tipo IN ($pl)");
    $st->execute($tipos);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($ignorar_usuario) {
        $ids = array_values(array_filter($ids, fn($id) => (int) $id !== (int) $ignorar_usuario));
    }
    notificar_usuarios($conn, $ids, $os_id, $tipo, $mensagem);
}

function notificar_responsaveis_os(PDO $conn, int $os_id, string $tipo, string $mensagem, ?int $ignorar_usuario = null): void
{
    $st = $conn->prepare('SELECT usuario_id FROM os_responsaveis WHERE os_id = :os_id AND concluido_em IS NULL');
    $st->execute([':os_id' => $os_id]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($ignorar_usuario) {
        $ids = array_values(array_filter($ids, fn($id) => (int) $id !== (int) $ignorar_usuario));
    }
    notificar_usuarios($conn, $ids, $os_id, $tipo, $mensagem);
}