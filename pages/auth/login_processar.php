<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validar_csrf();
    $email = limpar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        header("Location: login.php?erro=Preencha todos os campos");
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            registrar_auditoria($conn, $usuario['id'], 'login', 'usuario', $usuario['id'], 'Login realizado');
            header("Location: ../../index.php");
            exit();
        } else {
            header("Location: login.php?erro=Email ou senha incorretos ou usuário desativado");
            exit();
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header("Location: login.php?erro=Não foi possível concluir o login");
        exit();
    }
}

header("Location: login.php");
exit();
?>
