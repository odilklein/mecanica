# Assistente de IA

Chat integrado ao sistema para consultar dados, ler anexos e **preparar**
alterações, com aprovação humana obrigatória. Implementado em PHP, dentro do
módulo `src/Modules/Agent/`, sem serviço externo.

> **Estado:** preenchimento de formulário com IA (texto/voz), chat com streaming,
> modelos Groq selecionáveis, reasoning, anexos, ferramentas de leitura,
> propostas com aprovação, widgets e auditoria.

---

## 1. Como ativar

```bash
# 1. Chave da Groq (a mesma usada na transcrição dos tickets)
GROQ_API_KEY=gsk_...

# 2. Opcional: sobrescrever o endpoint/modelo
LLM_BASE_URL=https://api.groq.com/openai/v1
LLM_MODEL=openai/gpt-oss-20b

# 3. Aplicar as migrations
docker compose exec app php bin/migrate.php migrate

# 4. Rebuild necessário para o pdftotext (poppler-utils)
docker compose build app && docker compose up -d app

# 5. Acessar
/assistant
```

Se `LLM_API_KEY` estiver vazio, o assistente usa `GROQ_API_KEY`.

### Variáveis

| Variável | Padrão | Função |
|---|---|---|
| `GROQ_API_KEY` | — | chave usada pelo chat e pela transcrição |
| `LLM_API_KEY` | vazio | chave específica do chat (opcional) |
| `LLM_BASE_URL` | `https://api.groq.com/openai/v1` | endpoint OpenAI-compatible |
| `LLM_MODEL` | `openai/gpt-oss-20b` | modelo padrão |
| `LLM_VISION_MODEL` | `qwen/qwen3.8-27b` | modelo usado quando há imagem |
| `LLM_MAX_TOKENS` | `1024` | teto do modo Rápido |
| `LLM_MAX_TOKENS_BALANCED` | `2048` | teto do modo Equilibrado |
| `LLM_MAX_TOKENS_DEEP` | `3072` | teto do modo Profundo |
| `LLM_TEMPERATURE` | `0.3` | temperatura |
| `AGENT_MOCK` | `false` | modo demonstração sem provedor |
| `AGENT_MAX_TURNS_HOUR` | `30` | limite por usuário/hora |
| `AGENT_MAX_TURNS_DAY` | `200` | limite por usuário/dia |
| `AGENT_MAX_STEPS` | `6` | iterações de tool calling por turno |
| `APP_KEY` | — | assina o token das ofertas de peça (gere com `php -r "echo bin2hex(random_bytes(32));"`) |

> **Limite do plano gratuito da Groq:** `8000` tokens por minuto. Cada turno com
> tool calling faz 1–3 chamadas, então o contexto é podado para ~6k caracteres
> de histórico. Se o volume crescer, use o plano Developer (250k TPM).

### Modo demonstração

```env
AGENT_MOCK=true
```

Não chama o provedor. Usa dados reais do banco para exercitar o pipeline
completo (streaming → tool → widget → proposta → aprovação).

### Permissão do diretório de anexos

O Apache roda como `www-data`. Em ambiente com bind mount, garanta:

```bash
docker compose exec -u root app sh -c \
  'mkdir -p storage/agent && chown -R www-data:www-data storage/agent'
```

Sem isso, o serviço cai para `sys_get_temp_dir()/mecanica-agent` (funciona, mas
os arquivos não sobrevivem a um restart do container).

---

## 1.1 Preenchimento dentro do formulário (principal)

O caminho recomendado para o dia a dia: o usuário descreve (ou fala) e o
**formulário real** é preenchido para revisão. Sem cartão de proposta, sem
segunda tela de confirmação.

```text
[ Preencher com IA ]  →  modal (texto ou microfone)
        ↓  POST /assistant/prefill
  o modelo devolve só os campos permitidos
        ↓
  o JS aplica no formulário e dispara as cascatas
        ↓
  CEP  → ViaCEP preenche rua/bairro/cidade/UF
  FIPE → marca → modelo → ano → valor
        ↓
  usuário revisa e salva
```

### O que a IA **não** preenche

