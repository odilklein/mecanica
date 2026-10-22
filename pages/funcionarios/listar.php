<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/campos.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$titulo_pagina = 'Funcionários';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$config = [
    'apiUrl' => $caminho_base . '/api/funcionarios.php',
    'idCampo' => 'id',
    'singular' => 'Funcionário',
    'plural' => 'funcionários',
    'porPagina' => 15,
    'tplId' => 'tplFuncionario',
    'campoBusca' => 'busca',
    'emptyHtml' => crud_empty_state('users-group', 'Nenhum funcionário encontrado', 'Ajuste a busca ou cadastre um novo funcionário.'),
];
$scripts_config = crud_config($config);
$scripts_extra = '<script src="' . $caminho_base . '/js/funcionarios.js"></script>';
?>

<div class="page-header">
    <div>
        <h1>Funcionários</h1>
        <span class="page-subtitle">Equipe e perfis de acesso</span>
    </div>
    <button type="button" class="btn btn-primary" data-crud="novo"><i class="icon-base ti tabler-user-plus me-1"></i>Novo funcionário</button>
</div>

<div class="row g-3 mb-1">
    <?php
    echo crud_kpi('kpiTotal', 'Funcionários', 'users-group', 'primary');
    echo crud_kpi('kpiAtivos', 'Ativos', 'user-check', 'success');
    echo crud_kpi('kpiInativos', 'Suspensos', 'user-off', 'danger');
    echo crud_kpi('kpiAdmins', 'Administradores', 'shield-lock', 'info');
    ?>
</div>

<div class="card">
    <?php
    $filtros = crud_filtro_select('perfil', [
        'admin' => 'Administrador',
        'secretaria' => 'Secretaria',
        'mecanico_chefe' => 'Mecânico-chefe',
        'mecanico' => 'Mecânico',
    ], 'Todos os perfis');
    $filtros .= crud_filtro_select('status', ['ativo' => 'Ativos', 'inativo' => 'Suspensos'], 'Todos os status');
    echo crud_toolbar('Buscar por nome, e-mail, cargo ou CPF...', $filtros);
    echo crud_bulk_bar([
        ['id' => 'ativar', 'rotulo' => 'Ativar', 'icone' => 'user-check', 'tom' => 'outline-info'],
        ['id' => 'suspender', 'rotulo' => 'Desativar', 'icone' => 'user-off', 'tom' => 'outline-secondary'],
    ]);
    echo crud_tabela_abrir([
        ['rotulo' => 'Funcionário'],
        ['rotulo' => 'Perfil'],
        ['rotulo' => 'Telefone'],
        ['rotulo' => 'Salário'],
        ['rotulo' => 'Admissão'],
        ['rotulo' => 'Status'],
        ['rotulo' => 'Ativo'],
        ['rotulo' => 'Ações', 'classe' => 'actions-cell'],
    ]);
    echo crud_rodape();
    ?>
</div>

<template id="tplFuncionario">
    <tr>
        <td class="check-cell"><input class="form-check-input row-check" type="checkbox"></td>
        <td data-label="Funcionário">
            <div class="d-flex align-items-center">
                <div class="avatar avatar-sm me-3"><span class="avatar-initial rounded-circle bg-label-primary func-inicial"></span></div>
                <div class="d-flex flex-column">
                    <span class="text-heading text-truncate fw-medium func-nome"></span>
                    <small class="func-email"></small>
                </div>
            </div>
        </td>
        <td data-label="Perfil">
            <span class="text-truncate d-flex align-items-center text-heading">
                <span class="badge func-perfil me-1"></span>
                <small class="text-body-secondary func-cargo"></small>
            </span>
        </td>
        <td data-label="Telefone" class="func-telefone"></td>
        <td data-label="Salário" class="func-salario"></td>
        <td data-label="Admissão" class="func-admissao"></td>
        <td data-label="Status"><span class="status-dot"><span class="dot"></span><span class="status-text"></span></span></td>
        <td data-label="Ativo">
            <div class="form-check form-switch">
                <input class="form-check-input toggle-ativo" type="checkbox" role="switch">
            </div>
        </td>
        <td class="actions-cell">
            <div class="dropdown table-dropdown">
                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações">
                    <i class="icon-base ti tabler-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                    <h6 class="dropdown-header">Ações do funcionário</h6>
                    <button class="dropdown-item acao-editar" type="button"><i class="icon-base ti tabler-pencil"></i> Editar</button>
                </div>
            </div>
        </td>
    </tr>
</template>

<?php echo crud_toast('funcToast'); ?>

<div class="modal fade" id="funcModal" tabindex="-1" data-crud="modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form data-crud="form">
                <div class="modal-header">
                    <h5 class="modal-title" data-crud="modal-titulo">Novo Funcionário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" data-crud="modal-erro" hidden></div>
                    <input type="hidden" name="id" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <?php echo campo_icone('nome', 'Nome completo *', 'user', 'text', ['required' => true, 'maxlength' => 100]); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('email', 'E-mail *', 'mail', 'email', ['required' => true, 'maxlength' => 120]); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('cpf', 'CPF', 'id', 'text', ['maxlength' => 14, 'oninput' => 'formatarCPF(this)'], '000.000.000-00'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('telefone', 'Telefone', 'phone', 'text', ['maxlength' => 20, 'inputmode' => 'tel', 'oninput' => 'formatarTelefone(this)']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('data_admissao', 'Admissão', 'calendar', 'date'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo campo_icone('cargo', 'Cargo', 'briefcase', 'text', ['maxlength' => 100], 'Ex.: Mecânico'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php echo campo_icone('salario', 'Salário', 'currency-dollar', 'number', ['step' => '0.01', 'min' => 0], '0,00'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php echo campo_select_icone('tipo', 'Perfil *', 'shield-lock',
                                '<option value="mecanico">Mecânico</option>'
                                . '<option value="mecanico_chefe">Mecânico-chefe</option>'
                                . '<option value="secretaria">Secretaria</option>'
                                . '<option value="admin">Administrador</option>', ['required' => true]); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo campo_icone('senha', 'Senha', 'lock', 'password', ['minlength' => 8, 'autocomplete' => 'new-password'], 'Mín. 8 caracteres'); ?>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <?php echo campo_check_icone('ativo', 'Funcionário ativo', 'user-check', true, 'funcAtivo'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo crud_confirm_modal(); ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
