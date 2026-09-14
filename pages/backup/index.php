<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin']);

$dir_backup = __DIR__ . '/../../uploads/backups';
if (!is_dir($dir_backup)) @mkdir($dir_backup, 0775, true);

function gerar_dump_sql(PDO $conn): string
{
    $saida = "-- Backup gerado em " . date('Y-m-d H:i:s') . "\n";
    $saida .= "SET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n\n";
    $tabelas = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tabelas as $tabela) {
        $create = $conn->query("SHOW CREATE TABLE `$tabela`")->fetch(PDO::FETCH_ASSOC);
        $saida .= "DROP TABLE IF EXISTS `$tabela`;\n";
        $linhas_create = explode("\n", $create['Create Table']);
        foreach ($linhas_create as $linha) {
            $linha = str_replace('\\n', "\n", $linha);
            $saida .= $linha . "\n";
        }
        $saida .= ";\n\n";
        $stmt = $conn->query("SELECT * FROM `$tabela`");
        $colunas = [];
        $count_colunas = $stmt->columnCount();
        for ($i = 0; $i < $count_colunas; $i++) {
            $colunas[] = $stmt->getColumnMeta($i)['name'];
        }
        $lotes = 0;
        foreach ($stmt as $linha_dados) {
            if ($lotes === 0) {
                $saida .= "INSERT INTO `$tabela` (`" . implode('`, `', $colunas) . "`) VALUES\n";
            } else {
                $saida .= ",\n";
            }
            $valores = [];
            foreach ($colunas as $col) {
                $val = $linha_dados[$col];
                if ($val === null) {
                    $valores[] = 'NULL';
                } elseif (is_int($val) || is_float($val)) {
                    $valores[] = (string) $val;
                } else {
                    $valores[] = $conn->quote((string) $val);
                }
            }
            $saida .= '(' . implode(', ', $valores) . ')';
            $lotes++;
            if ($lotes >= 200) {
                $saida .= ";\n";
                $lotes = 0;
            }
        }
        if ($lotes > 0) $saida .= ";\n";
        $saida .= "\n";
    }
    $saida .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $saida;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_GET['acao'] ?? '';
    if ($acao === 'criar') {
        $nome = 'backup_' . date('Ymd_His') . '.sql';
        $caminho = $dir_backup . '/' . $nome;
        $ok = @file_put_contents($caminho, gerar_dump_sql($conn));
        if ($ok === false) {
            $_SESSION['mensagem'] = 'Não foi possível salvar o backup no servidor.';
            $_SESSION['mensagem_tipo'] = 'danger';
        } else {
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'backup', 'banco', null, 'Backup do banco criado', ['arquivo' => $nome]);
            $_SESSION['mensagem'] = 'Backup criado com sucesso: ' . $nome;
            $_SESSION['mensagem_tipo'] = 'success';
        }
        header('Location: index.php');
        exit();
    }
    if ($acao === 'excluir') {
        $arquivo = basename($_POST['arquivo'] ?? '');
        if ($arquivo !== '' && is_file($dir_backup . '/' . $arquivo) && strncmp($arquivo, 'backup_', 7) === 0 && substr($arquivo, -4) === '.sql') {
            @unlink($dir_backup . '/' . $arquivo);
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'backup_excluir', 'banco', null, 'Backup excluído', ['arquivo' => $arquivo]);
            $_SESSION['mensagem'] = 'Backup excluído.';
            $_SESSION['mensagem_tipo'] = 'success';
        }
        header('Location: index.php');
        exit();
    }
    header('Location: index.php');
    exit();
}

$backups = [];
foreach (glob($dir_backup . '/backup_*.sql') ?: [] as $caminho) {
    $backups[] = ['nome' => basename($caminho), 'tamanho' => filesize($caminho), 'data' => filemtime($caminho)];
}
usort($backups, fn($a, $b) => $b['data'] <=> $a['data']);

$titulo_pagina = 'Backup do banco';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header"><div><h1><i data-lucide="database-backup" class="text-primary me-2"></i>Backup do banco</h1><span class="page-subtitle">Gere e baixe cópias do banco de dados</span></div></div>
<div class="row g-3">
<div class="col-lg-4"><div class="card"><div class="card-header"><i data-lucide="plus" class="me-2"></i>Novo backup</div><div class="card-body"><p class="small text-muted">Cria um arquivo <code>.sql</code> completo (estrutura + dados) e o salva em <code>uploads/backups/</code>.</p><form method="POST" action="index.php?acao=criar"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><button class="btn btn-primary btn-icon w-100" type="submit"><i data-lucide="database-backup"></i>Gerar backup agora</button></form><p class="small text-muted mt-3">Recomenda-se gerar backups com frequência e copiá-los para fora do servidor.</p></div></div></div>
<div class="col-lg-8"><div class="card"><div class="card-header"><i data-lucide="clock-history" class="me-2"></i>Backups salvos</div><div class="card-body p-0">
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Arquivo</th><th>Data</th><th>Tamanho</th><th class="text-end">Ações</th></tr></thead><tbody>
<?php foreach ($backups as $b): ?><tr><td><i data-lucide="file-archive" class="me-2"></i><?php echo esc($b['nome']); ?></td><td><?php echo date('d/m/Y H:i', (int) $b['data']); ?></td><td><?php echo number_format((int) $b['tamanho'] / 1024, 1, ',', '.') . ' KB'; ?></td><td class="text-end actions-cell"><a class="btn btn-outline-primary btn-sm" href="baixar.php?arquivo=<?php echo esc(urlencode($b['nome'])); ?>" title="Baixar"><i data-lucide="download"></i></a><form method="POST" action="index.php?acao=excluir" class="d-inline" onsubmit="return confirm('Excluir este backup?');"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="arquivo" value="<?php echo esc($b['nome']); ?>"><button class="btn btn-outline-danger btn-sm ms-1" type="submit" title="Excluir"><i data-lucide="trash-2"></i></button></form></td></tr><?php endforeach; ?>
<?php if (!$backups): ?><tr class="row-empty"><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="database"></i></div><h4>Nenhum backup salvo</h4><p>Clique em "Gerar backup agora" para criar a primeira cópia.</p></div></td></tr><?php endif; ?>
</tbody></table></div>
</div></div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>