Campos derivados ficam fora do schema (`PrefillSchema`) e são preenchidos pelo
sistema:

| Derivado | Quem preenche |
|---|---|
| rua, bairro, cidade, UF | ViaCEP, a partir do CEP |
| `fipe_brand_code`, `fipe_model_code`, `fipe_year_code`, `fipe_value` | cascata FIPE |
| totais, tarefas e itens | regras do domínio |

Se o texto falar “rua Av. Paulista, bairro Bela Vista”, a IA extrai **apenas o
CEP**; o ViaCEP devolve o endereço oficial. Isso evita divergência entre o que
o usuário ditou e o que o cadastro deve ter.

### Formulários suportados

| Formulário | Schema | Campos extraídos |
|---|---|---|
| Cliente | `client` | nome, CPF, telefone, e-mail, CEP, número, observações |
| Veículo | `vehicle` | cliente, placa, marca, modelo, ano, cor, KM, chassi |
| Nova OS | `service_order` | cliente, contato, CEP/número, placa, marca/modelo/ano, queixa, prioridade |

Se a placa/cliente já existir, o servidor devolve o **id** e o formulário
seleciona o registro em vez de criar duplicata. A busca por nome só aceita
match parcial quando existe exatamente um cliente.

### Adicionar outro formulário

1. Em `PrefillSchema::all()`, declarar `label`, `description`, `fields` e
   `derived`.
2. No template, adicionar um botão com `data-ai-prefill="<chave>"`.
3. Se houver cascata (CEP, FIPE), o aplicador em `ai-prefill.js` já cobre;
   para uma nova cascata, adicionar um resolvedor em `apply()`.

O endpoint é `POST /assistant/prefill` (auth + CSRF + 30 extrações/hora por
sessão). Ele não grava nada: só devolve campos.

---

## 2. Modelos

O usuário escolhe o modelo no próprio compositor. O catálogo fica em
`ModelCatalog::MODELS`.

| Modelo | Preço in/out por 1M | Visão | Reasoning | Uso |
|---|---|---|---|---|
| `openai/gpt-oss-20b` | $0,075 / $0,30 | não | low/medium/high | padrão, rápido |
| `openai/gpt-oss-120b` | $0,15 / $0,60 | não | low/medium/high | análises mais longas |
| `qwen/qwen3.8-27b` | $0,80 / $4,00 | **sim** (3 imagens) | none/low/medium/high | imagem + documento |

Regras aplicadas no servidor:

- o modelo vem de uma **allow-list**; id desconhecido cai no padrão;
- **anexo de imagem força o modelo com visão** e a interface recebe um aviso;
- `reasoning_effort` só é enviado quando o modelo suporta;
- `max_completion_tokens` cresce com o esforço, porque reasoning consome o
  mesmo orçamento da resposta;
- GPT-OSS não aceita `reasoning_format`; Qwen aceita `parsed`/`hidden`
  (nunca `raw`, que retorna 400 com tool calling).

### Reasoning

O raciocínio é exibido **somente para administradores**. Para os demais papéis o
servidor pede `include_reasoning=false` / `reasoning_format=hidden` e nem envia
os eventos — não é apenas escondido na interface.

Na interface, o painel aparece como "Pensando…" com shimmer, colapsa sozinho
quando a resposta começa e mostra a duração ("Pensou por 2,1s").

---

## 3. Arquitetura

```text
Navegador (chat)
   │  POST /assistant/stream  (SSE)
   ▼
AssistantController ──► AgentRunner ──► LlmClient ──► Groq
   │                        │
   │                        ├──► ToolRegistry ──► domínio (PDO)
   │                        │        └── leitura: tabelas/widgets
   │                        │        └── escrita: ProposalService
   │                        ├──► AttachmentService (PDF/imagem/áudio/texto)
   │                        └──► AgentStore (sessões, mensagens, uso)

Aprovação: POST /assistant/proposals/{id}/apply
   └──► ProposalService::apply ──► services do domínio + audit_logs
```

