<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
require_once __DIR__ . '/../../includes/turnstile.php';
require_once __DIR__ . '/../../includes/login_rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validar_csrf();

    // Verificação anti-bot (Turnstile). Se a configuração estiver incompleta,
    // turnstile_verificar() retorna ok e o login segue normalmente.
    $turnstile = turnstile_verificar($_POST['cf-turnstile-response'] ?? null, 'login');
    if (!$turnstile['ok']) {
        error_log('Turnstile (login) recusado: ' . $turnstile['motivo']
            . (isset($turnstile['detalhe']) ? ' [' . $turnstile['detalhe'] . ']' : ''));
        $msg = ($turnstile['codigo'] ?? '') === 'token_ausente'
            ? 'Confirme a verificação de segurança do formulário antes de entrar.'
            : 'Falha na verificação de segurança. Tente novamente.';
        header('Location: login.php?erro=' . urlencode($msg));
        exit();
    }

    $email = limpar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        header("Location: login.php?erro=Preencha todos os campos");
        exit();
    }

    // Rate limit: bloqueia força-bruta por email + IP (além do Turnstile).
    $ip = login_rate_limit_ip();
    $limite = login_rate_limit_verificar($conn, $email, $ip);
    if ($limite['bloqueado']) {
        header("Location: login.php?erro=Muitas tentativas de login. Tente novamente em " . $limite['minutos_restantes'] . " min.");
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            login_rate_limit_limpar($conn, $email, $ip);
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            registrar_auditoria($conn, $usuario['id'], 'login', 'usuario', $usuario['id'], 'Login realizado');
            header("Location: ../../index.php");
            exit();
        } else {
            login_rate_limit_registrar_falha($conn, $email, $ip);
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
