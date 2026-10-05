SET NAMES utf8mb4;

-- =============================================
-- MIGRAÇÃO 004 — CONDENSAR STATUS DA OS (12 -> 8)
-- -------------------------------------------------
-- Mapeamento:
--   triagem                 -> recebida
--   aguardando_diagnostico  -> em_analise
--   aguardando_orcamento    -> em_analise
--   pronta_para_execucao    -> em_execucao
--   aguardando_retorno      -> em_execucao
--   (demais permanecem)
--
-- Histórico em os_eventos NÃO é alterado (texto livre).
--
-- Aplicar (banco já existente):
--   docker exec -i mecanica-db mysql -umecanica -proot123 mecanica \
--     < sql/migrations/004_condensar_status_os.sql
-- =============================================

-- Backup de segurança da coluna de status.
CREATE TABLE IF NOT EXISTS ordens_servico_bkp_status_os AS
SELECT id, status, NOW() AS copiado_em FROM ordens_servico;

-- 1) Amplia o ENUM para aceitar os valores antigos e o novo (em_analise).
ALTER TABLE ordens_servico MODIFY status ENUM(
    'recebida','triagem','aguardando_diagnostico','aguardando_orcamento','aguardando_aprovacao',
    'aguardando_peca','pronta_para_execucao','em_execucao','aguardando_retorno',
    'pronta_para_entrega','entregue','cancelada','em_analise'
) NOT NULL DEFAULT 'recebida';

-- 2) Converte os registros existentes para o fluxo condensado.
UPDATE ordens_servico SET status = CASE status
    WHEN 'triagem' THEN 'recebida'
    WHEN 'aguardando_diagnostico' THEN 'em_analise'
    WHEN 'aguardando_orcamento' THEN 'em_analise'
    WHEN 'pronta_para_execucao' THEN 'em_execucao'
    WHEN 'aguardando_retorno' THEN 'em_execucao'
    ELSE status
END;

-- 3) Reduz o ENUM para o conjunto final.
ALTER TABLE ordens_servico MODIFY status ENUM(
    'recebida','em_analise','aguardando_aprovacao','aguardando_peca',
    'em_execucao','pronta_para_entrega','entregue','cancelada'
) NOT NULL DEFAULT 'recebida';

-- 4) Recria a view com os novos rótulos.
CREATE OR REPLACE VIEW vw_ordens_servico_detalhada AS
SELECT
    os.id,
    os.numero,
    os.status,
    CASE os.status
        WHEN 'recebida' THEN 'Recebida'
        WHEN 'em_analise' THEN 'Em análise'
        WHEN 'aguardando_aprovacao' THEN 'Aguardando aprovação'
        WHEN 'aguardando_peca' THEN 'Aguardando peça'
        WHEN 'em_execucao' THEN 'Em execução'
        WHEN 'pronta_para_entrega' THEN 'Pronta para entrega'
        WHEN 'entregue' THEN 'Entregue'
        WHEN 'cancelada' THEN 'Cancelada'
    END AS status_label,
    os.prioridade,
    os.queixa_inicial,
    os.diagnostico,
    os.data_entrada,
    os.data_conclusao,
    c.id AS cliente_id,
    c.nome AS cliente,
    c.telefone AS cliente_telefone,
    v.id AS veiculo_id,
    v.marca,
    v.modelo,
    v.ano,
    v.placa,
    TRIM(CONCAT(COALESCE(v.marca, ''), ' ', COALESCE(v.modelo, ''))) AS veiculo,
    u.nome AS responsavel_chefe,
    (SELECT GROUP_CONCAT(ur.nome ORDER BY ur.nome SEPARATOR ', ')
       FROM os_responsaveis r
       JOIN usuarios ur ON ur.id = r.usuario_id
      WHERE r.os_id = os.id AND r.concluido_em IS NULL) AS responsaveis,
    (SELECT COUNT(*) FROM os_tarefas t WHERE t.os_id = os.id) AS total_tarefas,
    (SELECT COUNT(*) FROM os_tarefas t WHERE t.os_id = os.id AND t.status = 'concluida') AS tarefas_concluidas,
    (SELECT COUNT(*) FROM pedidos_pecas p WHERE p.os_id = os.id AND p.status NOT IN ('recebido', 'cancelado')) AS pedidos_pendentes
FROM ordens_servico os
JOIN clientes c ON c.id = os.cliente_id
LEFT JOIN veiculos v ON v.id = os.veiculo_id
LEFT JOIN usuarios u ON u.id = os.responsavel_chefe_id;
