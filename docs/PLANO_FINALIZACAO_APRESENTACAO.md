# Plano de finalização, documentação e apresentação — PI 24/11/2026

> **Status analisado:** 17/11/2026 (7 dias antes da apresentação)
> **Última atualização:** 17/11/2026 — após limpeza da base, remoção da trigger e criação da documentação em `docs/PI/`
> **Repositório:** `odilklein/mecanica` · branch `master` · commit `a34e748`
> **Aplicação:** http://localhost:8080 (Docker ativo, banco com dados de operação)

---

## 1. Diagnóstico rápido

### 1.1 Veredito

O sistema **está funcionalmente pronto para demonstração**. Não há tela quebrada, erro de PHP ou migration pendente. O que falta não é "terminar o sistema": é **fechar a história da apresentação**, **limpar a base de demonstração** e **produzir a documentação acadêmica**, que hoje não existe no repositório.

Os itens que estavam pendentes no handoff anterior foram concluídos no commit `a34e748`:

- ✅ Tabela de clientes trocou **Cidade** por **Bairro** e **Situação** por **Veículos** (placas).
- ✅ Formulários passaram a ocupar a largura da tela.
- ✅ Campos **Ativo** e **Observações** saíram dos formulários de cliente/veículo.
- ✅ **Color picker** (Pickr do tema) no campo Cor do veículo.

### 1.2 Evidências verificadas nesta análise

| Verificação | Resultado |
|---|---|
| Login e navegação (admin, secretária e mecânico) | OK |
| 30+ rotas GET principais | Todas 200/302 esperados, sem 500 |
| Migrations | 32/32 aplicadas |
| Console do navegador | 1 erro na tela de login (✅ corrigido em 17/11) |
| Logs do Apache/PHP | Sem warnings ou erros |
| Criação de cliente pelo formulário novo | OK (teste feito e removido) |
| APIs internas (busca, notificações, peças, FIPE) | OK |
| Assistente de IA / chave Groq | Chave válida; modelos `gpt-oss-20b`, `qwen3.8-27b` e `whisper` disponíveis |
| Permissões do mecânico | Menu e dashboard restritos corretamente |
| Responsividade | Funciona, com 2 ajustes visuais no dashboard mobile |

### 1.3 Números do sistema (para a documentação)

| Métrica | Valor |
|---|---|
| Arquivos PHP | 135 |
| Linhas PHP | ~27.200 |
| Linhas JS + CSS | ~14.000 |
| Rotas explícitas | ~89 (mais os CRUDs genéricos) |
| Controllers | 23 |
| Views | 28 |
| Migrations | 32 |
| Tabelas da aplicação | 35 (+ tabelas legadas da demonstração) |
| Clientes / veículos | 46 / 64 (após limpeza) |
| Ordens de serviço | 176 |
| Orçamentos | 159 |
| Pedidos de peças | 100 |
| Tickets | 176 |
| Peças no catálogo | 2.558 |
| Modelos FIPE | 7.313 |

### 1.4 O que já está pronto (por módulo)

- **Autenticação e perfis:** login, rate-limit, Turnstile opcional, 4 papéis, escopo do mecânico.
- **Dashboard:** KPIs, faturamento, ticket médio, séries de 6 meses, fila de OS, pedidos de peças, visão por perfil.
- **Clientes e veículos:** CRUD, CPF, CEP/ViaCEP, FIPE, KM, chassi, cor com color picker, veículos por cliente.
- **Ordens de serviço:** wizard de abertura, diagnóstico, tarefas, equipe, prioridade, histórico, entrega/retirada com KM.
- **Orçamentos:** itens peça/mão de obra, catálogo, desconto, impressão, status e reabertura.
- **Peças e fornecedores:** catálogo de 2.558 itens, categorias, estoque, preço/custo, filtros, CSV.
- **Pedidos de peças:** vínculo com OS, itens, quick view, fluxo de status.
- **Tickets:** central de atendimento, conversa, anexos de áudio, notificações, submenu por conversa.
- **IA:** preenchimento de OS/cliente/veículo por texto ou voz, queixa assistida, texto para o cliente, assistente com ferramentas e propostas com aprovação humana.
- **Relatórios, configurações, auditoria, funcionários e perfil.**
- **Integrações:** FIPE, Canal da Peça, ViaCEP, Groq, Cloudflare Turnstile.

