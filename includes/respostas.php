<?php
// =============================================
// MENSAGENS FLASH E RESPOSTAS
// Centraliza o padrão "grava mensagem + redireciona"
// e evita vazar detalhes de erro do banco ao usuário.
// =============================================

if (!function_exists('redirecionar_com_mensagem')) {
    function redirecionar_com_mensagem(string $local, string $mensagem, string $tipo = 'success'): void
    {
        $_SESSION['mensagem'] = $mensagem;
        $_SESSION['mensagem_tipo'] = $tipo;
        header('Location: ' . $local);
        exit();
    }
}

if (!function_exists('mensagem_de_erro')) {
    // Registra o erro real no log e devolve uma mensagem segura ao usuário.
    function mensagem_de_erro(Throwable $e, string $mensagem = 'Não foi possível concluir a operação.'): string
    {
        error_log($e->getMessage());
        return $mensagem;
    }
}