O agente **nunca escreve** no domínio. Ferramentas `propor_*` apenas criam uma
linha em `agent_proposals` com `payload` (o que o modelo pediu) e `preview` (o
que o domínio calculou a partir dos dados reais).

### Arquivos

```text
src/Modules/Agent/
├── AgentProvider.php                 rotas + menu
├── migrations/
│   ├── 0001_create_agent_tables.php
│   ├── 0002_add_proposal_fingerprint.php
│   └── 0003_create_agent_attachments.php
├── Controllers/AssistantController.php
├── Services/
│   ├── AgentRunner.php               loop, prompt, streaming, reasoning
│   ├── LlmClient.php                 HTTP + SSE + tool calls + reasoning
│   ├── ModelCatalog.php              modelos, capacidades, preços
│   ├── PartFinder.php                cruza veículo + ficha + catálogos
│   ├── VehicleSpecService.php        ficha técnica curada (fonte/confiança)
│   ├── OfferToken.php                assinatura das ofertas exibidas
│   ├── AttachmentService.php         upload, pdftotext, Whisper, imagens
│   ├── ToolRegistry.php              catálogo, RBAC, execução
│   ├── AgentStore.php                sessões, mensagens, limites
│   └── ProposalService.php           criar, rejeitar, aplicar
└── views/index.php

public/js/assistant.js                chat, markdown, widgets, SSE, anexos
public/css/assistant.css
```

### Tabelas

| Tabela | Papel |
|---|---|
| `agent_sessions` | conversas do usuário |
| `agent_messages` | histórico + `meta` (widgets, tools, uso, reasoning, anexos) |
| `agent_proposals` | propostas e o resultado da decisão |
| `agent_usage` | consumo diário (limites) |
| `agent_attachments` | arquivos, texto extraído, páginas, métricas |
| `vehicle_specs` | ficha técnica curada (óleo, filtros, pneus) com fonte e confiança |

### Propostas que se sobrescrevem

Cada proposta tem uma **intenção** (`intent`). Uma nova proposta com a mesma
intenção substitui a pendente anterior em vez de empilhar duplicatas:

| Ação | Intenção | Comportamento |
|---|---|---|
| abrir OS | `os:vehicle:<id>` | a nova substitui a anterior |
| pedido de peças | `partorder:os:<id>` | **mescla itens** (mesmo SKU/descrição substitui a quantidade; item novo entra na lista) |
| mudar status | `status:os:<id>` | a nova substitui a anterior |

A anterior fica com status `superseded` e o cartão na interface mostra
"substituída". Isso vale para novas solicitações; propostas antigas criadas
antes da migração não têm `intent`.

---

## 4. Anexos

| Tipo | Limite | Processamento | Vai para o modelo como |
|---|---|---|---|
| PDF | 10 MB / 40 páginas | `pdftotext -layout` (poppler-utils) | bloco `[ANEXO: ...]` delimitado |
| imagem (jpg/png/webp) | 5 MB, 3 por turno | validação por `finfo` + `getimagesize` | `image_url` base64 |
| áudio (webm/ogg/mp3/wav/m4a) | 10 MB | Groq Whisper (`GROQ_TRANSCRIPTION_MODEL`) | transcrição em texto |
| texto/CSV/JSON/MD | 10 MB | leitura direta, corte em 60k chars | bloco delimitado |

- Upload: `POST /assistant/attachments` (CSRF, tipo real por `finfo`, não por extensão).
- Download: `GET /assistant/attachments/{id}` com checagem de dono. Arquivos ficam
  **fora do webroot** (`storage/agent/<user_id>/<sha>.<ext>`).
- Texto extraído é truncado em 60k caracteres por turno.
- PDF digitalizado (sem camada de texto) retorna erro explicativo pedindo foto
  da página.
- A interface aceita clipe, arrastar-e-soltar e colar imagem.

Segurança: o conteúdo extraído entra no prompt como **dado delimitado**, com
instrução explícita de não obedecer comandos embutidos. Ainda assim, qualquer
escrita continua dependendo de proposta + aprovação humana.

---

## 5. Ferramentas

