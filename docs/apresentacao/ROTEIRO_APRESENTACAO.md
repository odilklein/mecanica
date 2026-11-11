# Roteiro de Apresentação — Trigger e View

**Projeto:** Auto Mecânica — Sistema de Gestão de Oficina (PHP + MySQL)
**Tema:** Objetos de banco — 1 Trigger e 1 View
**Duração sugerida:** 8 a 12 minutos

> Dica: 1 slide ≈ 1 minuto. Deixe os blocos de código no slide e o restante no roteiro (aqui).

---

## Slide 1 — Capa

- Título: **Trigger e View no banco da Auto Mecânica**
- Nome do grupo / integrantes / disciplina / data.

**Fala:** "Nosso sistema gerencia clientes, veículos e, principalmente, Ordens de Serviço (OS)
de uma oficina. Vamos mostrar dois recursos de banco que automatizam e organizam a operação:
uma Trigger e uma View."

---

## Slide 2 — O sistema em 1 minuto

- Sistema web (PHP puro + MySQL) para uma oficina mecânica.
- Fluxo principal:
  `Cliente/Veículo → Nova OS → Diagnóstico → Tarefas → Peças → Execução → Entrega → Histórico`.
- 4 perfis de acesso: Administrador, Secretaria, Mecânico-chefe e Mecânico.
- O **MySQL** guarda tudo: cadastros, OS, tarefas, pedidos de peças, eventos e histórico.

**Fala:** "O coração do sistema é a Ordem de Serviço. Ela passa por vários status, de
'Recebida' até 'Entregue'. Controlar esse histórico é essencial para o negócio."

---

## Slide 3 — Principais tabelas (relacionamentos)

```
usuarios ─┐
          ├─< ordens_servico >─┐
clientes ─┘         │          └─< veiculos
                    ├─< os_tarefas
                    ├─< os_responsaveis >── usuarios
                    ├─< os_eventos              (histórico da OS)
                    ├─< pedidos_pecas >── pedido_pecas_itens
                    └─< os_entregas
```

- `ordens_servico`: número, cliente, veículo, status, prioridade, queixa, diagnóstico.
- `os_eventos`: linha do tempo da OS (criação, diagnóstico, status, peças, entrega).
- `clientes`, `veiculos`, `usuarios`, `os_responsaveis`, `pedidos_pecas`.

**Fala:** "A OS se liga a cliente, veículo, equipe, tarefas, peças e eventos. É esse
relacionamento que a View vai reunir e que a Trigger vai alimentar."

---

## Slide 4 — Objetivo da Trigger

**Pergunta de negócio:** "Quem mudou o status desta OS, de quê para quê e quando?"

- **Trigger:** `trg_os_status_historico`
- **Evento:** `AFTER UPDATE` na tabela `ordens_servico`
- **Ação:** quando o status muda, grava sozinha um registro em `os_eventos`.
- **Por que no banco:** garante histórico mesmo que algum fluxo de código esqueça de registrar.
  É a **fonte única de verdade** do histórico de status.

**Fala:** "Antes, cada tela de mudança de status era responsável por gravar o histórico no
PHP. Se surgisse um novo caminho de código, podia esquecer. Com a trigger, isso passa a ser
responsabilidade do banco — automático e consistente."

---

## Slide 5 — Código da Trigger (SQL)

```sql
DELIMITER $$
CREATE TRIGGER trg_os_status_historico
AFTER UPDATE ON ordens_servico
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO os_eventos
            (os_id, tipo, status_anterior, status_novo, descricao, criado_por)
        VALUES (
            NEW.id,
            'status',
            OLD.status,
            NEW.status,
            CONCAT('Status alterado de "', OLD.status, '" para "', NEW.status, '".'),
            COALESCE(@usuario_atual, NEW.criado_por)
        );
    END IF;
END$$
DELIMITER ;
```

**Pontos para explicar:**
- `OLD` / `NEW` comparam o status antes e depois.
- `IF OLD.status <> NEW.status` evita registrar quando nada mudou.
- `@usuario_atual` = quem está logado (setado no `config/conexao.php`); se não houver,
  usa o criador da OS.

