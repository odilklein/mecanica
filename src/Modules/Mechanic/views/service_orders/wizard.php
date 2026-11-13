<?php
$path = $resource->path();
$clientsJson = json_encode($clients, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$vehiclesJson = json_encode($vehicles ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$priorities = \Modules\Mechanic\Support\ServiceOrderStatus::PRIORITIES;
$ticketUsers = $ticketUsers ?? $mechanics;
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-clipboard-plus"></i>Novo atendimento</h1>
        <span class="page-subtitle">Registre a chegada do cliente em poucos passos</span>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-outline-primary" data-ai-prefill="service_order">
            <i class="icon-base ti tabler-sparkles"></i> Preencher com IA
        </button>
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-list"></i> Ver OS</a>
    </div>
</div>

<form method="POST" action="<?= e($path) ?>" id="formOS">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="bs-stepper vertical wizard-vertical-icons-example os-wizard-stepper" id="osStepper">
                <div class="bs-stepper-header">
                    <div class="step" data-target="#step-cliente">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-circle"><i class="icon-base ti tabler-user icon-md"></i></span>
                            <span class="bs-stepper-label">
                                <span class="bs-stepper-title">Cliente</span>
                                <span class="bs-stepper-subtitle">Quem chegou à oficina</span>
                            </span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-veiculo">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-circle"><i class="icon-base ti tabler-car icon-md"></i></span>
                            <span class="bs-stepper-label">
                                <span class="bs-stepper-title">Veículo</span>
                                <span class="bs-stepper-subtitle">Placa, marca e modelo</span>
                            </span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-servico">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-circle"><i class="icon-base ti tabler-tool icon-md"></i></span>
                            <span class="bs-stepper-label">
                                <span class="bs-stepper-title">Serviços</span>
                                <span class="bs-stepper-subtitle">Prioridade e peças necessárias</span>
                            </span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-equipe">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-circle"><i class="icon-base ti tabler-users-group icon-md"></i></span>
                            <span class="bs-stepper-label">
                                <span class="bs-stepper-title">Equipe</span>
                                <span class="bs-stepper-subtitle">Mecânicos designados</span>
                            </span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-atendimento">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-circle"><i class="icon-base ti tabler-clipboard-text icon-md"></i></span>
                            <span class="bs-stepper-label">
                                <span class="bs-stepper-title">OS</span>
                                <span class="bs-stepper-subtitle">Queixa, observações e conversa</span>
                            </span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-revisao">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-circle"><i class="icon-base ti tabler-checkup-list icon-md"></i></span>
                            <span class="bs-stepper-label">
                                <span class="bs-stepper-title">Revisão</span>
                                <span class="bs-stepper-subtitle">Confira e crie a OS</span>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="bs-stepper-content">
                    <!-- ETAPA 1: CLIENTE -->
                    <div id="step-cliente" class="content">
                        <div class="content-header mb-4">
                            <h6 class="mb-0">Dados do cliente</h6>
                            <small>Busque um cliente cadastrado ou preencha os dados de um novo.</small>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label" for="client_id">Cliente cadastrado</label>
                                <select class="select2 form-select" id="client_id" name="client_id" data-placeholder="Buscar cliente por nome ou telefone" data-allow-clear="true">
                                    <option value=""></option>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name'] . ($c['phone'] ? ' — ' . $c['phone'] : '') . ($c['city'] ? ' · ' . $c['city'] : '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Deixe em branco para cadastrar um novo cliente.</div>
                            </div>

                            <div class="col-12" id="newClientFields">
                                <hr class="my-2">
                                <h6 class="mb-3"><i class="icon-base ti tabler-user-plus me-1"></i>Novo cliente</h6>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label" for="client_name">Nome *</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-user"></i></span>
                                            <input type="text" class="form-control" id="client_name" name="client_name" maxlength="120" autocomplete="off" placeholder="Nome completo">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="client_phone">Telefone</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-phone"></i></span>
                                            <input type="text" class="form-control" id="client_phone" name="client_phone" maxlength="20" data-mask="phone" placeholder="(11) 98888-7777">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label" for="client_email">E-mail</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-mail"></i></span>
                                            <input type="email" class="form-control" id="client_email" name="client_email" maxlength="120" autocomplete="off" placeholder="cliente@email.com">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="zip">CEP</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-map-pin"></i></span>
                                            <input type="text" class="form-control" id="zip" name="client_zip" maxlength="10" data-mask="cep" placeholder="00000-000">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label" for="address">Rua</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-road"></i></span>
                                            <input type="text" class="form-control" id="address" name="client_address" maxlength="200" placeholder="Rua">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="address_number">Número</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-hash"></i></span>
                                            <input type="text" class="form-control" id="address_number" name="client_address_number" maxlength="20" placeholder="Ex.: 123">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="neighborhood">Bairro</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-building-community"></i></span>
                                            <input type="text" class="form-control" id="neighborhood" name="client_neighborhood" maxlength="80" placeholder="Centro">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="city">Cidade</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-building"></i></span>
                                            <input type="text" class="form-control" id="city" name="client_city" maxlength="80" placeholder="São Paulo">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="state">UF</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-map"></i></span>
                                            <input type="text" class="form-control" id="state" name="client_state" maxlength="2" placeholder="SP">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn btn-label-secondary btn-prev" disabled>
                                <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-next">
                                <span class="align-middle d-sm-inline-block d-none me-sm-2">Próximo</span>
                                <i class="icon-base ti tabler-arrow-right icon-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ETAPA 2: VEÍCULO -->
                    <div id="step-veiculo" class="content">
                        <div class="content-header mb-4">
                            <h6 class="mb-0">Dados do veículo</h6>
                            <small>Selecione um veículo do cliente ou cadastre um novo pela FIPE.</small>
                        </div>

                        <div class="row g-4">
                            <div class="col-12" id="vehicleExistingBox" hidden>
                                <label class="form-label" for="vehicle_id">Veículo do cliente</label>
                                <select class="select2 form-select" id="vehicle_id" name="vehicle_id" data-placeholder="Buscar veículo por placa ou modelo" data-allow-clear="true">
                                    <option value=""></option>
                                </select>
                                <div class="form-text">Selecione um veículo já cadastrado ou deixe em branco para cadastrar um novo.</div>
                                <hr class="my-4">
                            </div>

                            <div class="col-12" id="newVehicleFields">
                                <h6 class="mb-3"><i class="icon-base ti tabler-car me-1"></i>Novo veículo</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="fipe_brand_code">Marca</label>
                                        <select class="select2 form-select" id="fipe_brand_code" name="fipe_brand_code" data-placeholder="Buscar marca">
                                            <option value=""></option>
                                            <?php foreach ($brands as $b): ?>
                                            <option value="<?= e($b['code']) ?>"><?= e($b['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="fipe_model_code">Modelo</label>
                                        <select class="select2 form-select" id="fipe_model_code" name="fipe_model_code" data-placeholder="Buscar modelo">
                                            <option value=""></option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="fipe_year_code">Ano</label>
                                        <select class="select2 form-select" id="fipe_year_code" name="fipe_year_code" data-placeholder="Buscar ano">
                                            <option value=""></option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="fipe_value_display">Valor FIPE</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-currency-real"></i></span>
                                            <input type="text" class="form-control" id="fipe_value_display" readonly placeholder="—">
                                        </div>
                                        <input type="hidden" id="fipe_value" name="fipe_value" value="">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="plate">Placa *</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-license"></i></span>
                                            <input type="text" class="form-control" id="plate" name="plate" maxlength="10" data-mask="plate" placeholder="ABC-1234" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="color">Cor</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-palette"></i></span>
                                            <input type="text" class="form-control" id="color" name="color" maxlength="30" placeholder="Ex.: Prata">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn btn-label-secondary btn-prev">
                                <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-next">
                                <span class="align-middle d-sm-inline-block d-none me-sm-2">Próximo</span>
                                <i class="icon-base ti tabler-arrow-right icon-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ETAPA 3: SERVIÇO -->
                    <div id="step-servico" class="content">
                        <div class="content-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <h6 class="mb-0">Serviços e peças</h6>
                                <small>Defina a prioridade e as peças necessárias. Queixa e observações internas ficam na etapa <strong>OS</strong>.</small>
                            </div>
                            <div class="os-wizard-priority">
                                <label class="form-label" for="priority">Prioridade</label>
                                <select class="select2 select2-priority form-select" id="priority" name="priority" data-placeholder="Selecione a prioridade">
                                    <option value=""></option>
                                    <?php foreach ($priorities as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $key === 'normal' ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="card border-primary border mb-0">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="icon-base ti tabler-package icon-md text-primary"></i>
                                    <div>
                                        <h6 class="mb-0">Peças necessárias</h6>
                                        <small class="text-body-secondary">Busque no catálogo ou digite uma peça manual</small>
                                    </div>
                                </div>
                                <span class="badge bg-label-primary" id="osPartsCount">0</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label" for="osPartSearch">Buscar peça no catálogo</label>
                                        <select class="form-select part-autocomplete" id="osPartSearch" data-placeholder="Buscar por nome, marca ou código..."></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="osPartQty">Quantidade</label>
                                        <input type="number" class="form-control" id="osPartQty" value="1" min="1" max="99" step="1" aria-label="Quantidade">
                                    </div>
                                </div>

                                <div class="input-group mt-3">
                                    <input type="text" class="form-control" id="osPartManual" placeholder="Ou digite uma peça manual...">
                                    <button type="button" class="btn btn-outline-primary" id="osPartManualAdd" aria-label="Adicionar peça manual"><i class="icon-base ti tabler-plus"></i></button>
                                </div>

                                <div class="dynamic-list mt-3" data-list="parts">
                                    <div class="dynamic-list-items"></div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="parts_json" id="parts_json" value="[]">

                        <div class="wizard-actions">
                            <button type="button" class="btn btn-label-secondary btn-prev">
                                <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-next">
                                <span class="align-middle d-sm-inline-block d-none me-sm-2">Próximo</span>
                                <i class="icon-base ti tabler-arrow-right icon-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ETAPA 4: EQUIPE -->
                    <div id="step-equipe" class="content">
                        <div class="content-header mb-4">
                            <h6 class="mb-0">Funcionários designados</h6>
                            <small>Selecione quem vai trabalhar nesta OS.</small>
                        </div>

                        <div class="row g-2">
                            <?php foreach ($mechanics as $m): ?>
                            <div class="col-md-4">
                                <label class="card p-3 d-flex flex-row align-items-center gap-2 mb-0" style="cursor:pointer">
                                    <input type="checkbox" name="mechanics[]" value="<?= (int) $m['id'] ?>" class="form-check-input mt-0">
                                    <span class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-primary"><?= e(strtoupper(substr($m['name'], 0, 1))) ?></span></span>
                                    <span>
                                        <span class="d-block fw-medium"><?= e($m['name']) ?></span>
                                        <small class="text-body-secondary"><?= e(roleLabel($m['role'])) ?></small>
                                    </span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$mechanics): ?><p class="text-body-secondary">Nenhum mecânico disponível.</p><?php endif; ?>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label" for="lead_mechanic_id">Mecânico responsável</label>
                                <select class="select2 form-select" id="lead_mechanic_id" name="lead_mechanic_id" data-placeholder="Selecione o responsável">
                                    <option value=""></option>
                                    <?php foreach ($mechanics as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> — <?= e(roleLabel($m['role'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn btn-label-secondary btn-prev">
                                <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-next">
                                <span class="align-middle d-sm-inline-block d-none me-sm-2">Próximo</span>
                                <i class="icon-base ti tabler-arrow-right icon-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ETAPA 5: OS -->
                    <div id="step-atendimento" class="content">
                        <div class="content-header mb-4">
                            <h6 class="mb-0">OS</h6>
                            <small>Informações da OS e conversa com a equipe. O ticket é criado automaticamente com o resumo da OS.</small>
                        </div>

                        <div class="card mb-0">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label" for="ticket_subject">Assunto</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text"><i class="icon-base ti tabler-messages"></i></span>
                                            <input type="text" class="form-control" id="ticket_subject" name="ticket_subject" maxlength="200" placeholder="Ex.: Acompanhamento do atendimento">
                                        </div>
                                        <div class="form-text">Em branco, usamos “OS #0000 - Cliente”.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="ticket_assigned_to">Responsável</label>
                                        <select class="select2 form-select" id="ticket_assigned_to" name="ticket_assigned_to" data-placeholder="Mesmo responsável da OS">
                                            <option value=""></option>
                                            <?php foreach ($ticketUsers as $u): ?>
                                            <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> — <?= e(roleLabel($u['role'])) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Em branco, usa o mecânico responsável.</div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="initial_complaint">Queixa do cliente</label>
                                        <div class="d-flex gap-2 align-items-start">
                                            <textarea class="form-control" id="initial_complaint" name="initial_complaint" rows="3" placeholder="Ex.: Barulho no motor ao acelerar"></textarea>
                                            <?php if (!empty($ticketAudioEnabled)): ?>
                                            <button type="button" class="btn btn-icon btn-text-secondary ticket-audio-btn"
                                                    data-ticket-audio data-target="#initial_complaint"
                                                    data-csrf="<?= e($csrf) ?>" data-endpoint="/api/transcribe"
                                                    title="Gravar áudio e transcrever" aria-label="Gravar áudio e transcrever">
                                                <i class="icon-base ti tabler-microphone"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="internal_notes">Observações internas</label>
                                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="3" placeholder="Não visíveis ao cliente"></textarea>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn btn-label-secondary btn-prev">
                                <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-next">
                                <span class="align-middle d-sm-inline-block d-none me-sm-2">Próximo</span>
                                <i class="icon-base ti tabler-arrow-right icon-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ETAPA 6: REVISÃO -->
                    <div id="step-revisao" class="content">
                        <div class="content-header mb-4">
                            <h6 class="mb-0">Revise os dados</h6>
                            <small>Confira antes de criar o atendimento.</small>
                        </div>

                        <div class="card mb-0">
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">Cliente</small>
                                        <span class="fw-medium" id="reviewCliente">—</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">Veículo</small>
                                        <span class="fw-medium" id="reviewVeiculo">—</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">Serviço</small>
                                        <span class="fw-medium" id="reviewServico">—</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">Prioridade</small>
                                        <span class="fw-medium" id="reviewPrioridade">—</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">Equipe</small>
                                        <span class="fw-medium" id="reviewEquipe">—</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">OS</small>
                                        <span class="fw-medium" id="reviewTicket">—</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-body-secondary d-block">Peças</small>
                                        <span class="fw-medium" id="reviewPecas">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0 small">
                            <i class="icon-base ti tabler-info-circle"></i>
                            Ao confirmar, a OS é criada, os mecânicos selecionados são notificados e a OS é aberta.
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn btn-label-secondary btn-prev">
                                <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-base ti tabler-check me-sm-2"></i> Criar OS
                            </button>
                        </div>
                    </div>
                </div>
            </div>
</form>

<script>
window.WIZARD_CLIENTS = <?= $clientsJson ?>;
window.WIZARD_VEHICLES = <?= $vehiclesJson ?>;
</script>
