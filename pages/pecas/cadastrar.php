<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria']);

$titulo_pagina = "Nova Peça";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="package-open" class="text-primary me-2"></i>Nova Peça</h1>
        <span class="page-subtitle">Cadastre uma nova peça no estoque</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="codigo" class="form-label">Código *</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" placeholder="Ex: P001" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome da Peça *</label>
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: Filtro de Óleo" required>
                        </div>
                        <div class="col-md-3">
                            <label for="categoria" class="form-label">Categoria</label>
                            <select class="form-select" id="categoria" name="categoria">
                                <option value="">Selecione...</option>
                                <option value="Filtros">Filtros</option>
                                <option value="Freios">Freios</option>
                                <option value="Motor">Motor</option>
                                <option value="Suspensão">Suspensão</option>
                                <option value="Elétrica">Elétrica</option>
                                <option value="Lubrificantes">Lubrificantes</option>
                                <option value="Pneus">Pneus</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label for="quantidade" class="form-label">Quantidade Inicial *</label>
                            <input type="number" class="form-control" id="quantidade" name="quantidade" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="estoque_minimo" class="form-label">Estoque Mínimo *</label>
                            <input type="number" class="form-control" id="estoque_minimo" name="estoque_minimo" min="0" value="5" required>
                        </div>
                        <div class="col-md-3">
                            <label for="preco_compra" class="form-label">Preço de Compra</label>
                            <input type="text" class="form-control" id="preco_compra" name="preco_compra" oninput="formatarMoedaInput(this)">
                        </div>
                        <div class="col-md-3">
                            <label for="preco_venda" class="form-label">Preço de Venda *</label>
                            <input type="text" class="form-control" id="preco_venda" name="preco_venda" oninput="formatarMoedaInput(this)" required>
                        </div>
                        <div class="col-md-6">
                            <label for="fornecedor" class="form-label">Fornecedor</label>
                            <input type="text" class="form-control" id="fornecedor" name="fornecedor">
                        </div>
                        <div class="col-md-6">
                            <label for="localizacao" class="form-label">Localização (prateleira)</label>
                            <input type="text" class="form-control" id="localizacao" name="localizacao" placeholder="Ex: Corredor A, Prateleira 2">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i data-lucide="save"></i> Salvar Peça
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