### 1.5 O que foi concluído em 17/11/2026

- ✅ **Trigger `trg_os_status_historico` removida** do banco (decisão do grupo: não usar trigger na apresentação).
- ✅ **Base limpa:** removidos clientes/veículos/OS/orçamentos/pedidos/tickets de teste ("João Teste", "Cliente Auditoria", "Teste Funcionario", "asdsad").
- ✅ **Configurações da oficina preenchidas** (nome, CNPJ fictício, telefone, e-mail, endereço, cidade/UF e termos do orçamento).
- ✅ **Impressão de orçamento corrigida:** endereço com separador e sem a marca interna `[seed-op]`.
- ✅ **Erro de console no login corrigido** (`templateName is not defined`).
- ✅ **Ownership de `storage/` ajustado** para `www-data`.
- ✅ **Documentação do PI criada** em `docs/PI/` (documento, requisitos, modelagem, arquitetura, manual, testes e slides).
- ✅ **Documento oficial preenchido** em `docs/Padrão Documentação ES2_226 - PREENCHIDO.docx` e `.pdf` (43 páginas, com diagramas e prints).
- ✅ **Roteiro de slides e demo criado** em `docs/PI/07_SLIDES_E_ROTEIRO.md`.

> A view `vw_ordens_servico_detalhada` e as tabelas legadas continuam no banco, mas não são usadas pela aplicação. Podem ser removidas em uma limpeza futura; não impactam a demo.

---

## 2. O que falta para "finalizar"

### 2.1 Bloqueadores da demo (P0 — fazer antes de 24/11)

| # | Item | Situação | Por que importa |
|---|---|---|---|
| P0.1 | ~~Alinhar trigger/view~~ | ✅ **Resolvido:** trigger removida; apresentação não usa o tema. | O grupo decidiu não usar trigger. A view legada continua no banco, mas não é usada. |
| P0.2 | Limpar a base de demonstração | ✅ **Concluído em 17/11.** | Evita "João Teste", "Cliente Auditoria" e mensagens de teste na demo. |
| P0.3 | Preencher Configurações da oficina | ✅ **Concluído em 17/11.** | A impressão do orçamento agora sai com cabeçalho completo. |
| P0.4 | **Ensaio cronometrado do fluxo completo** | ⏳ Pendente | Cliente → veículo → OS → orçamento → peça → entrega precisa caber em 5–6 min. |
| P0.5 | **Vídeo de backup da demo** | ⏳ Pendente | Internet, Groq, projetor e microfone são riscos reais. |
| P0.6 | **Definir o título e a identidade do projeto** | ⏳ Pendente | README diz "Auto Mecânica", sidebar diz "Mecânica Leme", repositório diz "mecanica". Padronizar. |

> **Sobre a trigger:** ela foi removida do banco em 17/11/2026. O histórico da OS continua sendo gravado pela aplicação em `service_order_events`. Se a banca perguntar, a resposta é: "optamos por manter a regra na aplicação, com migrations e testes; a trigger era do banco legado e foi removida."

### 2.2 Importantes (P1 — se der tempo antes da apresentação)

