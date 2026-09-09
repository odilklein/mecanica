<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$titulo_pagina = 'Novo atendimento';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="clipboard-plus" class="text-primary me-2"></i>Novo atendimento</h1>
        <span class="page-subtitle">Registre a chegada do cliente em poucos passos</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon"><i data-lucide="list"></i> Ver OS</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="nome">Nome do cliente *</label>
                            <input class="form-control" id="nome" name="nome" maxlength="120" required autofocus>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="telefone">Telefone</label>
                            <input class="form-control" id="telefone" name="telefone" maxlength="20" inputmode="tel">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="marca">Marca</label>
                            <input class="form-control" id="marca" name="marca" maxlength="50">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="modelo">Modelo</label>
                            <input class="form-control" id="modelo" name="modelo" maxlength="60">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="placa">Placa</label>
                            <input class="form-control" id="placa" name="placa" maxlength="10" style="text-transform:uppercase">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="prioridade">Prioridade</label>
                            <select class="form-select" id="prioridade" name="prioridade">
                                <option value="normal">Normal</option>
                                <option value="baixa">Baixa</option>
                                <option value="alta">Alta</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="queixa_inicial">O que precisa ser feito? *</label>
                            <textarea class="form-control" id="queixa_inicial" name="queixa_inicial" rows="3" maxlength="4000" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="observacoes_internas">Observações internas</label>
                            <textarea class="form-control" id="observacoes_internas" name="observacoes_internas" rows="3" maxlength="4000"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon"><i data-lucide="save"></i> Criar ordem de serviço</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