| Ferramenta | Papéis | Tipo |
|---|---|---|
| `buscar_os` | todos | leitura |
| `detalhar_os` | todos | leitura |
| `buscar_veiculo` | admin, secretaria, chefe | leitura |
| `buscar_cliente` | admin, secretaria, chefe | leitura |
| `buscar_peca` | admin, secretaria, chefe | leitura |
| `resumo_operacional` | admin, secretaria, chefe | leitura |
| `propor_abrir_os` | admin, secretaria, chefe | proposta |
| `propor_pedido_pecas` | todos | proposta |
| `propor_status_os` | admin, secretaria, chefe | proposta |
| `propor_cadastro_cliente` | admin, secretaria, chefe | proposta |
| `buscar_pecas_veiculo` | todos | leitura + escolha |
| `ficha_veiculo` | todos | leitura |
| `salvar_ficha_veiculo` | admin | gravação (dado de referência, auditado) |

### Adicionar uma ferramenta

1. Em `ToolRegistry::ROLES`, registrar `nome => papéis|null`.
2. Em `defs()`, descrever o schema JSON.
3. Implementar `private function runNome(array $args): array` retornando
   `ok`, `summary` e, opcionalmente, `renders`.

Ferramenta que altera algo **não grava**: chama `ProposalService::create()`.

---

## 6. Peças e ficha técnica

O fluxo é: **veículo → ficha → ofertas → escolha do mecânico → proposta**.

1. `ficha_veiculo` consulta `vehicle_specs` (viscosidade/capacidade do óleo,
   códigos de filtro, pneus) e devolve **fonte e confiança**. Se não estiver
   cadastrada, o assistente diz isso e não inventa.
2. `buscar_pecas_veiculo` recebe a descrição do mecânico (mesmo genérica),
   normaliza a categoria (óleo, filtro, freio, suspensão...) e cruza:
   - catálogo local (`parts`, com `applications`);
   - Canal da Peça (busca + preço + estoque + link).
3. A pontuação exige **evidência de compatibilidade**: o modelo do veículo
   precisa aparecer no nome/aplicação e o ano precisa caber na faixa. Sem isso,
   a oferta é descartada — em vez de sugerir peça errada.
4. O cartão mostra até 6 ofertas com preço, estoque, origem, "conferir" e
   **"Usar esta peça"**. Também gera links de conferência para **Mercado Livre**
   e **Google Shopping** (busca, sem scraping/API).
5. A escolha é assinada (HMAC, `APP_KEY`, 30 min) e vira item da proposta de
   pedido, com SKU, preço, fornecedor e URL. Clicar de novo no mesmo item
   substitui a quantidade — não duplica.

**Sobre ficha técnica:** não existe API pública confiável. A tabela é curada
pelo admin (ou pelo assistente com `salvar_ficha_veiculo`) e sempre registra a
fonte. A migração inicial traz Tracker 1.2 (2020–2026) e Onix 1.0, com fonte no
manual do proprietário Chevrolet.

**Sobre Mercado Livre:** a API pública de busca passou a exigir autenticação
(HTTP 403). Por isso o assistente entrega o **link de busca** para conferência,
não preços do ML. Preço estruturado vem do catálogo local e do Canal da Peça.

---

## 7. Widgets

Widget é propriedade do resultado da ferramenta, não da saída do modelo.

| `type` | Uso |
|---|---|
| `table` | listagens (`columns`, `rows`, `link`) |
| `kpi` | indicadores |
| `bars` | distribuição |
| `os_detail` | cartão da OS |
| `proposal` | cartão de aprovação |
| `parts_choice` | ofertas de peça com ficha, preço, link e escolha |
| `spec` | ficha técnica do veículo |
| `empty` | estado vazio |

Renderização em `public/js/assistant.js` (`renderWidget`), sempre com
`textContent` — nunca `innerHTML` com dados do servidor.

---

## 7. Interface

