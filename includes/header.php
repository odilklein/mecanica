<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoMecânica | <?php echo $titulo_pagina ?? 'Sistema de Gestão'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../../index.php">
                <i class="fas fa-wrench me-2"></i>AutoMecânica
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Alternar navegação">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="../../index.php"><i class="fas fa-home me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../clientes/listar.php"><i class="fas fa-users me-1"></i>Clientes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../veiculos/listar.php"><i class="fas fa-car me-1"></i>Veículos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pecas/listar.php"><i class="fas fa-cogs me-1"></i>Estoque</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../servicos/listar.php"><i class="fas fa-tools me-1"></i>Serviços</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../agendamentos/listar.php"><i class="fas fa-calendar-check me-1"></i>Agendamentos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../orcamentos/listar.php"><i class="fas fa-file-invoice me-1"></i>Orçamentos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../orcamentos/relatorio.php"><i class="fas fa-chart-bar me-1"></i>Relatórios</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?php echo $_SESSION['usuario_email'] ?? ''; ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../../pages/auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container-fluid py-4">
