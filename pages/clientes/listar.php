<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/clientes.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/campos.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

// Garante a coluna de status em bancos existentes (idempotente).
clientes_garantir_schema($conn);

$titulo_pagina = 'Clientes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$config = [
    'apiUrl' => $caminho_base . '/api/clientes.php',
    'idCampo' => 'id',
    'singular' => 'Cliente',
    'plural' => 'clientes',
    'porPagina' => 15,
    'tplId' => 'tplCliente',
    'emptyHtml' => crud_empty_state('users', 'Nenhum cliente encontrado', 'Ajuste a busca ou cadastre um novo cliente.'),
];
$scripts_config = crud_config($config);
$scripts_extra = '<script src="' . $caminho_base . '/js/clientes.js"></script>';
?>

<div class="page-header">
    <div>
        <h1>Clientes</h1>
        <span class="page-subtitle">Cadastro e contatos da oficina</span>
    </div>
    <button type="button" class="btn btn-primary" data-crud="novo"><i class="icon-base ti tabler-user-plus me-1"></i>Novo Cliente</button>
</div>

<div class="row g-3 mb-1">
    <?php
    echo crud_kpi('kpiTotal', 'Clientes', 'users', 'primary');
    echo crud_kpi('kpiAtivos', 'Ativos', 'user-check', 'success');
    echo crud_kpi('kpiInativos', 'Suspensos', 'user-off', 'danger');
    echo crud_kpi('kpiCidades', 'Cidades', 'map-pin', 'info');
    ?>
</div>

<div class="card">
    <?php
    $filtros = crud_filtro_select('ordem', [
        'recentes' => 'Mais recentes',
    ], 'Ordenação');
    echo crud_toolbar('Buscar por nome, CPF, telefone ou email...', $filtros);
    echo crud_bulk_bar([
        ['id' => 'ativar', 'rotulo' => 'Ativar', 'icone' => 'user-check', 'tom' => 'outline-info'],
        ['id' => 'suspender', 'rotulo' => 'Suspender', 'icone' => 'user-off', 'tom' => 'outline-secondary'],
        ['id' => 'excluir', 'rotulo' => 'Excluir', 'icone' => 'trash', 'tom' => 'outline-danger'],
    ]);
    echo crud_tabela_abrir([
        ['rotulo' => 'Cliente'],
        ['rotulo' => 'Contato'],
        ['rotulo' => 'CPF'],
        ['rotulo' => 'Endereço'],
        ['rotulo' => 'Veículos'],
        ['rotulo' => 'Ações', 'classe' => 'actions-cell'],
    ]);
    echo crud_rodape();
    ?>
</div>

<template id="tplCliente">
    <tr>
        <td class="check-cell"><input class="form-check-input row-check" type="checkbox"></td>
        <td data-label="Cliente">
            <div class="d-flex align-items-center">
                <div class="avatar avatar-sm me-3"><span class="avatar-initial rounded-circle bg-label-primary cliente-inicial"></span></div>
                <div>
                    <span class="fw-medium text-heading cliente-nome"></span>
                    <br><small class="text-body-secondary cliente-id"></small>
                </div>
            </div>
        </td>
        <td data-label="Contato">
            <div class="cliente-telefone"></div>
            <small class="text-body-secondary cliente-email"></small>
        </td>
        <td data-label="CPF"><span class="cliente-cpf"></span></td>
        <td data-label="Endereço">
            <div class="d-flex flex-column">
                <span class="cliente-endereco"></span>
                <small class="text-body-secondary cliente-cidade"></small>
            </div>
        </td>
        <td data-label="Veículos" class="cliente-veiculos-cell">
            <span class="cliente-veiculos-texto" title=""></span>
        </td>
        <td class="actions-cell">
            <div class="dropdown table-dropdown">
                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do cliente">
                    <i class="icon-base ti tabler-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                    <h6 class="dropdown-header">Ações do cliente</h6>
                    <a class="dropdown-item acao-detalhes" href="#"><i class="icon-base ti tabler-info-circle"></i> Informações</a>
                    <button class="dropdown-item acao-editar" type="button"><i class="icon-base ti tabler-pencil"></i> Editar</button>
                    <button class="dropdown-item text-danger acao-excluir" type="button"><i class="icon-base ti tabler-trash"></i> Excluir</button>
                </div>
            </div>
        </td>
    </tr>
</template>

<?php echo crud_toast('clientesToast'); ?>

<div class="modal fade" id="clienteModal" tabindex="-1" data-crud="modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form data-crud="form">
                <div class="modal-header">
                    <h5 class="modal-title" data-crud="modal-titulo">Novo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" data-crud="modal-erro" hidden></div>
                    <input type="hidden" name="id" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <?php echo campo_icone('nome', 'Nome completo *', 'user', 'text', ['required' => true, 'maxlength' => 120]); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('cpf', 'CPF', 'id', 'text', ['maxlength' => 14, 'oninput' => 'formatarCPF(this)'], '000.000.000-00'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('telefone', 'Telefone', 'phone', 'text', ['maxlength' => 20, 'inputmode' => 'tel', 'oninput' => 'formatarTelefone(this)']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('email', 'E-mail', 'mail', 'email', ['maxlength' => 120]); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('cidade', 'Cidade', 'building', 'text', ['maxlength' => 120]); ?>
                        </div>
                        <div class="col-12">
                            <?php echo campo_icone('endereco', 'Endereço', 'road', 'text', ['maxlength' => 255], 'Rua, número, bairro'); ?>
                        </div>
                        <div class="col-12">
                            <?php echo campo_textarea_icone('observacoes', 'Observações', 'notebook', ['rows' => 3]); ?>
                        </div>
                        <div class="col-12">
                            <?php echo campo_check_icone('ativo', 'Cliente ativo', 'user-check', true, 'clienteAtivo'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i> Salvar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo crud_confirm_modal(); ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
