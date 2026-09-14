<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
require_once __DIR__ . '/../../config/empresa.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT o.*, c.nome as cliente_nome, c.cpf, c.telefone, c.email, c.endereco, c.cidade, v.marca, v.modelo, v.placa, v.ano, v.km_atual FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id WHERE o.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$orcamento = $stmt->fetch();

if (!$orcamento) {
    die("Orçamento não encontrado.");
}

$stmt = $conn->prepare("SELECT oi.*, p.nome as peca_nome, s.nome as servico_nome FROM orcamento_itens oi LEFT JOIN pecas p ON p.id = oi.peca_id LEFT JOIN servicos s ON s.id = oi.servico_id WHERE oi.orcamento_id = :id ORDER BY oi.id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$itens = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento #<?php echo str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 30px; }
        .header { text-align: center; border-bottom: 3px solid #b8270f; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #b8270f; font-size: 28px; }
        .header p { color: #666; }
        .header .contact-line { font-size: 12px; margin-top: 8px; line-height: 1.6; }
        .info-box { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .info-box h3 { font-size: 14px; color: #b8270f; margin-bottom: 8px; text-transform: uppercase; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .info-grid .full { grid-column: 1 / -1; }
        .info-label { font-weight: bold; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        table th { background: #f8f9fa; font-size: 13px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { margin-left: auto; width: 300px; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        .totals .grand-total { font-size: 18px; font-weight: bold; border-top: 2px solid #000; padding-top: 10px; }
        .totals .grand-total span { color: #b8270f; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
        .stamp { margin-top: 30px; display: flex; justify-content: space-between; }
        .stamp div { width: 45%; text-align: center; border-top: 1px solid #999; padding-top: 8px; font-size: 12px; }
        .no-print { text-align: center; margin: 20px 0; }
        .no-print button { padding: 12px 30px; font-size: 16px; background: #b8270f; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        @media print {
            .no-print { display: none; }
            body { font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir</button>   <a href="detalhes.php?id=<?php echo $id; ?>" style="margin-left:10px;">Voltar</a>
    </div>

    <div class="container">
        <div class="header">
            <h1><?php echo EMPRESA_NOME; ?></h1>
            <p><?php echo EMPRESA_SLOGAN; ?> · Orçamento de Serviços Automotivos</p>
            <p class="contact-line">
                <?php echo EMPRESA_ENDERECO; ?> · <?php echo EMPRESA_CIDADE; ?><br>
                Tel: <?php echo EMPRESA_TELEFONE; ?> · <?php echo EMPRESA_EMAIL; ?>
            </p>
        </div>

        <div class="info-box">
            <h3>Orçamento Nº <?php echo str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT); ?></h3>
            <div class="info-grid">
                <div><span class="info-label">Data:</span> <?php echo date('d/m/Y', strtotime($orcamento['data_orcamento'])); ?></div>
                <div><span class="info-label">Validade:</span> <?php echo $orcamento['data_validade'] ? date('d/m/Y', strtotime($orcamento['data_validade'])) : '---'; ?></div>
                <div class="full"><span class="info-label">Status:</span> <?php echo esc(ucfirst($orcamento['status'])); ?></div>
            </div>
        </div>

        <div class="info-box">
            <h3>Dados do Cliente</h3>
            <div class="info-grid">
                <div><span class="info-label">Cliente:</span> <?php echo esc($orcamento['cliente_nome']); ?></div>
                <div><span class="info-label">Telefone:</span> <?php echo esc($orcamento['telefone'] ?: '-'); ?></div>
                <div><span class="info-label">CPF:</span> <?php echo esc($orcamento['cpf'] ?: '-'); ?></div>
                <div><span class="info-label">Email:</span> <?php echo esc($orcamento['email'] ?: '-'); ?></div>
            </div>
        </div>

        <?php if ($orcamento['marca']): ?>
        <div class="info-box">
            <h3>Dados do Veículo</h3>
            <div class="info-grid">
                <div><span class="info-label">Veículo:</span> <?php echo esc($orcamento['marca'] . ' ' . $orcamento['modelo']); ?></div>
                <div><span class="info-label">Placa:</span> <?php echo esc($orcamento['placa']); ?></div>
                <div><span class="info-label">Ano:</span> <?php echo esc($orcamento['ano'] ?: '-'); ?></div>
                <div><span class="info-label">KM:</span> <?php echo number_format($orcamento['km_atual'], 0, ',', '.'); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <h3 style="margin-top:20px; color:#b8270f; text-transform:uppercase; font-size:14px;">Descrição dos Serviços / Peças</h3>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width:40px">Qtd</th>
                    <th>Descrição</th>
                    <th style="width:130px">Preço Unit.</th>
                    <th style="width:130px">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <tr>
                        <td class="text-center"><?php echo (int) $item['quantidade']; ?></td>
                        <td>
                            <?php echo $item['tipo'] == 'peca' ? '[Peça] ' : '[Mão de Obra] '; ?>
                            <?php echo htmlspecialchars($item['descricao_item'] ?: ($item['peca_nome'] ?: $item['servico_nome'])); ?>
                        </td>
                        <td class="text-right"><?php echo 'R$ ' . number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                        <td class="text-right"><?php echo 'R$ ' . number_format($item['subtotal'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Peças:</span><span><?php echo 'R$ ' . number_format($orcamento['total_pecas'], 2, ',', '.'); ?></span></div>
            <div><span>Mão de Obra:</span><span><?php echo 'R$ ' . number_format($orcamento['total_mao_obra'], 2, ',', '.'); ?></span></div>
            <div class="grand-total"><span>TOTAL:</span><span><?php echo 'R$ ' . number_format($orcamento['total_geral'], 2, ',', '.'); ?></span></div>
        </div>

        <?php if ($orcamento['observacoes']): ?>
        <div class="info-box" style="margin-top:20px;">
            <h3>Observações</h3>
            <p><?php echo nl2br(htmlspecialchars($orcamento['observacoes'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="stamp">
            <div>Assinatura do Cliente</div>
            <div>Responsável pela Oficina</div>
        </div>

        <div class="footer">
            <p>Obrigado pela preferência! Este orçamento é válido até a data indicada acima.</p>
            <p>AutoMecânica - Sistema de Gestão de Oficina Mecânica</p>
        </div>
    </div>
</body>
</html>
