<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/campos.php';
require_once __DIR__ . '/../../includes/fipe.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

// Catálogo FIPE: alimenta as opções de marca do wizard.
fipe_garantir_schema($conn);
$fipe_marcas = $conn->query("SELECT id, nome_exibicao FROM fipe_marcas ORDER BY nome_exibicao")->fetchAll();
$opcoes_marca = '<option value="">Selecione a marca</option>';
foreach ($fipe_marcas as $m) {
    $opcoes_marca .= '<option value="' . (int) $m['id'] . '">' . esc($m['nome_exibicao']) . '</option>';
}

$mecanicos = $conn->query("SELECT id, nome, tipo FROM usuarios WHERE ativo = 1 AND tipo IN ('mecanico', 'mecanico_chefe') ORDER BY nome")->fetchAll();
$sugestoes_tarefas = ['Diagnóstico completo', 'Troca de óleo e filtros', 'Revisão dos freios', 'Alinhamento e balanceamento', 'Troca da correia dentada', 'Inspeção da suspensão', 'Verificação de vazamentos'];
$sugestoes_pecas = ['Óleo do motor | 1', 'Filtro de óleo | 1', 'Pastilhas de freio dianteiras | 1', 'Filtro de ar | 1', 'Correia dentada | 1'];

$titulo_pagina = 'Novo atendimento';
$head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">'
    . '<link rel="stylesheet" href="' . $caminho_base . '/assets/vendor/libs/bs-stepper/bs-stepper.css">';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$os_api_url = $caminho_base . '/api/ordens_servico.php';
$scripts_extra  = '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>';
$scripts_extra .= '<script src="' . $caminho_base . '/assets/vendor/libs/bs-stepper/bs-stepper.js"></script>';
$scripts_extra .= '<script>window.VeiculoFipeConfig = {marcaId:0,baseId:0,modeloId:0,anoAtual:0,valorAtualFormatado:""};</script>';
$scripts_extra .= '<script>window.OrdemServicoApi = ' . json_encode($os_api_url) . ';</script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/veiculo-fipe.js"></script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/os-wizard.js"></script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/viacep.js"></script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/os-stepper.js"></script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/ordem-nova.js"></script>';

$etapas = [
    ['alvo' => '#etapa-cliente', 'icone' => 'user', 'titulo' => 'Cliente', 'subtitulo' => 'Buscar ou cadastrar'],
    ['alvo' => '#etapa-veiculo', 'icone' => 'car', 'titulo' => 'Veículo', 'subtitulo' => 'Dados e FIPE'],
    ['alvo' => '#etapa-servico', 'icone' => 'stethoscope', 'titulo' => 'Serviço', 'subtitulo' => 'Queixa e tarefas'],
    ['alvo' => '#etapa-equipe', 'icone' => 'users-group', 'titulo' => 'Equipe', 'subtitulo' => 'Responsáveis'],
    ['alvo' => '#etapa-revisao', 'icone' => 'clipboard-check', 'titulo' => 'Revisão', 'subtitulo' => 'Confirmar e criar'],
];

/** Botões de navegação no rodapé de cada etapa (padrão da referência). */
function botoes_navegacao(bool $primeira = false, bool $ultima = false): string
{
    $prev = $primeira
        ? '<button class="btn btn-label-secondary btn-prev" disabled type="button">'
            . '<i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>'
            . '<span class="align-middle d-sm-inline-block d-none">Anterior</span></button>'
        : '<button class="btn btn-label-secondary btn-prev" type="button">'
            . '<i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>'
            . '<span class="align-middle d-sm-inline-block d-none">Anterior</span></button>';

    $next = $ultima
        ? '<button class="btn btn-primary btn-submit" type="submit">'
            . '<span class="align-middle d-sm-inline-block d-none me-sm-2">Criar OS</span>'
            . '<i class="icon-base ti tabler-check icon-xs"></i></button>'
        : '<button class="btn btn-primary btn-next" type="button">'
            . '<span class="align-middle d-sm-inline-block d-none me-sm-2">Próximo</span>'
            . '<i class="icon-base ti tabler-arrow-right icon-xs"></i></button>';

    return '<div class="wizard-nav">' . $prev . $next . '</div>';
}
?>

<div class="page-header">
    <div>
        <h1>Novo atendimento</h1>
        <span class="page-subtitle">Registre a chegada do cliente em poucos passos</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary"><i class="icon-base ti tabler-list"></i> Ver OS</a>
</div>

