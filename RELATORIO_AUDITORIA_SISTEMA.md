# Relatório de Auditoria do Sistema

**Data:** 09/11/2026 (auditoria inicial) · 11/11/2026 (revisão incremental)
**Escopo:** arquitetura, segurança, autorização, regras de negócio, integridade de dados, performance e qualidade.
**Resultado:** auditoria inicial com 170 verificações + revisão incremental por seção, regressão de páginas/perfis e fluxo completo validados.

---

## 1. Resumo executivo

A auditoria encontrou problemas relevantes principalmente em quatro frentes:

1. **Autorização**: o app novo havia perdido a regra do sistema legado que restringia o mecânico às OS em que ele participa. Mecânicos conseguiam listar, abrir, editar e excluir qualquer OS e qualquer pedido de peças.
2. **Segurança do login**: o login novo não usava o rate-limit nem o Turnstile já existentes no legado, permitindo força-bruta.
3. **Regras de negócio incompletas**: orçamento não tinha ação de aprovar/reprovar; entrega não concluía a OS; ticket encerrado aceitava mensagens; não-lidas usavam timestamp (perdia mensagens no mesmo segundo); notificações não chegavam a todos os participantes.
4. **Performance**: o catálogo de peças devolvia 4 MB por listagem; faltavam índices para filtros frequentes.

Todas as falhas críticas/altas foram corrigidas. As recomendações de médio/longo prazo estão na seção 7.

---

## 2. Método

- Leitura integral do kernel (`Auth`, `Session`, `Router`, `CrudController`, `QueryBuilder`, `Repository`, `Menu`, `ViewFactory`).
- Leitura dos controllers, resources, services, migrations e views dos módulos `Core` e `Mechanic`.
- Varredura por SQL dinâmico, `SELECT *`, interpolação de variáveis, XSS em views/JS, uploads, URLs externas e logs.
- Inspeção do schema MySQL (índices, FKs, enums, unique keys).
- Testes E2E via HTTP com usuários diferentes (admin, secretária, mecânico-chefe, mecânico e usuário de fora), validando banco e respostas.
- Medições de tempo/payload das telas e endpoints principais.

---

## 3. Segurança

### 3.1 Corrigido

