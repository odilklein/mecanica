<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = limpar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        header("Location: login.php?erro=Preencha todos os campos");
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            header("Location: ../../index.php");
            exit();
        } else {
            header("Location: login.php?erro=Email ou senha incorretos");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: login.php?erro=Erro no sistema: " . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: login.php");
exit();
?>
