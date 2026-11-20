# ADR-005 — IA assistiva com aprovação humana obrigatória

**Status:** aceito
**Data:** 2026-10

## Contexto

O diferencial do projeto é usar IA para reduzir digitação e melhorar a
comunicação, sem entregar à IA a decisão sobre dados da oficina. Um erro
silencioso em orçamento, OS ou cadastro teria custo real.

## Decisão

A IA pode **ler**, **resumir** e **propor**, mas não grava diretamente.

1. O modelo recebe ferramentas de consulta e monta uma proposta
   (`agent_proposals`) com status `pending`.
2. A proposta mostra uma prévia do que mudaria.
3. Só é aplicada após aprovação explícita do usuário e verificação de que ele
   tem o papel necessário (`requires_role`).
4. Cada proposta tem `fingerprint` e chave de idempotência; propostas antigas
   podem ser substituídas (`superseded`) ou expirar.

## Alternativas consideradas

| Alternativa | Risco |
|---|---|
| IA gravando direto | Erro sem revisão, difícil de auditar |
| IA apenas em modo leitura | Perde o ganho de tempo no preenchimento |
| Aprovação por outro usuário | Burocracia incompatível com o balcão |

## Consequências

- Positivas: ganho de produtividade com controle; trilha de auditoria; a IA não
  vira um ator com permissões próprias.
- Negativas: uma etapa a mais no fluxo; exige interface de revisão.
- O módulo é opcional: sem `LLM_API_KEY`, o sistema continua funcionando.

## Escopo da IA

- Preencher formulários de cliente, veículo e nova OS para revisão.
- Transformar ditado em queixa técnica.
- Gerar mensagem de WhatsApp a partir do orçamento.
- Responder perguntas sobre os dados da oficina.
- Transcrever áudio.