- layout sem navbar e sem rodapé, ocupando a tela inteira;
- streaming SSE com digitação contínua e botão **Parar**;
- seletor de modelo e de esforço (Rápido / Equilibrado / Profundo) no compositor;
- painel de reasoning (admin), colapsável e com duração;
- anexos: clipe, arrastar-e-soltar, colar imagem, chips com tipo/tamanho/páginas;
- timeline de ferramentas com duração e resultado;
- markdown (negrito, itálico, listas, código, links, tabelas);
- cartões de proposta com Aprovar/Rejeitar e status "substituída" para a anterior;
- cartão de peças com ficha do veículo, ofertas, link de conferência e escolha;
- comandos `/resumo`, `/os`, `/cliente`, `/peca`, `/abrir-os`, `/nova`, `/ajuda`;
- **áudio no compositor**: microfone ao lado do enviar, visualizador de
  espectro em tempo real, cronômetro; `Parar` transcreve para o campo (revisão)
  e `Enviar` durante a gravação transcreve e envia direto;
- **preencher com IA** nos formulários de cliente, veículo e nova OS, com
  texto ou voz e destaque nos campos preenchidos;
- atalhos: `Enter` envia, `Shift+Enter` quebra linha, `Esc` para/descarta a
  gravação, `Ctrl/⌘+Shift+K` nova conversa, `Ctrl/⌘+Shift+A` anexar;
- histórico de conversas, pendências e uso diário (tokens + custo estimado).

### Comportamento do agente

- **Criar ≠ buscar**: se o usuário diz que vai cadastrar um cliente/OS, o
  assistente monta a proposta de cadastro direto e pergunta só o que falta. Só
  pesquisa quando o pedido é consultar algo existente.
- **Sem widget vazio**: busca sem resultado responde em texto; nenhum cartão é
  renderizado sem conteúdo.
- **Respostas variadas**: o prompt evita abertura fixa, narração de ferramentas
  e repetição dos dados que já estão no cartão.

### Visualizador de áudio

Implementado com **Web Audio API (`AnalyserNode`) + canvas**, sem biblioteca
externa. As alternativas pesquisadas:

| Opção | Situação |
|---|---|
| `wavesurfer.js` (Record plugin) | BSD-3, mas ~100 kB e desenha forma de onda, não espectro |
| `audioMotion-analyzer` | 30 kB e ótimo visual, porém **AGPL-3.0** (incompatível com projeto proprietário) |
| Web Audio nativo | zero dependência, controle total, ~80 linhas |

A transcrição continua no `/api/transcribe` (Groq Whisper), o mesmo dos tickets.

---

## 8. O que foi validado

Testes manuais em 12/11/2026, com a API real da Groq:

- `openai/gpt-oss-20b`, `openai/gpt-oss-120b`, `qwen/qwen3.8-27b` e
  `whisper-large-v3-turbo` disponíveis na conta;
- turno com tool calling (`buscar_os`) + reasoning (admin) + widget de tabela;
- reasoning ausente para o papel `mechanic` (gate no servidor);
- upload de PDF → `pdftotext` → resposta citando o conteúdo do arquivo;
- upload de imagem → troca automática para o Qwen com aviso → descrição correta;
- custo estimado por mensagem (`US$ 0,0004` no 20B; `US$ 0,0032` no Qwen com imagem);
- aprovação de proposta criando OS + tarefas + pedido + ticket + auditoria;
- pedido de peças com SKU, preço, fornecedor e URL persistidos;
- criação repetida de proposta com a mesma intenção não duplica: a anterior vira
  `superseded` (status) e o pedido de peças mescla itens;
- layout sem navbar/rodapé em desktop e mobile, tema claro e escuro;
- áudio: gravação com visualizador, parada com transcrição no campo e envio
  direto durante a gravação;
- "vou cadastrar um cliente" gerou proposta sem busca prévia; busca sem
  resultado respondeu em texto, sem widget vazio.

---

## 9. Próximos passos

1. Aprovação em dois níveis para ações destrutivas.
2. Guardrail com `meta-llama/llama-prompt-guard-2-86m` sobre mensagem e anexos.
3. Retomada de stream interrompido.
4. Testes automatizados (proposta, apply, RBAC, escopo do mecânico).
5. Telegram como adaptador fino sobre a mesma API.
6. MCP como adaptador JSON-RPC sobre `defs()` + `run()`.
