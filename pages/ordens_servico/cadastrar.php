<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$mecanicos = $conn->query("SELECT id, nome, tipo FROM usuarios WHERE ativo = 1 AND tipo IN ('mecanico', 'mecanico_chefe') ORDER BY nome")->fetchAll();
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
        <!-- Wizard Progress -->
        <div class="wizard-progress">
            <div class="wizard-step active" data-step="0">
                <div class="step-num">1</div>
                <span class="step-label">Cliente</span>
            </div>
            <div class="wizard-step" data-step="1">
                <div class="step-num">2</div>
                <span class="step-label">Veículo</span>
            </div>
            <div class="wizard-step" data-step="2">
                <div class="step-num">3</div>
                <span class="step-label">Serviço</span>
            </div>
            <div class="wizard-step" data-step="3">
                <div class="step-num">4</div>
                <span class="step-label">Equipe</span>
            </div>
        </div>

        <form method="POST" action="processar.php?acao=cadastrar" id="formOS">
            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">

            <!-- Step 1: Cliente -->
            <div class="wizard-panel active">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="panel-header">
                            <i data-lucide="user-round"></i>
                            <div>
                                <h3 class="panel-title">Dados do Cliente</h3>
                                <p class="panel-subtitle">Identifique quem está trazendo o veículo</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label-with-icon" for="nome"><i data-lucide="user"></i> Nome do cliente *</label>
                                <input class="form-control" id="nome" name="nome" maxlength="120" required autofocus>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-with-icon" for="telefone"><i data-lucide="phone"></i> Telefone</label>
                                <input class="form-control" id="telefone" name="telefone" maxlength="20" inputmode="tel" oninput="formatarTelefone(this)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Veículo -->
            <div class="wizard-panel">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="panel-header">
                            <i data-lucide="car-front"></i>
                            <div>
                                <h3 class="panel-title">Dados do Veículo</h3>
                                <p class="panel-subtitle">Informações do carro que será atendido</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-with-icon" for="marca"><i data-lucide="tag"></i> Marca</label>
                                <input class="form-control" id="marca" name="marca" maxlength="50" list="listaMarcas" placeholder="Ex: Volkswagen">
                                <datalist id="listaMarcas">
                                    <option value="Fiat"><option value="Volkswagen"><option value="Chevrolet">
                                    <option value="Ford"><option value="Toyota"><option value="Honda">
                                    <option value="Hyundai"><option value="Renault"><option value="Jeep">
                                </datalist>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label-with-icon" for="modelo"><i data-lucide="car"></i> Modelo</label>
                                <input class="form-control" id="modelo" name="modelo" maxlength="60" placeholder="Ex: Gol, Corolla">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-with-icon" for="placa"><i data-lucide="hash"></i> Placa</label>
                                <input class="form-control" id="placa" name="placa" maxlength="10" style="text-transform:uppercase" oninput="formatarPlaca(this)" placeholder="ABC-1234">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Detalhes do Serviço -->
            <div class="wizard-panel">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="panel-header">
                            <i data-lucide="stethoscope"></i>
                            <div>
                                <h3 class="panel-title">Detalhes do Serviço</h3>
                                <p class="panel-subtitle">Descreva o problema e a urgência</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-with-icon" for="prioridade"><i data-lucide="flag"></i> Prioridade</label>
                                <select class="form-select" id="prioridade" name="prioridade">
                                    <option value="normal" selected>Normal</option>
                                    <option value="baixa">Baixa</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label-with-icon" for="queixa_inicial"><i data-lucide="message-circle-warning"></i> O que precisa ser feito? *</label>
                                <textarea class="form-control" id="queixa_inicial" name="queixa_inicial" rows="3" maxlength="4000" required placeholder="Descreva a queixa do cliente..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label-with-icon" for="observacoes_internas"><i data-lucide="notebook-pen"></i> Observações internas</label>
                                <textarea class="form-control" id="observacoes_internas" name="observacoes_internas" rows="2" maxlength="4000" placeholder="Anotações internas (não visíveis ao cliente)"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Equipe e Peças -->
            <div class="wizard-panel">
                <div class="card mb-3">
                    <div class="card-body p-4">
                        <div class="panel-header">
                            <i data-lucide="users-round"></i>
                            <div>
                                <h3 class="panel-title">Funcionários Designados</h3>
                                <p class="panel-subtitle">Selecione quem vai trabalhar nesta OS</p>
                            </div>
                        </div>
                        <div class="select-cards-grid">
                            <?php foreach ($mecanicos as $mecanico): ?>
                            <label class="select-card-employee" tabindex="0">
                                <input type="checkbox" name="funcionarios_ids[]" value="<?php echo (int) $mecanico['id']; ?>" class="d-none">
                                <div class="emp-avatar"><?php echo strtoupper(substr($mecanico['nome'], 0, 1)); ?></div>
                                <div class="emp-info">
                                    <div class="emp-name"><?php echo esc($mecanico['nome']); ?></div>
                                    <div class="emp-role"><?php echo esc(ucfirst(str_replace('_', ' ', $mecanico['tipo']))); ?></div>
                                </div>
                                <div class="emp-check"><i data-lucide="check"></i></div>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!$mecanicos): ?>
                            <p class="text-muted">Nenhum mecânico disponível.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body p-4">
                                <div class="panel-header">
                                    <i data-lucide="list-checks"></i>
                                    <div>
                                        <h3 class="panel-title">Tarefas do Trabalho</h3>
                                        <p class="panel-subtitle">Itens de trabalho (uma por linha)</p>
                                    </div>
                                </div>
                                <div data-dynamic-list>
                                    <input type="hidden" name="tarefas" value="">
                                    <div class="dynamic-list-items"></div>
                                    <div class="dynamic-list-input mt-2">
                                        <input class="form-control" placeholder="Digite a tarefa e pressione Enter" onkeydown="if(event.key==='Enter'){event.preventDefault();this.closest('[data-dynamic-list]').querySelector('.btn-add').click();}">
                                        <button type="button" class="btn-add" title="Adicionar tarefa"><i data-lucide="plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body p-4">
                                <div class="panel-header">
                                    <i data-lucide="package-search"></i>
                                    <div>
                                        <h3 class="panel-title">Peças Necessárias</h3>
                                        <p class="panel-subtitle">Formato: Descrição | Quantidade</p>
                                    </div>
                                </div>
                                <div data-dynamic-list>
                                    <input type="hidden" name="pecas" value="">
                                    <div class="dynamic-list-items"></div>
                                    <div class="dynamic-list-input mt-2">
                                        <input class="form-control" placeholder="Ex.: Radiador | 1" onkeydown="if(event.key==='Enter'){event.preventDefault();this.closest('[data-dynamic-list]').querySelector('.btn-add').click();}">
                                        <button type="button" class="btn-add" title="Adicionar peça"><i data-lucide="plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wizard Footer -->
            <div class="wizard-footer">
                <button type="button" id="wizardPrev" class="btn btn-outline-secondary btn-icon" style="visibility:hidden">
                    <i data-lucide="arrow-left"></i> Anterior
                </button>
                <button type="button" id="wizardNext" class="btn btn-primary btn-icon">
                    Próximo <i data-lucide="arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