| # | Achado | Correção |
|---|--------|----------|
| S1 | Login novo sem rate-limit e sem Turnstile (o legado tinha) | `AuthController` passou a usar `includes/login_rate_limit.php` e `includes/turnstile.php`; widget adicionado ao login; rate-limit testado (6ª tentativa bloqueada). |
| S2 | `session.use_strict_mode` desligado | Ativado em `Session::start()` antes de `session_start()`. |
| S3 | Logout sem validação de CSRF | `AuthController::logout` agora valida o token. |
| S4 | Troca de senha no perfil sem exigir a senha atual | Campo “Senha atual” + `password_verify` antes de alterar. |
| S5 | Auditoria gravava hash de senha no JSON | `record_audit()` remove `password`, `password_confirmation`, `current_password`, tokens e secrets. |
| S6 | Secretária/mecânico-chefe podiam gerenciar (e criar) administradores | Gestão de funcionários restrita ao papel `admin` (resource + menu + rota). |
| S7 | Exclusão de usuário/cliente/veículo podia apagar histórico ou deixar OS órfãs | Exclusão bloqueada; adoção de desativação (`is_active`) para clientes, veículos e funcionários. |
| S8 | JSON inline em wizard/orçamento podia quebrar o `<script>` (XSS armazenado) | `json_encode` com `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. |
| S9 | Cabeçalho do modal responsivo do DataTables inseria nome/placa sem escape | Valor passou por `esc()` antes de ir para o HTML. |
| S10 | URLs de produto/imagem podiam ser `javascript:` | Helper `safeUrl()` no front-end e validação `http(s)` no back-end. |
| S11 | `QueryBuilder` interpolava coluna/operador sem whitelist | Validação de identificadores e lista fechada de operadores. |
| S12 | Sem cabeçalhos de segurança | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e `Permissions-Policy` no `.htaccess`. |
| S13 | `display_errors`/`expose_php` expostos | `display_errors` controlado por `APP_DEBUG`; `expose_php=Off` e limites de upload no `Dockerfile` (requer rebuild). |
| S14 | Upload de áudio limitado a 2 MB pelo PHP, mas endpoint aceita 20 MB | Limites de 25 MB no `.htaccess` e no php.ini do Docker. |
| S15 | Acesso de mecânico a OS/pedidos de terceiros | Ver seção 4.1. |
| S16 | Dropdown de OS do novo ticket listava todas as OS | Filtrado por participação para não-admin. |

### 3.2 Recomendado (não bloqueante)

- **CSP**: não foi adicionada porque o sistema usa scripts/estilos inline e fontes externas. Recomenda-se migrar scripts inline para arquivos e então aplicar CSP com nonce.
- **HSTS**: só faz sentido com HTTPS obrigatório; não foi fixado no código.
- **Rate-limit do endpoint de transcrição**: qualquer usuário autenticado pode chamar o Groq; recomenda-se limite por usuário para controlar custo.
- **Backoff do login**: o `usleep` de até 2,5 s segura um worker PHP; em cenário de muitas tentativas, pode pressionar o Apache. Avaliar limitar o atraso ou usar rate-limit externo.
- **Remover `Nova pasta.7z`** do repositório/deploy (backup sem uso).
- **Revisar `storage/`**: os diretórios estão como `root`; em produção execute `chown -R www-data:www-data storage` (o cliente Canal da Peça já cai para `/tmp` se não puder escrever).

---

## 4. Autorização e regras de negócio

### 4.1 Autorização por participação (crítico — corrigido)

**Problema:** o sistema legado restringia o mecânico às OS em que era responsável. No app novo, `ServiceOrderResource::roles()` incluía `mechanic` e o controller só checava o papel — qualquer mecânico via/editava qualquer OS. O mesmo valia para pedidos de peças.

**Correção:**
- Novo `Modules\Mechanic\Support\OrderAccess`:
  - admin/secretaria/mecânico-chefe: acesso total;
  - mecânico: apenas OS em que é `created_by`, `lead_mechanic_id` ou está em `service_order_responsibles`.
- `ServiceOrderController`:
  - listagem/datatable filtrada por participação;
  - `show`, `edit`, `update`, `updateStatus`, `updateDiagnosis`, `updateInternalNotes`, `addTask`, `updateTask`, `updateTaskStatus` e `registerDelivery` com guarda;
  - criação de OS restrita a admin/secretaria/mecânico-chefe;
  - edição completa restrita a admin/secretaria/mecânico-chefe;
  - exclusão de OS bloqueada (usar status “Cancelada”).
- `PartOrderController`:
  - listagem/datatable filtrada por participação;
  - criação de pedido permitida ao mecânico apenas para OS em que participa;
  - edição/exclusão restritas a admin/secretaria/mecânico-chefe;
  - select de OS do formulário filtrado.
- `SearchController`: OS e pedidos filtrados; tickets agora incluem participantes da OS.
- `DashboardController`: listas de “OS em andamento”, “OS por cliente” e timeline filtradas para mecânicos.
- UI: botões de editar/status/entrega/designação/novo orçamento escondidos para mecânicos; ações de editar/excluir do datatable filtradas por papel (`userRole`/`roles`).
- Teste dedicado: 27 verificações de acesso (mecânico responsável vê; usuário de fora não vê; não altera status; não exclui; não edita pedido; busca/dashboard não vazam).

### 4.2 Orçamento sem aprovação (crítico — corrigido)

**Problema:** o orçamento nascia como `open` e não existia rota/tela para aprovar, reprovar ou cancelar. O faturamento e o ticket médio do dashboard (que usam `status='approved'`) nunca seriam alimentados pelo fluxo normal.

**Correção:**
- Rota `POST /budgets/{id}/status`.
- `BudgetController::updateStatus` com validação de status, auditoria e mensagem.
- Card “Status” na tela do orçamento.
- Teste: aprovar um orçamento de teste, validar `approved`, restaurar para `open`.

### 4.3 Entrega não concluía a OS (alto — corrigido)

**Problema:** o formulário de entrega/retirada gravava o registro, mas não alterava o status; a OS nunca chegava a `delivered`.

**Correção:**
- `registerDelivery` atualiza a OS para `delivered`, grava `completion_date`, registra evento e notifica os participantes (exceto quem registrou).
- Transições de status passaram a respeitar `ServiceOrderStatus::next()`; “Entregue” só pelo registro de entrega.
- Teste: fluxo `received → in_analysis → in_progress → ready_for_delivery → delivered` (13 verificações).

### 4.4 Ticket encerrado aceitava mensagens (alto — corrigido)

- `sendMessage` e o caminho de ticket existente no `store` retornam `409 ticket_closed`.
- Rodapé de mensagem escondido e aviso “Ticket encerrado. Reabra para enviar novas mensagens.” na UI.
- Teste: encerrar, tentar enviar (409), reabrir, enviar (201).

### 4.5 Não-lidas por timestamp (alto — corrigido)

**Problema:** `ticket_reads.last_read_at` tinha precisão de segundos; uma mensagem enviada no mesmo segundo da abertura podia ser considerada lida.

**Correção:**
- Migration `0013_add_last_read_message_to_ticket_reads`: coluna `last_read_message_id` + backfill.
- `markRead` grava o maior ID de mensagem; a contagem de não-lidas compara IDs.
- Teste: mensagem enviada logo após abrir aparece como não-lida.

### 4.6 Notificações incompletas (alto — corrigido)

- Tickets: notificações para todos os participantes (criador/responsável do ticket, criador/responsável da OS e mecânicos designados), sem auto-notificação.
- Pedidos de peças: notificação na criação e em cada mudança de status; criador do pedido incluído; resumo automático do ticket agora lista descrição × quantidade das peças.
- OS: status notifica participantes (não o autor); tarefa usa o número da OS; designação de responsável usa o número da OS e não duplica.
- Novo tipo `os_responsible` mapeado no sino e na página de notificações.
- Clique na notificação marca como lida antes de navegar.
- Testes: criação de OS/ticket/peças, troca de mensagens, status, tarefas, responsável e API do sino.

### 4.7 Exclusões que apagavam histórico (alto — corrigido)

- OS: exclusão bloqueada; usar status “Cancelada”.
- Funcionários: exclusão bloqueada; usar `is_active`.
- Clientes/veículos: exclusão bloqueada; usar `is_active`.
- Formulários ganharam o switch “Ativo”; listagens mostram a situação.
- Seleções de OS/orçamento só listam clientes/veículos ativos.
- O servidor valida que o veículo pertence ao cliente e está ativo (criação e edição de OS/orçamento).
- Teste: desativar/reativar funcionária (login bloqueado/liberado), cliente e veículo; tentativas de exclusão bloqueadas (20 verificações).

### 4.8 Outras correções

- **Sidebar de tickets**: seção “Atendimento” no topo, sempre expandida, com até 6 conversas + “Ver todos os tickets”, sem os atalhos fixos “Todos/Não lidos/Encerrados”. Cada conversa mostra título curto, número da OS e ponto de não lida; a conversa aberta é destacada e o topo da sidebar não é mais rolado para fora.
- Ticket manual: responsável validado (`is_active=1`); OS de terceiros não pode ser vinculada.
- OS sem ticket (legado): botão “Ticket” cria sob demanda, com resumo e notificação.
- `parts_json` com itens vazios não cria pedido de peças vazio.
- Criação de OS não aceita cliente/veículo inativo nem veículo de outro cliente.
- Edição de OS valida veículo × cliente × ativo.
- Perfil atualiza o e-mail na sessão e exige senha atual para troca.
- Login/logout gravam auditoria.

---

## 5. Performance e banco

### 5.1 Índices adicionados (migration 0014)

- `service_orders(status, entry_date)`, `(entry_date)`, `(completion_date)`
- `part_orders(status)`, `(expected_date)`
- `budgets(status, budget_date)`
- `tickets(status, updated_at)`
- `notifications(user_id, read_at)`
- `service_order_tasks(order_id, status)`
- `clients(name)`, `(email)`, `(phone)`
- `vehicles(is_active, plate)`
- `users(role, is_active)`
- `audit_logs(entity, entity_id)`, `(created_at)`

### 5.2 Catálogo de peças

- A listagem `/parts/datatable` devolvia **4,08 MB** (2558 peças) por incluir `p.*` e um join agregado não usado pela tela.
- Passou a selecionar apenas as colunas usadas: **1,92 MB** (redução de ~53%) e tempo de ~135 ms para ~52 ms.
- O join de ofertas foi removido da listagem (não era consumido pelo JS).

### 5.3 Cache

- `CanalDaPecaClient` agora cai para `/tmp/canaldapeca` quando `storage/cache` não é gravável, evitando perda silenciosa de cache no container.

### 5.4 Medições (admin, ambiente local)

| Tela | Tempo |
|------|-------|
| `/` | ~25 ms |
| `/service-orders` | ~12 ms |
| `/tickets` | ~25 ms |
| `/parts` | ~16 ms (após cache) |
| `/clients` | ~12 ms |
| `/reports` | ~15 ms |

### 5.5 Recomendado

- **DataTables server-side** para catálogos grandes (peças com 2558 linhas ainda geram ~1,9 MB). O DataTables atual é client-side e retorna todas as linhas (limite de 10.000).
- **Sequência de numeração**: `MAX(number)+1` para OS/orçamento tem condição de corrida sob concorrência. As unique keys impedem duplicidade, mas o segundo usuário recebe erro. Recomenda-se tabela de sequência ou retry.
- **Integração de estoque**: pedidos de peças/OS usam descrição livre; não baixam estoque do catálogo. Recomenda-se vincular `part_id` e gerar `part_stock_movements` ao receber/usar.
- **Retenção**: notificações, auditoria e arquivos de cache do Canal da Peça crescem sem limpeza. Agendar expurgo.
- **Dashboard**: os cards agregados (contagens totais) continuam globais para mecânicos; as listas já são filtradas. Definir se os agregados também devem ser por participação.

---

## 6. Testes executados

Suítes em `/tmp/opencode` (dados de teste removidos ao final):

| Suíte | Verificações | Cobertura |
|-------|--------------|-----------|
| `e2e_test.py` | 77 | OS → ticket → resumo → mensagens → acesso → encerrar/reabrir → peças → status → tarefas → responsável → sino → sidebar |
| `e2e_extra.py` | 22 | sidebar com poucos tickets, ticket encerrado na UI, dropdown de OS, ticket manual, filtros, marcar notificação |
| `e2e_fororder.py` | 11 | OS legada sem ticket + criação sob demanda + reaproveitamento |
| `e2e_delivery.py` | 13 | transições de status, bloqueio de “Entregue” direto, entrega conclui OS e notifica |
| `e2e_access.py` | 27 | mecânico x OS/pedidos, listagens, edição, exclusão, busca global, dashboard, telas gerenciais |
| `e2e_softdelete.py` | 20 | desativar/reativar funcionário/cliente/veículo e bloquear exclusões |
| **Total** | **170** | **todas passando** |

Testes manuais complementares:
- Rate-limit de login: 6ª tentativa bloqueada por 15 min.
- Troca de senha: senha atual errada rejeitada; senha correta aceita; senha restaurada.
- Aprovação de orçamento: status alterado e restaurado.
- Headers de segurança verificados via `curl -I`.

---

## 7. Recomendações priorizadas

1. **Curto prazo**
   - Rodar `php bin/migrate.php migrate` (Core/0010, Mechanic/0013 e Mechanic/0014).
   - Rebuildar a imagem Docker para aplicar `expose_php=Off` e limites de upload.
   - Ajustar ownership de `storage/` (`www-data`).
   - Revisar/commitar as mudanças do CRM de peças e dashboard feitas em paralelo.

2. **Médio prazo**
   - DataTables server-side para peças e demais listagens grandes.
   - Sequência de numeração de OS/orçamento.
   - Integração de estoque com pedidos/OS.
   - CSP com nonce (após remover scripts inline) e HSTS em produção.

3. **Longo prazo**
   - Expurgo agendado de notificações/auditoria/cache.
   - Rate-limit por usuário no endpoint de transcrição.
   - Testes automatizados no CI (PHPUnit + essas suítes E2E).
   - Remover código legado não servido (`api/`, `pages/`, `assets/` duplicado). Antes de remover `includes/`, mover `login_rate_limit.php` e `turnstile.php` para `src/Modules/Core/Support/`, pois o login novo depende deles.

---

## 8. Arquivos principais alterados

**Kernel/segurança**
- `src/Kernel/Auth/Auth.php`
- `src/Kernel/Http/Session.php`
- `src/Kernel/Database/QueryBuilder.php`
- `src/Kernel/Support/helpers.php`
- `src/Kernel/Crud/Resource.php`
- `src/Kernel/Crud/CrudController.php`
- `src/Kernel/views/crud/datatables.php`
- `src/Kernel/views/layouts/app.php`
- `public/index.php`, `public/.htaccess`, `.htaccess`, `Dockerfile`, `.env.example`

**Core**
- `src/Modules/Core/Controllers/AuthController.php`
- `src/Modules/Core/Controllers/ProfileController.php`
- `src/Modules/Core/Controllers/UserController.php`
- `src/Modules/Core/Controllers/ClientController.php`
- `src/Modules/Core/Controllers/SearchController.php`
- `src/Modules/Core/Controllers/DashboardController.php`
- `src/Modules/Core/Resources/UserResource.php`
- `src/Modules/Core/Resources/ClientResource.php`
- `src/Modules/Core/CoreProvider.php`
- views de login, perfil e notificações

**Mechanic**
- `src/Modules/Mechanic/Support/OrderAccess.php` (novo)
- `src/Modules/Mechanic/Controllers/ServiceOrderController.php`
- `src/Modules/Mechanic/Controllers/PartOrderController.php`
- `src/Modules/Mechanic/Controllers/BudgetController.php`
- `src/Modules/Mechanic/Controllers/TicketController.php`
- `src/Modules/Mechanic/Controllers/PartController.php`
- `src/Modules/Mechanic/Controllers/VehicleController.php`
- `src/Modules/Mechanic/Resources/ServiceOrderResource.php`
- `src/Modules/Mechanic/Resources/PartOrderResource.php`
- `src/Modules/Mechanic/Resources/VehicleResource.php`
- `src/Modules/Mechanic/Resources/BudgetResource.php`
- `src/Modules/Mechanic/Services/TicketService.php`
- `src/Modules/Mechanic/Services/CanalDaPecaClient.php`
- `src/Modules/Mechanic/MechanicProvider.php`
- views de OS, orçamento, peças, veículos e tickets

**Migrations**
- `Core/0010_create_login_tentativas.php`
- `Mechanic/0013_add_last_read_message_to_ticket_reads.php`
- `Mechanic/0014_add_performance_indexes.php`

**Front-end**
- `public/js/crud-datatable.js`
- `public/js/tickets.js`
- `public/js/notifications.js`
- `public/js/safe-url.js` (novo)
- `public/js/part-autocomplete.js`
- `public/js/part-order-items.js`
- `public/js/part-product-form.js`
- `public/js/parts-datatable.js`
- `public/js/os-wizard.js`
- `public/css/mecanica.css`

---

## 9. Revisão incremental por seção (11/11/2026)

Depois da auditoria inicial, cada seção foi revisada com correções de UX, regras de negócio e integridade. Commits principais: `49e1964`, `44f65eb`, `2ce4542`, `96ece93`, `2ccdddc`, `976ff89`, `5f99453`.

### 9.1 Dashboard e tickets
- Dashboard com KPIs, faturamento de 6 meses, ticket médio, status semanal e escopo por mecânico.
- Central de tickets com contexto/resumo recolhível, filtros na URL, status/responsável na lista, reatribuição, confirmação estilizada e persistência do estado da sidebar.
- Notificações e badges de não lidas revisados.

### 9.2 Peças e fornecedores
- Busca por marca/código, iniciais de avatar, seleção em massa, OEM achatado e fallback para imagens com 403.
- Estoque/referência, máscara de preço/custo, filtros e paginação server-side do catálogo.
- Fornecedores sem checkboxes e com filtro de situação.

### 9.3 Pedidos de peças
- Formulário do mecânico restrito a **OS + peças**, com alerta fixo de status “Solicitado”.
- Quick view da peça com marca, código, categoria, SKU, código de barras, preço, descrição, aplicação veicular e link.
- Card do veículo da OS, quantidade no modal e paginação de itens (5 por página).
- Fluxo **Solicitado → Validado → Pedido → A caminho → Recebido**; Cancelado encerra. Mecânico só cria como Solicitado; gestão mantém fornecedor, previsão e andamento.
- Página de detalhe somente leitura com itens, resumo, recebimento e histórico de auditoria.

### 9.4 Ordens de serviço
- Listagem sem checkboxes, com filtros de status/prioridade/responsável e coluna de responsável.
- Edição não permite pular etapas; “Entregue” só é concluído pelo registro de entrega.
- Entrega/retirada liberada apenas em “Pronta para entrega”, sem duplicidade; KM de saída valida e atualiza o veículo.
- Designação de responsável valida mecânico ativo; remoção registra evento.
- Mecânico edita apenas tarefas próprias ou sem responsável; gestão mantém controle total.
- Wizard com e-mail do cliente, responsável incluído na equipe e validação de mecânicos/tarefas.
- Edição de OS filtra veículos pelo cliente e adiciona o responsável à equipe.

### 9.5 Orçamentos
- Fluxo **Aberto → Aprovado/Reprovado/Cancelado**; reabertura para Aberto; edição somente em Aberto (validada no servidor).
- Editor com OS visível/filtrada, sugestão de veículo da OS, busca de peça no catálogo, preços em pt-BR e tabela mais legível.
- Desconto limitado ao subtotal; totais recalculados no servidor.
- Histórico de criação/status/atualização na tela do orçamento.
- Impressão com dados da oficina, contato do cliente, detalhes do veículo, termos e assinatura.
- Listagem com filtros de status/cliente, colunas de OS/validade e total sem quebra.

### 9.6 Clientes e veículos
- Cliente com CPF (máscara, validação e unicidade), observações e layout revisado.
- Página do cliente com status, edição, CPF, “cliente desde”, veículos com status/KM/link e novo veículo já vinculado.
- Listagens sem checkboxes, com filtros de situação/cidade (clientes) e situação/cliente (veículos).
- Veículo com KM atual, chassi e observações; edição sem FIPE preserva marca/modelo/ano.
- Cliente pré-selecionado ao criar veículo pela página do cliente.
- CPF vazio agora é gravado como `NULL`, evitando conflito de chave única.

### 9.7 Relatórios, configurações, auditoria e funcionários
- Relatórios com período (30 dias, mês, 90 dias, ano, tudo), KPIs no período, ticket médio, tabela de orçamentos por status e exportação CSV.
- Configurações com dados da oficina (nome, CNPJ/CPF, telefone, e-mail, endereço), validade padrão e termos de orçamento.
- Dados da oficina usados na impressão e no nome da sidebar/rodapé; validade padrão usada no editor.
- Auditoria com filtros por tipo/usuário/ação/entidade/período, ações/entidades traduzidas, status legíveis, detalhes amigáveis e links para os registros.
- Funcionários com salário no formulário, senha mínima de 8 caracteres, filtros de perfil/situação e sem checkboxes.
- Perfil permite editar telefone.

### 9.8 Validação final
- **Matriz de acesso:** admin, secretária, mecânico-chefe e mecânico testados em 13 rotas; permissões corretas.
- **Fluxo completo:** cliente → veículo → OS → orçamento aprovado → pedido de peça → transições de status → entrega, com KM atualizado e dados removidos ao final.
- **Regressão de páginas:** dashboard, tickets, peças, fornecedores, pedidos, OS, orçamentos, clientes, veículos, relatórios, configurações, auditoria, funcionários e perfil.
- **Base após limpeza:** 10 funcionários, 49 clientes, 67 veículos, 181 OS, 162 orçamentos e 106 pedidos de peças.
- PHP lint e JS lint sem erros; nenhum dado temporário remanescente.

### 9.9 Recomendações restantes
- CSP e HSTS dependem de migração de scripts inline e HTTPS obrigatório.
- Rate-limit dedicado para o endpoint de transcrição de áudio.
- Fallback manual de marca/modelo para veículos fora da FIPE.
- Paginação server-side para OS/orçamentos/funcionários se o volume crescer muito.
- Rotina de backup/restauração e monitoramento de erros em produção.

---

## 10. Conclusão

O sistema está funcional e as falhas críticas de autorização, login, exclusão de histórico e fluxo de orçamento/entrega foram corrigidas. A revisão incremental por seção consolidou UX, regras de status, permissões e integrações, com regressão de páginas, matriz de acesso por perfil e fluxo completo cliente → veículo → OS → orçamento → pedido → entrega. As recomendações restantes são de escala e endurecimento, não bloqueantes para o uso atual.


Nome	E-mail	Senha	Perfil
Administrador Geral	admin@mecanica.com	admin123	Administrador
Secretária	secretaria@mecanica.com	admin123	Secretaria
Mecânico	mecanico@mecanica.com	admin123	Mecânico
Carlos Souza	carlos.souza@mecanica.com	admin123	Mecânico-chefe
Fernanda Lima	fernanda.lima@mecanica.com	admin123	Mecânico
Rafael Alves	rafael.alves@mecanica.com	admin123	Mecânico
Juliana Prado	juliana.prado@mecanica.com	admin123	Secretaria
André Santos	andre.santos@mecanica.com	admin123	Mecânico
Patrícia Gomes	patricia.gomes@mecanica.com	admin123	Secretaria
Teste Funcionario	teste.func@mecanica.com	senha alterada (não é admin123)	Mecânico