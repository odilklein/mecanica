<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Novo Serviço";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-tools text-primary me-2"></i>Novo Serviço</h1>
        <span class="page-subtitle">Cadastre um novo tipo de serviço</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nome" class="form-label">Nome do Serviço *</label>
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: Troca de Óleo" required>
                        </div>
                        <div class="col-12">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3" placeholder="Descrição detalhada do serviço"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="preco_mao_obra" class="form-label">Preço Mão de Obra *</label>
                            <input type="text" class="form-control" id="preco_mao_obra" name="preco_mao_obra" oninput="formatarMoedaInput(this)" required>
                        </div>
                        <div class="col-md-4">
                            <label for="tempo_estimado" class="form-label">Tempo Estimado (min)</label>
                            <input type="number" class="form-control" id="tempo_estimado" name="tempo_estimado" min="0" placeholder="Ex: 60">
                        </div>
                        <div class="col-md-4">
                            <label for="ativo" class="form-label">Status</label>
                            <select class="form-select" id="ativo" name="ativo">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fas fa-save"></i> Salvar Serviço
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function() {
        moedaParaNumero(document.getElementById('preco_mao_obra'));
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
