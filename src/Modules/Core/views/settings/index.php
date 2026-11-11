<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-settings"></i>Configurações</h1>
        <span class="page-subtitle">Dados da oficina, aparência, orçamentos e SEO</span>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <form method="POST" action="/settings">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="card mb-3">
                <div class="card-body p-4">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-palette"></i>
                        <div>
                            <h3 class="panel-title">Aparência</h3>
                            <p class="panel-subtitle">Cor da marca aplicada em todo o sistema</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="theme_color">Cor primária</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="color" class="form-control form-control-color flex-shrink-0" style="width: 3.25rem;"
                                       data-color-target="theme_color" value="<?= e(setting('theme_color', '#b8270f')) ?>">
                                <input type="text" class="form-control" id="theme_color" name="theme_color"
                                       value="<?= e(setting('theme_color', '#b8270f')) ?>" pattern="#[0-9a-fA-F]{6}" maxlength="7">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body p-4">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-building-store"></i>
                        <div>
                            <h3 class="panel-title">Dados da oficina</h3>
                            <p class="panel-subtitle">Usados na impressão de orçamentos e documentos</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="company_name">Nome / razão social</label>
                            <input type="text" class="form-control" id="company_name" name="company_name"
                                   value="<?= e(setting('company_name', 'Mecânica')) ?>" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_document">CNPJ / CPF</label>
                            <input type="text" class="form-control" id="company_document" name="company_document"
                                   value="<?= e(setting('company_document', '')) ?>" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_phone">Telefone</label>
                            <input type="text" class="form-control" id="company_phone" name="company_phone"
                                   value="<?= e(setting('company_phone', '')) ?>" maxlength="20" data-mask="phone">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="company_email">E-mail</label>
                            <input type="email" class="form-control" id="company_email" name="company_email"
                                   value="<?= e(setting('company_email', '')) ?>" maxlength="120">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="company_address">Endereço</label>
                            <input type="text" class="form-control" id="company_address" name="company_address"
                                   value="<?= e(setting('company_address', '')) ?>" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_city">Cidade</label>
                            <input type="text" class="form-control" id="company_city" name="company_city"
                                   value="<?= e(setting('company_city', '')) ?>" maxlength="80">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label" for="company_state">UF</label>
                            <input type="text" class="form-control text-uppercase" id="company_state" name="company_state"
                                   value="<?= e(setting('company_state', '')) ?>" maxlength="2">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body p-4">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-search"></i>
                        <div>
                            <h3 class="panel-title">SEO</h3>
                            <p class="panel-subtitle">Título e metadados usados pelos buscadores</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="seo_title">Título do site</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-text-size"></i></span>
                                <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?= e(setting('seo_title', '')) ?>" maxlength="120">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="seo_description">Meta description</label>
                            <div class="input-group input-group-merge input-group-merge-textarea">
                                <span class="input-group-text"><i class="icon-base ti tabler-align-left"></i></span>
                                <textarea class="form-control" id="seo_description" name="seo_description" rows="3"><?= e(setting('seo_description', '')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="seo_keywords">Palavras-chave</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-tags"></i></span>
                                <input type="text" class="form-control" id="seo_keywords" name="seo_keywords" value="<?= e(setting('seo_keywords', '')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body p-4">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-file-text"></i>
                        <div>
                            <h3 class="panel-title">Orçamentos</h3>
                            <p class="panel-subtitle">Validade padrão e termos exibidos na impressão</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="budget_validity_days">Validade padrão (dias)</label>
                            <input type="number" class="form-control" id="budget_validity_days" name="budget_validity_days"
                                   min="1" max="365" value="<?= e(setting('budget_validity_days', '15')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="budget_terms">Termos e condições</label>
                            <textarea class="form-control" id="budget_terms" name="budget_terms" rows="3"
                                      placeholder="Ex.: Orçamento válido por 15 dias. Peças com garantia de 90 dias."><?= e(setting('budget_terms', '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar configurações</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('input[type="color"][data-color-target]').forEach(function (picker) {
    var target = document.getElementById(picker.getAttribute('data-color-target'));
    if (!target) { return; }
    picker.addEventListener('input', function () { target.value = picker.value; });
    target.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(target.value)) { picker.value = target.value; }
    });
});
</script>
