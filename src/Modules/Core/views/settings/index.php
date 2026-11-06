<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-settings"></i>Configurações</h1>
        <span class="page-subtitle">Tema e SEO do sistema</span>
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