| # | Item | Situação | Esforço |
|---|---|---|---|
| P1.1 | Erro de console no login (`templateName is not defined`) | ✅ **Corrigido em 17/11** | — |
| P1.2 | `/api/fipe/*` e `/api/notifications` sem autenticação | ⏳ Pendente | 30 min |
| P1.3 | Exportação CSV ignora `from`/`to` (só usa `period`) | ⏳ Pendente | 20 min |
| P1.4 | Dashboard mobile: sparkline do faturamento vaza do card | ⏳ Pendente | 30 min |
| P1.5 | Edição de veículo não pré-seleciona FIPE quando o carro veio do seed | ⏳ Pendente | 1 h |
| P1.6 | Impressão de OS / termo de entrega não existe (só orçamento) | ⏳ Pendente | 3 h |
| P1.7 | Placeholder de data em inglês (`mm/dd/yyyy`) nos prazos de tarefa | ⏳ Pendente | 20 min |
| P1.8 | Rotacionar a chave Groq do `.env` antes de zipar o projeto | ⏳ Pendente | 5 min |

### 2.3 Fora do escopo desta apresentação (P2 — citar como "trabalhos futuros")

- Portal do cliente (acompanhar OS).
- Módulo financeiro (contas a pagar/receber, fluxo de caixa).
- Emissão de NF-e / integração fiscal.
- Envio de e-mail/WhatsApp (hoje só gera o texto para copiar).
- Catálogo de serviços/mão de obra (hoje é texto livre no orçamento).
- Baixa automática de estoque ao receber pedido/usar peça.
- Fotos/anexos na OS.
- DataTables server-side para catálogos grandes.
- Sequência de numeração de OS/orçamento sem condição de corrida.
- Suíte de testes no repositório + CI (hoje só há relatório de testes manuais).
- Backup automático e monitoramento em produção.
- Política de privacidade/LGPD (o sistema guarda CPF, telefone e endereço).

---

## 3. Documentação

### 3.1 O que já existe e pode ser reaproveitado

| Documento | Uso na entrega |
|---|---|
| `README.md` | Visão técnica, stack, instalação, comandos e checklist de produção. |
| `docs/RELATORIO_AUDITORIA_SISTEMA.md` | Capítulo de **qualidade, segurança e testes** (170 verificações). |
| `docs/ASSISTENTE.md` | Capítulo de **IA**: arquitetura, ferramentas, segurança e limitações. |
| `docs/RELATORIO_CANAL_DA_PECA.md` | Capítulo de **integração com o catálogo de peças**. |
| `docs/PI/` | **Documentação completa do PI** (documento, requisitos, modelagem, arquitetura, manual, testes e slides). |

### 3.2 Estrutura do documento do PI (já criada)

1. **Capa, resumo e sumário**
2. **Introdução** — contexto da oficina, problema, objetivo geral e específicos, justificativa.
3. **Escopo** — o que o sistema faz e o que não faz (usar a seção 2.3 como "não escopo").
4. **Análise de requisitos**
   - Atores e perfis (administrador, secretaria, mecânico-chefe, mecânico).
   - Requisitos funcionais (RF01…RFnn) por módulo.
   - Requisitos não funcionais (RNF): usabilidade, segurança, desempenho, portabilidade.
   - Regras de negócio (RN): fluxo de OS, orçamento, entrega, permissões.
5. **Modelagem**
   - Diagrama de casos de uso + descrição dos principais.
   - DER (diagrama entidade-relacionamento).
   - Dicionário de dados das tabelas principais.
   - Diagrama de classes/módulos.
   - Diagramas de sequência: abrir OS, aprovar orçamento, registrar entrega.
6. **Arquitetura**
   - Visão em camadas: front controller → kernel → módulos → MySQL.
   - Padrões: MVC modular, container de DI, migrations, resources/CRUD.
   - Stack e decisões técnicas.
   - Segurança: CSRF, RBAC, rate-limit, auditoria, sessão, headers.
   - Integrações externas (FIPE, Canal da Peça, ViaCEP, Groq, Turnstile).
