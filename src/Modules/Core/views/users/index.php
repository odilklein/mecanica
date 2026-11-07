<?php
$path = $resource->path();
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i><?= e($resource->labels()['plural']) ?></h1>
        <span class="page-subtitle">Gerencie os funcionários da oficina</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>/create" class="btn btn-primary">
            <i class="icon-base ti tabler-plus"></i> Novo funcionário
        </a>
    </div>
</div>

<!-- Fixed Header (Vuexy — tables-datatables-extensions) -->
<div class="card">
    <h5 class="card-header text-md-start text-center border-bottom">Funcionários</h5>
    <div class="card-datatable table-responsive">
        <table class="dt-fixedheader table table-bordered">
            <thead>
                <tr>
                    <th></th>
                    <th></th>
                    <th>id</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Admissão</th>
                    <th>Salário</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th></th>
                    <th></th>
                    <th>id</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Admissão</th>
                    <th>Salário</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
window.USERS_DATATABLE = {
    url: '<?= e($path) ?>/datatable',
    csrf: '<?= e($csrf) ?>',
    basePath: '<?= e($path) ?>'
};
</script>
