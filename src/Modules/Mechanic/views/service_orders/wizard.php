<?php
$path = $resource->path();
$clientsJson = json_encode($clients, JSON_UNESCAPED_UNICODE);
$vehiclesJson = json_encode($vehicles ?? [], JSON_UNESCAPED_UNICODE);
?>
<link rel="stylesheet" href="/assets/vendor/libs/bs-stepper/bs-stepper.css">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-clipboard-plus"></i>Novo atendimento</h1>
        <span class="page-subtitle">Registre a chegada do cliente em poucos passos</span>
    </div>
    <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-list"></i> Ver OS</a>
</div>

<form method="POST" action="<?= e($path) ?>" id="formOS">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="bs-stepper" id="osStepper">
        <div class="bs-stepper-header">
            <div class="step" data-target="#step-cliente"><button type="button" class="step-trigger"><span class="bs-stepper-circle">1</span><span class="bs-stepper-label">Cliente</span></button></div>
            <div class="line"></div>
            <div class="step" data-target="#step-veiculo"><button type="button" class="step-trigger"><span class="bs-stepper-circle">2</span><span class="bs-stepper-label">Veículo</span></button></div>
            <div class="line"></div>
            <div class="step" data-target="#step-servico"><button type="button" class="step-trigger"><span class="bs-stepper-circle">3</span><span class="bs-stepper-label">Serviço</span></button></div>
            <div class="line"></div>
            <div class="step" data-target="#step-equipe"><button type="button" class="step-trigger"><span class="bs-stepper-circle">4</span><span class="bs-stepper-label">Equipe</span></button></div>
            <div class="line"></div>
            <div class="step" data-target="#step-revisao"><button type="button" class="step-trigger"><span class="bs-stepper-circle">5</span><span class="bs-stepper-label">Revisão</span></button></div>
        </div>

        <div class="bs-stepper-content">
            <!-- ETAPA 1: CLIENTE -->
            <div id="step-cliente" class="content">
                <div class="content-header mb-4"><h6 class="mb-0">Dados do Cliente</h6><small>Busque um cliente existente ou preencha um novo</small></div>
                <input type="hidden" name="client_id" id="client_id" value="">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="client_name">Nome do cliente *</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="client_name" name="client_name" maxlength="120" autocomplete="off" placeholder="Digite o nome ou telefone do cliente">
                            <div class="list-group position-absolute w-100 shadow-sm" id="clientSuggestions" style="z-index:20" hidden></div>
                        </div>
                        <div class="form-text">Já está na base? Selecione para trazer os veículos.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="client_phone">Telefone</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-phone"></i></span>
                            <input type="text" class="form-control" id="client_phone" name="client_phone" maxlength="20" data-mask="phone" placeholder="(11) 98888-7777"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="client_zip">CEP</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-map-pin"></i></span>
                            <input type="text" class="form-control" id="zip" name="client_zip" maxlength="10" data-mask="cep" placeholder="00000-000"></div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="client_address">Rua</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-road"></i></span>
                            <input type="text" class="form-control" id="address" name="client_address" maxlength="200" placeholder="Rua"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="client_address_number">Número</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-hash"></i></span>
                            <input type="text" class="form-control" id="address_number" name="client_address_number" maxlength="20" placeholder="Ex.: 123"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="client_neighborhood">Bairro</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-building-community"></i></span>
                            <input type="text" class="form-control" id="neighborhood" name="client_neighborhood" maxlength="80" placeholder="Centro"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="client_city">Cidade</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-building"></i></span>
                            <input type="text" class="form-control" id="city" name="client_city" maxlength="80" placeholder="São Paulo"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="client_state">UF</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-map"></i></span>
                            <input type="text" class="form-control" id="state" name="client_state" maxlength="2" placeholder="SP"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-label-secondary" disabled>Anterior</button>
                    <button type="button" class="btn btn-primary" onclick="OSWizard.next()">Próximo</button>
                </div>
            </div>

            <!-- ETAPA 2: VEÍCULO -->
            <div id="step-veiculo" class="content">
                <div class="content-header mb-4"><h6 class="mb-0">Dados do Veículo</h6><small>Selecione um veículo do cliente ou informe a marca/modelo pela FIPE</small></div>
                <input type="hidden" name="vehicle_id" id="vehicle_id" value="">
                <div class="row g-3">
                    <div class="col-12" id="vehicleExistingBox" hidden>
                        <label class="form-label" for="vehicle_existing">Veículos deste cliente</label>
                        <select class="form-select" id="vehicle_existing"><option value="">Selecione um veículo cadastrado</option></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fipe_brand_code">Marca *</label>
                        <select class="form-select" id="fipe_brand_code" name="fipe_brand_code"><option value=""></option>
                            <?php foreach ($brands as $b): ?><option value="<?= e($b['code']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fipe_model_code">Modelo *</label>
                        <select class="form-select" id="fipe_model_code" name="fipe_model_code"><option value=""></option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fipe_year_code">Ano *</label>
                        <select class="form-select" id="fipe_year_code" name="fipe_year_code"><option value=""></option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fipe_value_display">Valor FIPE</label>
                        <input type="text" class="form-control" id="fipe_value_display" readonly placeholder="—">
                        <input type="hidden" id="fipe_value" name="fipe_value" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="plate">Placa *</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-license"></i></span>
                            <input type="text" class="form-control" id="plate" name="plate" maxlength="10" data-mask="plate" placeholder="ABC-1234"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="color">Cor</label>
                        <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-palette"></i></span>
                            <input type="text" class="form-control" id="color" name="color" maxlength="30" placeholder="Ex.: Prata"></div>
                    </div>
                </div>
                <input type="hidden" name="brand" id="brand" value="">
                <input type="hidden" name="model" id="model" value="">
                <input type="hidden" name="year" id="year" value="">
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-label-secondary" onclick="OSWizard.prev()">Anterior</button>
                    <button type="button" class="btn btn-primary" onclick="OSWizard.next()">Próximo</button>
                </div>
            </div>

            <!-- ETAPA 3: SERVIÇO -->
            <div id="step-servico" class="content">
                <div class="content-header mb-4"><h6 class="mb-0">O que precisa ser feito</h6><small>Descreva a queixa e detalhe as tarefas e peças</small></div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="priority">Prioridade</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="low">Baixa</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="initial_complaint">Queixa do cliente *</label>
                        <textarea class="form-control" id="initial_complaint" name="initial_complaint" rows="2" placeholder="Ex.: Barulho no motor ao acelerar"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="internal_notes">Observações internas</label>
                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2" placeholder="Não visíveis ao cliente"></textarea>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="card h-100"><div class="card-header"><i class="icon-base ti tabler-list-check me-2"></i>Tarefas</div><div class="card-body">
                            <div class="dynamic-list" data-list="tasks">
                                <div class="dynamic-list-items"></div>
                                <div class="input-group mt-2">
                                    <input class="form-control" placeholder="Digite a tarefa e Enter">
                                    <button type="button" class="btn btn-outline-primary btn-add"><i class="icon-base ti tabler-plus"></i></button>
                                </div>
                            </div>
                        </div></div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100"><div class="card-header"><i class="icon-base ti tabler-package me-2"></i>Peças necessárias</div><div class="card-body">
                            <div class="dynamic-list" data-list="parts">
                                <div class="dynamic-list-items"></div>
                                <div class="input-group mt-2">
                                    <input class="form-control" placeholder="Ex.: Pastilha de freio">
                                    <button type="button" class="btn btn-outline-primary btn-add"><i class="icon-base ti tabler-plus"></i></button>
                                </div>
                            </div>
                        </div></div>
                    </div>
                </div>
                <input type="hidden" name="tasks_json" id="tasks_json" value="[]">
                <input type="hidden" name="parts_json" id="parts_json" value="[]">

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-label-secondary" onclick="OSWizard.prev()">Anterior</button>
                    <button type="button" class="btn btn-primary" onclick="OSWizard.next()">Próximo</button>
                </div>
            </div>

            <!-- ETAPA 4: EQUIPE -->
            <div id="step-equipe" class="content">
                <div class="content-header mb-4"><h6 class="mb-0">Funcionários Designados</h6><small>Selecione quem vai trabalhar nesta OS</small></div>
                <div class="row g-2">
                    <?php foreach ($mechanics as $m): ?>
                    <div class="col-md-4">
                        <label class="card p-3 d-flex flex-row align-items-center gap-2" style="cursor:pointer">
                            <input type="checkbox" name="mechanics[]" value="<?= (int) $m['id'] ?>" class="form-check-input mt-0">
                            <span class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-primary"><?= e(strtoupper(substr($m['name'], 0, 1))) ?></span></span>
                            <span><span class="d-block fw-medium"><?= e($m['name']) ?></span><small class="text-body-secondary"><?= e(roleLabel($m['role'])) ?></small></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$mechanics): ?><p class="text-body-secondary">Nenhum mecânico disponível.</p><?php endif; ?>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-label-secondary" onclick="OSWizard.prev()">Anterior</button>
                    <button type="button" class="btn btn-primary" onclick="OSWizard.next()">Próximo</button>
                </div>
            </div>

            <!-- ETAPA 5: REVISÃO -->
            <div id="step-revisao" class="content">
                <div class="content-header mb-4"><h6 class="mb-0">Revise os dados</h6><small>Confira antes de criar o atendimento</small></div>
                <div class="card"><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><small class="text-body-secondary d-block">Cliente</small><span id="reviewCliente">—</span></div>
                        <div class="col-md-6"><small class="text-body-secondary d-block">Veículo</small><span id="reviewVeiculo">—</span></div>
                        <div class="col-md-6"><small class="text-body-secondary d-block">Serviço</small><span id="reviewServico">—</span></div>
                        <div class="col-md-6"><small class="text-body-secondary d-block">Equipe</small><span id="reviewEquipe">—</span></div>
                    </div>
                </div></div>
                <div class="alert alert-warning mt-3 mb-0 small"><i class="icon-base ti tabler-info-circle"></i> Ao confirmar, a OS é criada e os mecânicos selecionados são notificados.</div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-label-secondary" onclick="OSWizard.prev()">Anterior</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-check"></i> Criar OS</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
window.WIZARD_CLIENTS = <?= $clientsJson ?>;
window.WIZARD_VEHICLES = <?= $vehiclesJson ?>;
</script>
<script src="/assets/vendor/libs/bs-stepper/bs-stepper.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="/js/fipe.js"></script>
<script src="/js/os-wizard.js"></script>