7. **Implementação** — telas principais, fluxos e o diferencial de IA.
8. **Banco de dados** — schema, migrations, índices e integridade.
9. **Testes** — plano, casos e evidências (reaproveitar a auditoria).
10. **Resultados e limitações.**
11. **Conclusão e trabalhos futuros.**
12. **Referências.**
13. **Anexos** — manual do usuário, scripts SQL, roteiro da demo.

### 3.3 Artefatos (situação em 17/11/2026)

- [x] Documento principal do PI → `docs/PI/01_DOCUMENTO.md`
- [x] Tabela de requisitos funcionais e não funcionais → `docs/PI/02_REQUISITOS.md`
- [x] Diagrama de casos de uso → `docs/PI/02_REQUISITOS.md`
- [x] DER + dicionário de dados → `docs/PI/03_MODELAGEM.md`
- [x] Diagrama de arquitetura e de sequência → `docs/PI/04_ARQUITETURA.md`
- [x] Manual do usuário por perfil → `docs/PI/05_MANUAL_USUARIO.md`
- [x] Plano de testes consolidado → `docs/PI/06_TESTES.md`
- [x] Slides da apresentação → `docs/PI/07_SLIDES_E_ROTEIRO.md`
- [x] Roteiro cronometrado da demo → `docs/PI/07_SLIDES_E_ROTEIRO.md`
- [ ] Converter para `.docx`/`.pdf` e preencher capa/integrantes.
- [ ] Montar o arquivo de slides (PPTX/Canva) a partir do roteiro.

---

## 4. Apresentação

### 4.1 Estrutura sugerida (12–15 min)

| # | Slide | Tempo | Ideia central |
|---|---|---|---|
| 1 | Capa | 20 s | Nome, integrantes, curso, data. |
| 2 | O problema | 1 min | Oficina controla OS em papel/planilha; perde histórico, orçamento e comunicação. |
| 3 | A solução | 1 min | Sistema web único, do atendimento à entrega. |
| 4 | Perfis e visão geral | 1 min | Quem usa e o que cada um vê. |
| 5 | Arquitetura | 1 min | PHP modular + MySQL + IA, com diagrama simples. |
| 6 | **Demo 1** — atendimento | 2 min | Cliente → veículo → OS. |
| 7 | **Demo 2** — orçamento e peças | 2 min | Orçamento → aprovação → pedido → status. |
| 8 | **Demo 3** — execução e entrega | 1,5 min | Tarefas → pronta → entrega com KM. |
| 9 | Diferencial de IA | 2 min | Preenchimento por voz/texto e assistente com aprovação humana. |
| 10 | Banco de dados | 1 min | Schema, migrations, índices e integridade. |
| 11 | Qualidade e segurança | 1 min | RBAC, auditoria, CSRF, 170 verificações. |
| 12 | Resultados e limitações | 1 min | O que funciona, o que fica para depois. |
| 13 | Conclusão | 30 s | Retomada do problema e do ganho. |

### 4.2 Roteiro da demo (ordem exata)

1. **Login como administrador** (`admin@mecanica.com` — senha rotacionada; veja `~/.config/mecanica/credenciais-rotacionadas.txt` ou rode `bin/create_admin.php`).
2. Dashboard: mostrar os KPIs e a fila de OS.
3. **Abrir OS com IA:** descrever "Cliente João Pedro, Golf prata, barulho na suspensão" e revisar o preenchimento.
4. Escolher cliente e veículo já cadastrados; mostrar a queixa assistida.
5. Criar a OS; abrir o ticket gerado automaticamente.
6. Criar orçamento com uma peça do catálogo e mão de obra; imprimir.
7. Aprovar o orçamento; criar pedido de peça; mostrar o fluxo de status.
8. Voltar à OS: concluir tarefa, mudar status até "Pronta para entrega".
9. Registrar entrega; mostrar o KM atualizado e o evento no histórico.
10. Abrir o **Assistente** e pedir "resumo do dia" (ou usar o modo mock).

