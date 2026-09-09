<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Novo Cliente";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-user-plus text-primary me-2"></i>Novo Cliente</h1>
        <span class="page-subtitle">Preencha os dados do cliente</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="nome" class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="col-md-4">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" oninput="formatarCPF(this)">
                        </div>
                        <div class="col-md-4">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" oninput="formatarTelefone(this)">
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-4">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade">
                        </div>
                        <div class="col-12">
                            <label for="endereco" class="form-label">Endereço</label>
                            <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua, número, bairro">
                        </div>
                        <div class="col-12">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fas fa-save"></i> Salvar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
