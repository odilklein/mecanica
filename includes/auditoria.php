<?php
// =============================================
// REGISTRO DE AUDITORIA
// Grava ações sensíveis na tabela auditoria.
// Nunca deixar uma falha de auditoria quebrar
// a ação principal do usuário.
// =============================================
if (!function_exists('registrar_auditoria')) {
    function registrar_auditoria(PDO $conn, ?int $usuario_id, string $acao, string $entidade, ?int $entidade_id = null, string $descricao = '', ?array $dados = null): void
    {
        try {
            $stmt = $conn->prepare(
                'INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, dados) VALUES (:usuario_id, :entidade, :entidade_id, :acao, :dados)'
            );
            $stmt->execute([
                ':usuario_id' => $usuario_id ?: null,
                ':entidade' => mb_substr((string) $entidade, 0, 60),
                ':entidade_id' => $entidade_id ?: null,
                ':acao' => mb_substr((string) $acao, 0, 40),
                ':dados' => $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable $e) {
            error_log('Falha ao registrar auditoria: ' . $e->getMessage());
        }
    }
}