---

## Slide 6 — Demonstração da Trigger

Antes (estado atual da OS 3, que já mostra eventos):

```sql
SELECT status FROM ordens_servico WHERE id = 3;      -- em_execucao
SELECT id, tipo, status_anterior, status_novo
  FROM os_eventos WHERE os_id = 3 ORDER BY id;
```

Dispara a mudança (informando o usuário logado):

```sql
SET @usuario_atual = 3;
UPDATE ordens_servico SET status = 'pronta_para_entrega' WHERE id = 3;
```

Depois — **linha criada automaticamente**:

```sql
SELECT id, tipo, status_anterior, status_novo, descricao, criado_por
  FROM os_eventos WHERE os_id = 3 ORDER BY id DESC LIMIT 1;
-- status | em_execucao | pronta_para_entrega | Status alterado de... | 3
```

> Print sugerido: a tela de detalhes da OS mostrando o novo item na linha do tempo.

**Fala:** "Não executamos nenhum INSERT. A trigger percebeu a mudança e criou o histórico."

---

## Slide 7 — Por que a Trigger é útil para o negócio

- **Rastreabilidade:** todo andamento da OS fica registrado automaticamente.
- **Confiabilidade do histórico:** não depende do programador lembrar de gravar.
- **Base para indicadores:** tempo em cada etapa, gargalos, produtividade.
- **Segurança de auditoria:** em produção, a app recebe só `UPDATE` na OS; o histórico é
  gravado pela trigger (usuário não forja eventos direto).
- **Menos código e menos duplicação** no PHP.

---

## Slide 8 — Objetivo da View

**Pergunta de negócio:** "Quais OS estão em andamento, com cliente, veículo, equipe e
pendências — numa só consulta?"

- **View:** `vw_ordens_servico_detalhada`
- Reúne **5 tabelas**: `ordens_servico`, `clientes`, `veiculos`, `usuarios`, `os_responsaveis`.
- Entrega já pronto: nome do cliente, veículo, chefia, responsáveis ativos, rótulo do status
  e contadores de tarefas e pedidos pendentes.
- Usada no **dashboard** e na **listagem de OS** do sistema.

**Fala:** "O mesmo JOIN estava copiado em vários arquivos PHP. A View centraliza isso e
devolve a informação pronta para a tela."

---

## Slide 9 — Código da View (SQL)

```sql
CREATE OR REPLACE VIEW vw_ordens_servico_detalhada AS
SELECT
    os.id, os.numero, os.status,
    CASE os.status
        WHEN 'em_execucao' THEN 'Em execução'
        WHEN 'aguardando_peca' THEN 'Aguardando peça'
        WHEN 'pronta_para_entrega' THEN 'Pronta para entrega'
        -- ... demais status
    END AS status_label,
    os.prioridade, os.queixa_inicial, os.data_entrada,
    c.nome AS cliente, c.telefone AS cliente_telefone,
    v.placa,
    TRIM(CONCAT(COALESCE(v.marca,''), ' ', COALESCE(v.modelo,''))) AS veiculo,
    u.nome AS responsavel_chefe,
    (SELECT GROUP_CONCAT(ur.nome SEPARATOR ', ')
       FROM os_responsaveis r JOIN usuarios ur ON ur.id = r.usuario_id
      WHERE r.os_id = os.id AND r.concluido_em IS NULL) AS responsaveis,
    (SELECT COUNT(*) FROM os_tarefas t WHERE t.os_id = os.id) AS total_tarefas,
    (SELECT COUNT(*) FROM os_tarefas t
      WHERE t.os_id = os.id AND t.status = 'concluida') AS tarefas_concluidas,
    (SELECT COUNT(*) FROM pedidos_pecas p
      WHERE p.os_id = os.id AND p.status NOT IN ('recebido','cancelado')) AS pedidos_pendentes
FROM ordens_servico os
JOIN clientes c   ON c.id = os.cliente_id
LEFT JOIN veiculos v ON v.id = os.veiculo_id
LEFT JOIN usuarios u ON u.id = os.responsavel_chefe_id;
```