### 4.3 Perguntas prováveis da banca

- Por que PHP e não um framework? (modularidade própria, controle, escopo do curso)
- Como vocês garantem que o mecânico não vê OS de outro? (escopo por participação)
- O que acontece se dois usuários criarem OS ao mesmo tempo? (limitação conhecida + unique key)
- A IA pode alterar dados sozinha? (não: proposta + aprovação humana)
- Por que não tem trigger? (regra na aplicação, com migrations e testes; a trigger era do banco legado)
- Como seria a implantação real? (Docker, migrations, backup, HTTPS)

### 4.4 Riscos e planos B

| Risco | Impacto | Plano B |
|---|---|---|
| Internet/Groq instável | IA não responde | `AGENT_MOCK=true` + vídeo gravado |
| Microfone bloqueado | Demo de voz falha | Usar digitação; apresentar em `localhost` |
| Projetor muda resolução | Layout quebra | Testar em 1280×720 e 1920×1080 |
| Base com dados de teste | Credibilidade | Reseed + limpeza (✅ feito) |
| Tempo estourado | Corta a conclusão | Ensaio cronometrado e slide de backup |

---

## 5. Cronograma 17 → 24/11

| Data | Foco | Entregável |
|---|---|---|
| **17/11 (ter)** | Decisões, limpeza e documentação | ✅ Trigger removida, base limpa, configurações preenchidas, P1.1 corrigido, docs/PI criado |
| **18/11 (qua)** | Revisão da documentação | Conferir RF/RNF/RN, casos de uso e DER; preencher capa/integrantes |
| **19/11 (qui)** | Diagramas e manual | Revisar DER no draw.io, manual do usuário e plano de testes |
| **20/11 (sex)** | Documento final | Converter Markdown para PDF/DOCX; revisar texto e referências |
| **21/11 (sáb)** | Slides | Montar o PPTX/Canva com os 13 slides e as falas |
| **22/11 (dom)** | Ensaio | Demo cronometrada + vídeo de backup |
| **23/11 (seg)** | Revisão | Correções finais, checklist do dia, ensaio geral |
| **24/11 (ter)** | Apresentação | — |

---

## 6. Checklist do dia da apresentação

- [ ] **Na véspera (23/11):** `docker compose exec app php bin/seed_operacao.php --fresh` para atualizar as datas da base (dashboard dos últimos 7 dias).
- [ ] `docker compose up -d` e conferir http://localhost:8080.
- [ ] `php bin/migrate.php status` — tudo aplicado.
- [ ] Login dos 4 perfis testado.
- [ ] Base de demonstração conferida (sem "Teste").
- [ ] Configurações da oficina preenchidas.
- [ ] Vídeo de backup acessível offline.
- [ ] Slides em PDF e em PPTX.
- [ ] Aba do phpMyAdmin aberta para a demo de SQL.
- [ ] Cronômetro de ensaio.
- [ ] **Não** rodar `docker compose down -v` antes da apresentação.

---

## 7. Decisões tomadas e pendências

**Tomadas em 17/11:**

1. **Trigger:** não será usada; foi removida do banco.
2. **Base de demonstração:** limpa e com configurações preenchidas.
3. **Documentação:** criada em `docs/PI/` (Markdown).
4. **Apresentação:** roteiro de 13 slides com 3 demos.

**Pendências do grupo:**

1. **Identidade:** "Auto Mecânica", "Mecânica Leme" ou outro nome? (hoje o README e a sidebar divergem)
2. **Documento:** converter Markdown para PDF/DOCX e preencher capa/integrantes.
3. **Slides:** montar o PPTX/Canva a partir do roteiro.
4. **Vídeo de backup** da demo.
5. **Divisão do grupo:** quem apresenta cada bloco e quem responde cada pergunta.
6. **Dados da oficina:** trocar o CNPJ/endereço fictícios pelos reais, se for exigido.
