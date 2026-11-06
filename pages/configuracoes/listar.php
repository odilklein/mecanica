<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/configuracoes.php';
exigir_papel(['admin']);

$grupos = [
    'tema'    => ['tab' => 'tema',    'icone' => 'palette', 'titulo' => 'Aparência', 'subtitulo' => 'Cores e identidade visual do sistema'],
    'seo'     => ['tab' => 'seo',     'icone' => 'search',  'titulo' => 'SEO',       'subtitulo' => 'Título e metadados usados pelos buscadores'],
    'modulos' => ['tab' => 'modulos', 'icone' => 'puzzle',  'titulo' => 'Módulos',   'subtitulo' => 'Ligue ou desligue recursos do sistema'],
];

$configs = [];
foreach (array_keys($grupos) as $g) {
    $configs[$g] = config_grupo($conn, $g);
}

$titulo_pagina = 'Configurações';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-settings text-primary me-2"></i>Configurações</h1>
        <span class="page-subtitle">Tema, SEO e módulos do sistema</span>
    </div>
</div>

<form method="POST" action="processar.php" id="formConfig">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">

    <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
        <?php foreach ($grupos as $g => $meta): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $g === 'tema' ? ' active' : ''; ?>" id="tab-<?php echo $meta['tab']; ?>"
                    data-bs-toggle="tab" data-bs-target="#pane-<?php echo $meta['tab']; ?>" type="button" role="tab"
                    aria-controls="pane-<?php echo $meta['tab']; ?>" aria-selected="<?php echo $g === 'tema' ? 'true' : 'false'; ?>">
                <i class="icon-base ti tabler-<?php echo $meta['icone']; ?> me-1"></i><?php echo esc($meta['titulo']); ?>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($grupos as $g => $meta): ?>
        <div class="tab-pane fade<?php echo $g === 'tema' ? ' show active' : ''; ?>" id="pane-<?php echo $meta['tab']; ?>" role="tabpanel" aria-labelledby="tab-<?php echo $meta['tab']; ?>">
            <div class="card">
                <div class="card-body p-4">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-<?php echo $meta['icone']; ?>"></i>
                        <div>
                            <h3 class="panel-title"><?php echo esc($meta['titulo']); ?></h3>
                            <p class="panel-subtitle"><?php echo esc($meta['subtitulo']); ?></p>
                        </div>
                    </div>

                    <?php foreach ($configs[$g] as $cfg): ?>
                    <div class="mb-3">
                        <?php if ($cfg['tipo'] === 'bool'): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="<?php echo esc($cfg['chave']); ?>" name="<?php echo esc($cfg['chave']); ?>"
                                       value="1"<?php echo config_flag($conn, $cfg['chave']) ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="<?php echo esc($cfg['chave']); ?>"><?php echo esc($cfg['rotulo']); ?></label>
                                <?php if (!empty($cfg['ajuda'])): ?><div class="form-text"><?php echo esc($cfg['ajuda']); ?></div><?php endif; ?>
                            </div>

                        <?php elseif ($cfg['tipo'] === 'cor'): ?>
                            <label class="form-label" for="<?php echo esc($cfg['chave']); ?>_hex"><?php echo esc($cfg['rotulo']); ?></label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="color" class="form-control form-control-color flex-shrink-0" style="width: 3.25rem;"
                                       data-hex-target="<?php echo esc($cfg['chave']); ?>_hex"
                                       value="<?php echo esc(config_valor($conn, $cfg['chave'], '#b8270f')); ?>">
                                <input type="text" class="form-control" id="<?php echo esc($cfg['chave']); ?>_hex"
                                       name="<?php echo esc($cfg['chave']); ?>"
                                       value="<?php echo esc(config_valor($conn, $cfg['chave'], '#b8270f')); ?>"
                                       pattern="#[0-9a-fA-F]{6}" maxlength="7">
                            </div>
                            <?php if (!empty($cfg['ajuda'])): ?><div class="form-text"><?php echo esc($cfg['ajuda']); ?></div><?php endif; ?>

                        <?php elseif ($cfg['tipo'] === 'textarea'): ?>
                            <label class="form-label" for="<?php echo esc($cfg['chave']); ?>"><?php echo esc($cfg['rotulo']); ?></label>
                            <textarea class="form-control" id="<?php echo esc($cfg['chave']); ?>" name="<?php echo esc($cfg['chave']); ?>" rows="3"><?php echo esc(config_valor($conn, $cfg['chave'])); ?></textarea>
                            <?php if (!empty($cfg['ajuda'])): ?><div class="form-text"><?php echo esc($cfg['ajuda']); ?></div><?php endif; ?>

                        <?php else: ?>
                            <label class="form-label" for="<?php echo esc($cfg['chave']); ?>"><?php echo esc($cfg['rotulo']); ?></label>
                            <input type="<?php echo $cfg['tipo'] === 'numero' ? 'number' : 'text'; ?>" class="form-control"
                                   id="<?php echo esc($cfg['chave']); ?>" name="<?php echo esc($cfg['chave']); ?>"
                                   value="<?php echo esc(config_valor($conn, $cfg['chave'])); ?>">
                            <?php if (!empty($cfg['ajuda'])): ?><div class="form-text"><?php echo esc($cfg['ajuda']); ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="../../index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i>Salvar configurações</button>
    </div>
</form>

<script>
document.querySelectorAll('input[type="color"][data-hex-target]').forEach(function (picker) {
    var target = document.getElementById(picker.getAttribute('data-hex-target'));
    if (!target) { return; }
    picker.addEventListener('input', function () { target.value = picker.value; });
    target.addEventListener('input', function () {
        var v = target.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(v)) { picker.value = v; }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