> No slide, use a versão resumida (com "-- ... demais status") para caber.

---

## Slide 10 — Demonstração da View

```sql
SELECT numero, cliente, veiculo, status_label,
       responsaveis, tarefas_concluidas, total_tarefas, pedidos_pendentes
FROM vw_ordens_servico_detalhada
WHERE status NOT IN ('entregue', 'cancelada')
ORDER BY FIELD(prioridade, 'urgente', 'alta', 'normal', 'baixa'), data_entrada;
```

Resultado esperado (exemplo com o seed):

| numero | cliente | veiculo | status_label | responsaveis | tam | con | pend |
|-------:|---------|---------|--------------|--------------|----:|----:|-----:|
| 1007 | Marcos Vinicius Souza | Toyota Hilux | Pronta para execução | Mecanico 1 | 1 | 1 | 0 |
| 1002 | Maria Fernanda Costa | Volkswagen Golf | Pronta para entrega | Mecanico 1 | 2 | 1 | 0 |
| 1003 | João Pedro Santos | Chevrolet Onix | Em execução | Mecanico 2 | 1 | 0 | 1 |

> Print sugerido: o dashboard do sistema mostrando as mesmas OS com o status já traduzido.

**Fala:** "Uma consulta, cinco tabelas, com pendências de tarefas e peças — sem escrever JOIN
nenhum na aplicação."

---

## Slide 11 — Por que a View é útil para o negócio

- **Visão consolidada** do que está em andamento (fila operacional e dashboard).
- **Reaproveitamento:** elimina JOIN duplicado em vários arquivos PHP.
- **Consistência:** o rótulo do status e o cálculo de pendências ficam em um só lugar.
- **Agilidade:** base pronta para relatórios e indicadores da oficina.

---

## Slide 12 — Conclusão

- **Trigger** `trg_os_status_historico`: histórico de status automático e confiável.
- **View** `vw_ordens_servico_detalhada`: consulta consolidada das OS para o dia a dia.
- Ganhos: menos código duplicado, dados consistentes e melhor suporte à decisão.
- Fechamento: "O banco deixa de ser só armazenamento e passa a garantir regras do negócio."

---

## Anexo A — Roteiro de teste (rodar ao vivo)

1. Subir o banco (Docker):
   ```bash
   podman-compose down -v && podman-compose up -d --build
   ```
2. Conferir que os objetos existem:
   ```sql
   SHOW TRIGGERS LIKE 'ordens_servico';
   SHOW FULL TABLES WHERE Table_type = 'VIEW';
   ```
3. Trigger (demonstração): ver Anexo B.
4. View (demonstração): Slide 10.
5. Extra (na interface): abrir uma OS, mudar o status e ver o item novo na linha do tempo.

## Anexo B — Evidência de que a Trigger não duplica

```sql
SELECT COUNT(*) FROM os_eventos WHERE os_id = 3 AND tipo = 'status';
SET @usuario_atual = 3;
UPDATE ordens_servico SET status = 'em_execucao' WHERE id = 3;   -- volta status
SELECT COUNT(*) FROM os_eventos WHERE os_id = 3 AND tipo = 'status';  -- +1 (apenas um)
```

## Perguntas prováveis da banca

- **"Por que a trigger e não no PHP?"** Garante integridade no nível do banco; qualquer
  caminho de código que altere o status gera o histórico.
- **"E se o status não mudar?"** A condição `OLD.status <> NEW.status` impede registro vazio.
- **"De onde vem o autor?"** De `@usuario_atual`, definido na conexão a partir da sessão.
- **"A View guarda dados?"** Não; é uma consulta salva, sempre atual.
- **"Impacto de desempenho?"** A trigger só escreve quando há mudança real; a View usa
  índices existentes (FKs) e é filtrada/paginada na aplicação.
