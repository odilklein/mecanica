<?php
require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$usuario_id = (int) $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.']);
        exit();
    }
    if ($acao === 'marcar_lida') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE id = :id AND usuario_id = :uid AND lida_em IS NULL');
        $stmt->execute([':id' => $id, ':uid' => $usuario_id]);
        echo json_encode(['ok' => true]);
        exit();
    }
    if ($acao === 'marcar_todas_lidas') {
        $stmt = $conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = :uid AND lida_em IS NULL');
        $stmt->execute([':uid' => $usuario_id]);
        echo json_encode(['ok' => true]);
        exit();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Ação inválida.']);
    exit();
}

$stmt = $conn->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL');
$stmt->execute([$usuario_id]);
$total_nao_lidas = (int) $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT n.id, n.os_id, n.tipo, n.mensagem, n.lida_em, n.criada_em, os.numero AS os_numero
    FROM notificacoes n
    LEFT JOIN ordens_servico os ON os.id = n.os_id
    WHERE n.usuario_id = ?
    ORDER BY n.criada_em DESC
    LIMIT 15");
$stmt->execute([$usuario_id]);

$itens = [];
foreach ($stmt->fetchAll() as $n) {
    $itens[] = [
        'id' => (int) $n['id'],
        'os_id' => $n['os_id'] ? (int) $n['os_id'] : null,
        'os_numero' => $n['os_numero'] ? (int) $n['os_numero'] : null,
        'tipo' => $n['tipo'],
        'mensagem' => $n['mensagem'],
        'lida' => $n['lida_em'] !== null,
        'tempo' => tempo_relativo($n['criada_em']),
    ];
}

function tempo_relativo(string $data): string
{
    $d = new DateTime($data);
    $agora = new DateTime();
    $diff = $agora->diff($d);
    if ($diff->d > 6) return $d->format('d/m/Y H:i');
    if ($diff->d >= 1) return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
    if ($diff->h >= 1) return $diff->h . ' h atrás';
    if ($diff->i >= 1) return $diff->i . ' min atrás';
    return 'agora';
}

echo json_encode([
    'nao_lidas' => $total_nao_lidas,
    'itens' => $itens,
]);