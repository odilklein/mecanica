<?php
// =============================================
// RAIZ DO SITE — hospedagem compartilhada
// (InfinityFree).
//
// O app real vive em /public (fora do padrão de
// docroot) e o roteador deriva a rota a partir do
// SCRIPT_NAME. Ancorando o SCRIPT_NAME na raiz,
// /login, /clients etc. chegam ao kernel como
// esperado, sem sair do open_basedir do host.
// =============================================

declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__ . '/public/index.php';
