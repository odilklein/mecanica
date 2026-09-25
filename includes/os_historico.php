<?php
// =============================================
// HISTÓRICO DE STATUS DA OS
// Registra em os_eventos a mudança de status da ordem de serviço.
// Substitui o trigger trg_os_status_historico, que não é suportado em
// hospedagem compartilhada (ex.: InfinityFree) por exigir o privilégio
// TRIGGER do MySQL.
// =============================================

if (!function_exists('registrar_evento_status_os')) {
    /**
     * Grava um evento do tipo 'status' se o status atual da OS for diferente
     * do status anterior informado. Deve ser chamado DEPOIS do UPDATE que
     * alterou o status (dentro da mesma transação, quando houver).
     */
    function registrar_evento_status_os(PDO $conn, int $os_id, ?string $status_anterior, int $usuario_id, ?string $descricao = null): void
    {
        if ($os_id <= 0 || $status_anterior === null || $status_anterior === '') {
            return;
        }

        $stmt = $conn->prepare('SELECT status FROM ordens_servico WHERE id = :id');
        $stmt->execute([':id' => $os_id]);
        $status_novo = $stmt->fetchColumn();

        if ($status_novo === false || $status_novo === $status_anterior) {
            return;
        }

        $descricao = $descricao ?: ('Status alterado de "' . $status_anterior . '" para "' . $status_novo . '".');
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por)
            VALUES (:os_id, 'status', :anterior, :novo, :descricao, :usuario)");
        $stmt->execute([
            ':os_id' => $os_id,
            ':anterior' => $status_anterior,
            ':novo' => $status_novo,
            ':descricao' => $descricao,
            ':usuario' => $usuario_id,
        ]);
    }
}
