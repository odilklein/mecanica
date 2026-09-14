<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();
$acao = $_GET['acao'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$nome = limpar($_POST['nome'] ?? '');
$email = strtolower(limpar($_POST['email'] ?? ''));
$cpf = limpar($_POST['cpf'] ?? '') ?: null;
$telefone = limpar($_POST['telefone'] ?? '') ?: null;
$cargo = limpar($_POST['cargo'] ?? '') ?: null;
$data_admissao = $_POST['data_admissao'] ?? null;
$salario = $_POST['salario'] !== '' ? (float) $_POST['salario'] : null;
$tipo = $_POST['tipo'] ?? 'mecanico';
$senha = $_POST['senha'] ?? '';
$ativo = isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1;
$tipos = ['admin', 'secretaria', 'mecanico_chefe', 'mecanico'];
if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($tipo, $tipos, true) || ($salario !== null && $salario < 0)) { $_SESSION['mensagem'] = 'Informe nome, e-mail, perfil e salário válidos.'; $_SESSION['mensagem_tipo'] = 'warning'; header('Location: ' . ($acao === 'editar' ? 'editar.php?id=' . $id : 'cadastrar.php')); exit(); }
if ($tipo === 'admin' && !tem_papel(['admin'])) { http_response_code(403); exit('Apenas administrador pode criar administrador.'); }
try {
    if ($acao === 'cadastrar') {
        if (strlen($senha) < 8) throw new RuntimeException('A senha deve ter pelo menos 8 caracteres.');
        $stmt = $conn->prepare('INSERT INTO usuarios (nome, email, senha, tipo, cpf, telefone, salario, data_admissao, cargo, ativo) VALUES (:nome, :email, :senha, :tipo, :cpf, :telefone, :salario, :data_admissao, :cargo, 1)');
        $stmt->execute([':nome' => $nome, ':email' => $email, ':senha' => password_hash($senha, PASSWORD_DEFAULT), ':tipo' => $tipo, ':cpf' => $cpf, ':telefone' => $telefone, ':salario' => $salario, ':data_admissao' => $data_admissao ?: null, ':cargo' => $cargo]);
        $_SESSION['mensagem'] = 'Funcionário cadastrado com sucesso.';
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'usuario', (int) $conn->lastInsertId(), 'Funcionário cadastrado', ['nome' => $nome, 'tipo' => $tipo]);
    } elseif ($acao === 'editar') {
        if ($id <= 0) throw new RuntimeException('Funcionário inválido.');
        if ($id === (int) $_SESSION['usuario_id']) {
            if (!$ativo) throw new RuntimeException('Você não pode desativar o próprio acesso.');
            $tipo = $_SESSION['usuario_tipo'];
        }
        $fields = 'nome = :nome, email = :email, cpf = :cpf, telefone = :telefone, salario = :salario, data_admissao = :data_admissao, cargo = :cargo, ativo = :ativo, tipo = :tipo';
        $params = [':nome' => $nome, ':email' => $email, ':cpf' => $cpf, ':telefone' => $telefone, ':salario' => $salario, ':data_admissao' => $data_admissao ?: null, ':cargo' => $cargo, ':ativo' => $ativo, ':tipo' => $tipo, ':id' => $id];
        if ($senha !== '') { if (strlen($senha) < 8) throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.'); $fields .= ', senha = :senha'; $params[':senha'] = password_hash($senha, PASSWORD_DEFAULT); }
        $stmt = $conn->prepare("UPDATE usuarios SET $fields WHERE id = :id"); $stmt->execute($params);
        $_SESSION['mensagem'] = 'Funcionário atualizado com sucesso.';
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'usuario', $id, 'Funcionário atualizado', ['nome' => $nome, 'tipo' => $tipo, 'ativo' => $ativo]);
    } else { throw new RuntimeException('Ação inválida.'); }
    $_SESSION['mensagem_tipo'] = 'success'; header('Location: listar.php'); exit();
} catch (Throwable $e) { error_log($e->getMessage()); $_SESSION['mensagem'] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar o funcionário.'; $_SESSION['mensagem_tipo'] = 'danger'; header('Location: ' . ($acao === 'editar' ? 'editar.php?id=' . $id : 'cadastrar.php')); exit(); }
