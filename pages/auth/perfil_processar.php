<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit();
}
validar_csrf();

$usuario_id = (int) $_SESSION['usuario_id'];
$nome = limpar($_POST['nome'] ?? '');
$email = strtolower(limpar($_POST['email'] ?? ''));
$senha = $_POST['senha'] ?? '';
$senha_confirmacao = $_POST['senha_confirmacao'] ?? '';

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['mensagem'] = 'Informe um nome e um e-mail válidos.';
    $_SESSION['mensagem_tipo'] = 'warning';
    header('Location: perfil.php');
    exit();
}

$stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id');
$stmt->execute([':email' => $email, ':id' => $usuario_id]);
if ($stmt->fetchColumn()) {
    $_SESSION['mensagem'] = 'Este e-mail já está em uso por outro usuário.';
    $_SESSION['mensagem_tipo'] = 'warning';
    header('Location: perfil.php');
    exit();
}

if ($senha !== '') {
    if (strlen($senha) < 8) {
        $_SESSION['mensagem'] = 'A nova senha deve ter pelo menos 8 caracteres.';
        $_SESSION['mensagem_tipo'] = 'warning';
        header('Location: perfil.php');
        exit();
    }
    if ($senha !== $senha_confirmacao) {
        $_SESSION['mensagem'] = 'A confirmação da senha não confere.';
        $_SESSION['mensagem_tipo'] = 'warning';
        header('Location: perfil.php');
        exit();
    }
}

try {
    if ($senha !== '') {
        $stmt = $conn->prepare('UPDATE usuarios SET nome = :nome, email = :email, senha = :senha WHERE id = :id');
        $stmt->execute([':nome' => $nome, ':email' => $email, ':senha' => password_hash($senha, PASSWORD_DEFAULT), ':id' => $usuario_id]);
    } else {
        $stmt = $conn->prepare('UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id');
        $stmt->execute([':nome' => $nome, ':email' => $email, ':id' => $usuario_id]);
    }
    $_SESSION['usuario_nome'] = $nome;
    $_SESSION['usuario_email'] = $email;
    registrar_auditoria($conn, $usuario_id, 'editar', 'usuario', $usuario_id, 'Perfil atualizado', ['alterou_senha' => $senha !== '']);
    $_SESSION['mensagem'] = 'Dados atualizados com sucesso.';
    $_SESSION['mensagem_tipo'] = 'success';
} catch (Throwable $e) {
    error_log($e->getMessage());
    $_SESSION['mensagem'] = 'Não foi possível atualizar os dados.';
    $_SESSION['mensagem_tipo'] = 'danger';
}
header('Location: perfil.php');
exit();