<form id="formOS" novalidate>
    <div class="bs-stepper vertical wizard-vertical-icons-example mt-2" id="osStepper">

        <!-- ========== CABEÇALHO DAS ETAPAS ========== -->
        <div class="bs-stepper-header">
            <?php foreach ($etapas as $i => $etapa): ?>
                <?php if ($i > 0): ?><div class="line"></div><?php endif; ?>
                <div class="step" data-target="<?php echo esc($etapa['alvo']); ?>">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-circle"><i class="icon-base ti tabler-<?php echo esc($etapa['icone']); ?> icon-md"></i></span>
                        <span class="bs-stepper-label">
                            <span class="bs-stepper-title"><?php echo esc($etapa['titulo']); ?></span>
                            <span class="bs-stepper-subtitle"><?php echo esc($etapa['subtitulo']); ?></span>
                        </span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ========== CONTEÚDO DAS ETAPAS ========== -->
        <div class="bs-stepper-content wizard-content">

            <!-- ===== ETAPA 1: CLIENTE ===== -->
            <div id="etapa-cliente" class="content">
                <div class="content-header mb-4">
                    <h6 class="mb-0">Dados do Cliente</h6>
                    <small>Busque um cliente existente para agilizar o atendimento</small>
                </div>
                <input type="hidden" name="cliente_id" id="cliente_id" value="">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="nome">Nome do cliente *</label>
                        <div class="position-relative" id="clienteBuscaBox">
                            <input type="text" class="form-control" id="nome" name="nome" maxlength="120"
                                   required autocomplete="off" role="combobox" aria-expanded="false"
                                   aria-autocomplete="list" aria-controls="clienteSugestoes"
                                   placeholder="Digite o nome ou telefone do cliente">
                            <i class="icon-base ti tabler-search busca-icone"></i>
                            <div class="busca-resultados" id="clienteSugestoes" role="listbox" hidden></div>
                        </div>
                        <div class="form-text">Já está na base? Selecione para trazer telefone e veículos.</div>
                    </div>
                    <div class="col-md-4">
                        <?php echo campo_icone('telefone', 'Telefone', 'phone', 'text', ['maxlength' => 20, 'inputmode' => 'tel', 'oninput' => 'formatarTelefone(this)']); ?>
                    </div>
                </div>

                <hr class="my-4">

                <div class="content-header mb-3">
                    <h6 class="mb-0">Endereço <small class="text-body-secondary fw-normal">(opcional)</small></h6>
                    <small>Preencha o CEP para importar o endereço automaticamente.</small>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <?php echo campo_icone('cep', 'CEP', 'map-pin', 'text', ['maxlength' => 9, 'oninput' => 'formatarCep(this)'], '00000-000'); ?>
                    </div>
                    <div class="col-md-8">
                        <?php echo campo_icone('endereco', 'Rua', 'road', 'text', ['maxlength' => 200]); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo campo_icone('numero', 'Número', 'hash', 'text', ['maxlength' => 10]); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo campo_icone('complemento', 'Complemento', 'building-community', 'text', ['maxlength' => 120]); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo campo_icone('bairro', 'Bairro', 'home', 'text', ['maxlength' => 120]); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo campo_icone('cidade', 'Cidade', 'building', 'text', ['maxlength' => 120]); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo campo_icone('uf', 'Estado', 'map', 'text', ['maxlength' => 2, 'class' => 'text-uppercase']); ?>
                    </div>
                </div>
                <?php echo botoes_navegacao(true); ?>
            </div>

            <!-- ===== ETAPA 2: VEÍCULO ===== -->
            <div id="etapa-veiculo" class="content">
                <div class="content-header mb-4">
                    <h6 class="mb-0">Dados do Veículo</h6>
                    <small>Selecione um veículo já cadastrado ou use a busca por marca e modelo</small>
                </div>
                <input type="hidden" name="veiculo_id" id="veiculo_id" value="">
                <div class="row g-3">
                    <div class="col-12" id="veiculoExistenteBox" hidden>
                        <?php
                        echo campo_select_icone('veiculo_existente', 'Veículos deste cliente', 'car',
                            '<option value="">Selecione um veículo cadastrado</option>');
                        ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="marca_id">Marca</label>
                        <select class="form-select" id="marca_id" name="marca_id" data-placeholder="Marca">
                            <?php echo $opcoes_marca; ?>
                        </select>
                        <input type="hidden" name="marca_nome" id="marca_nome" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="modelo_base_id">Modelo</label>
                        <select class="form-select" id="modelo_base_id" name="modelo_base_id" data-placeholder="Modelo">
                            <option value="">Selecione a marca primeiro</option>
                        </select>
                        <input type="hidden" name="modelo_nome" id="modelo_nome" value="">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="modelo_id">Versão</label>
                        <select class="form-select" id="modelo_id" name="modelo_id" data-placeholder="Versão">
                            <option value="">Opcional</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="ano">Ano</label>
                        <select class="form-select" id="ano" name="ano" data-placeholder="Ano">
                            <option value="">Opcional</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info mb-0 py-2 d-none" id="fipeBox">
                            <i class="icon-base ti tabler-currency-dollar me-1"></i>
                            Valor FIPE de referência: <strong id="valorFipeTexto">—</strong>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <?php echo campo_icone('placa', 'Placa', 'license', 'text', ['maxlength' => 10, 'class' => 'text-uppercase', 'oninput' => 'formatarPlaca(this)'], 'ABC-1234'); ?>
                    </div>
                    <div class="col-md-3">
                        <?php echo campo_icone('cor', 'Cor', 'palette', 'text', ['maxlength' => 30, 'list' => 'listaCores'], 'Preto'); ?>
                        <datalist id="listaCores">
                            <?php foreach (cores_veiculo() as $cor_nome => $_hex): ?>
                            <option value="<?php echo esc($cor_nome); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <?php echo campo_icone('km_atual', 'KM Atual', 'gauge', 'number', ['min' => 0], '0'); ?>
                    </div>
                    <div class="col-md-3">
                        <?php echo campo_icone('chassi', 'Chassi', 'fingerprint', 'text', ['maxlength' => 30], 'Opcional'); ?>
                    </div>
                </div>
                <div class="alert alert-secondary mt-2 mb-0 py-2 hidden" id="veiculoInfo">
                    <i class="icon-base ti tabler-info-circle me-1"></i>
                    Veículo cadastrado sem vínculo com a FIPE: <strong id="veiculoInfoTexto"></strong>.
                    Selecione a marca e o modelo abaixo para vincular.
                </div>
                <?php echo botoes_navegacao(); ?>
            </div>

            <!-- ===== ETAPA 3: SERVIÇO ===== -->
            <div id="etapa-servico" class="content">
                <div class="content-header mb-4">
                    <h6 class="mb-0">O que precisa ser feito</h6>
                    <small>Descreva a queixa e detalhe as tarefas e peças do trabalho</small>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <?php echo campo_select_icone('prioridade', 'Prioridade', 'flag',
                            '<option value="baixa">Baixa</option>'
                            . '<option value="normal" selected>Normal</option>'
                            . '<option value="alta">Alta</option>'
                            . '<option value="urgente">Urgente</option>'); ?>
                    </div>
                    <div class="col-md-8">
                        <?php echo campo_textarea_icone('queixa_inicial', 'Queixa do cliente *', 'message-circle',
                            ['required' => true, 'maxlength' => 4000, 'rows' => 3], 'Ex.: Barulho no motor ao acelerar'); ?>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <?php echo campo_textarea_icone('observacoes_internas', 'Observações internas', 'notebook',
                            ['maxlength' => 4000, 'rows' => 2], 'Não visíveis ao cliente'); ?>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <!-- Coluna 1: Tarefas -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="icon-base ti tabler-list-check me-2"></i> Tarefas do Trabalho
                            </div>
                            <div class="card-body">
                                <div class="form-text mb-2">Arraste para reordenar; o texto é editável.</div>
                                <div data-dynamic-list data-dynamic-list-tarefas data-lista-simples="1" data-ordenavel="1">
                                    <input type="hidden" name="tarefas" value="">
                                    <div class="suggestions">
                                        <div class="suggestions-header">
                                            <span class="suggestions-label"><i class="icon-base ti tabler-sparkles"></i> Sugestões rápidas</span>
                                            <button type="button" class="suggestions-toggle" aria-expanded="false" title="Mostrar sugestões"><i class="icon-base ti tabler-dots"></i></button>
                                        </div>
                                        <div class="suggestion-chips" hidden>
                                            <?php foreach ($sugestoes_tarefas as $s): ?>
                                            <button type="button" class="suggestion-chip" data-sugestao="<?php echo esc($s); ?>"><?php echo esc($s); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="dynamic-list-items" data-ordenavel="1"></div>
                                    <div class="dynamic-list-input mt-2">
                                        <input class="form-control" placeholder="Digite a tarefa e pressione Enter">
                                        <button type="button" class="btn-add" title="Adicionar tarefa"><i class="icon-base ti tabler-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 2: Peças -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="icon-base ti tabler-package me-2"></i> Peças Necessárias
                            </div>
                            <div class="card-body">
                                <div class="form-text mb-2">Arraste para reordenar; descrição e quantidade são editáveis.</div>
                                <div data-dynamic-list data-dynamic-list-pecas data-ordenavel="1">
                                    <input type="hidden" name="pecas" value="">
                                    <div class="suggestions">
                                        <div class="suggestions-header">
                                            <span class="suggestions-label"><i class="icon-base ti tabler-sparkles"></i> Sugestões rápidas</span>
                                            <button type="button" class="suggestions-toggle" aria-expanded="false" title="Mostrar sugestões"><i class="icon-base ti tabler-dots"></i></button>
                                        </div>
                                        <div class="suggestion-chips" hidden>
                                            <?php foreach ($sugestoes_pecas as $p): ?>
                                            <button type="button" class="suggestion-chip" data-sugestao="<?php echo esc($p); ?>"><?php echo esc($p); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="dynamic-list-items" data-ordenavel="1"></div>
                                    <div class="dynamic-list-input mt-2">
                                        <input class="form-control" placeholder="Ex.: Radiador">
                                        <button type="button" class="btn-add" title="Adicionar peça"><i class="icon-base ti tabler-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php echo botoes_navegacao(); ?>
            </div>

            <!-- ===== ETAPA 4: EQUIPE ===== -->
            <div id="etapa-equipe" class="content">
                <div class="content-header mb-4">
                    <h6 class="mb-0">Funcionários Designados</h6>
                    <small>Selecione quem vai trabalhar nesta OS</small>
                </div>
                <div class="select-cards-grid">
                    <?php foreach ($mecanicos as $mecanico): ?>
                    <div class="select-card-employee" tabindex="0" role="button" aria-pressed="false">
                        <input type="checkbox" name="funcionarios_ids[]" value="<?php echo (int) $mecanico['id']; ?>" class="d-none">
                        <div class="emp-avatar"><?php echo strtoupper(substr($mecanico['nome'], 0, 1)); ?></div>
                        <div class="emp-info">
                            <div class="emp-name"><?php echo esc($mecanico['nome']); ?></div>
                            <div class="emp-role"><?php echo esc(ucfirst(str_replace('_', ' ', $mecanico['tipo']))); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$mecanicos): ?>
                    <p class="text-body-secondary">Nenhum mecânico disponível.</p>
                    <?php endif; ?>
                </div>
                <?php echo botoes_navegacao(); ?>
            </div>

            <!-- ===== ETAPA 5: REVISÃO ===== -->
            <div id="etapa-revisao" class="content">
                <div class="content-header mb-4">
                    <h6 class="mb-0">Revise os dados</h6>
                    <small>Confira as informações antes de abrir o atendimento</small>
                </div>
                <div class="review-grid">
                    <div class="review-section" data-ir-pra="0">
                        <span class="review-section-title"><i class="icon-base ti tabler-user"></i> Cliente</span>
                        <span class="review-section-body" id="reviewCliente">—</span>
                        <span class="review-section-edit"><i class="icon-base ti tabler-pencil"></i> Editar</span>
                    </div>
                    <div class="review-section" data-ir-pra="1">
                        <span class="review-section-title"><i class="icon-base ti tabler-car"></i> Veículo</span>
                        <span class="review-section-body" id="reviewVeiculo">—</span>
                        <span class="review-section-edit"><i class="icon-base ti tabler-pencil"></i> Editar</span>
                    </div>
                    <div class="review-section" data-ir-pra="2">
                        <span class="review-section-title"><i class="icon-base ti tabler-stethoscope"></i> Serviço</span>
                        <span class="review-section-body" id="reviewServico">—</span>
                        <span class="review-section-edit"><i class="icon-base ti tabler-pencil"></i> Editar</span>
                    </div>
                    <div class="review-section" data-ir-pra="3">
                        <span class="review-section-title"><i class="icon-base ti tabler-users-group"></i> Equipe & Peças</span>
                        <span class="review-section-body" id="reviewEquipe">—</span>
                        <span class="review-section-edit"><i class="icon-base ti tabler-pencil"></i> Editar</span>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 small d-flex align-items-center gap-2">
                    <i class="icon-base ti tabler-info-circle"></i>
                    <span>Ao confirmar, a OS será criada e os mecânicos selecionados serão notificados.</span>
                </div>
                <?php echo botoes_navegacao(false, true); ?>
            </div>

        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>