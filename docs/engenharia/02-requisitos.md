# Requisitos e rastreabilidade

Cada requisito abaixo foi conferido no código em execução. A coluna "Evidência"
aponta a rota, o controller ou a tabela que comprova o requisito — não é uma
lista aspiracional.

## Atores

| Ator | Papel no sistema (`users.role`) | Escopo |
|---|---|---|
| Administrador | `admin` | Acesso total, incluindo funcionários, configurações e auditoria |
| Secretaria | `secretary` | Atendimento, clientes, veículos, OS, orçamentos, peças e pedidos |
| Mecânico-chefe | `lead_mechanic` | Operação técnica, equipe, tarefas, peças e relatórios |
| Mecânico | `mechanic` | Execução das OS atribuídas, tarefas, tickets e pedidos de peças |

A base de demonstração tem 9 usuários: 1 administrador, 3 secretarias, 1
mecânico-chefe e 4 mecânicos.

## Requisitos funcionais

| ID | Requisito | Prioridade | Evidência no código |
|---|---|---|---|
| RF01 | Autenticar usuário com e-mail e senha, com bloqueio por tentativas excessivas | Essencial | `GET/POST /login`, `Auth::attempt`, `LoginRateLimit` |
| RF02 | Gerenciar funcionários (cadastro, edição, desativação e perfil de acesso) | Essencial | CRUD `/users`, `UserResource` (somente `admin`) |
| RF03 | Restringir funcionalidades por perfil de usuário | Essencial | `Auth::requireRole`, `Resource::roles()`, `CrudController` |
| RF04 | Cadastrar, consultar, editar e desativar clientes, com validação de CPF e preenchimento de endereço por CEP | Essencial | CRUD `/clients`, `ClientResource`, `isValidCpf`, ViaCEP |
| RF05 | Cadastrar e gerenciar veículos vinculados ao cliente, com placa validada e dados FIPE | Essencial | CRUD `/vehicles`, `VehicleResource`, `FipeService` |
| RF06 | Abrir e manter ordens de serviço com número, cliente, veículo, prioridade e responsável | Essencial | `/service-orders`, `ServiceOrderController` |
| RF07 | Registrar a queixa inicial, o diagnóstico técnico e as observações internas da OS | Essencial | `initial_complaint`, `diagnosis`, `internal_notes`; rota `/service-orders/{id}/diagnosis` |
| RF08 | Distribuir tarefas e alocar responsáveis na OS | Importante | `/service-orders/{id}/tasks`, `/responsible`; tabelas `service_order_tasks` e `service_order_responsibles` |
| RF09 | Acompanhar o status da OS por fluxo controlado, com histórico de eventos | Essencial | `ServiceOrderStatus::next`, `/service-orders/{id}/status`, `service_order_events` |
| RF10 | Gerenciar catálogo de peças, categorias, preços, custo e estoque mínimo | Importante | CRUD `/parts`, `/parts/categories`, `PartResource` |
| RF11 | Solicitar peças vinculadas à OS e acompanhar o pedido até o recebimento | Importante | `/part-orders`, `PartOrderStatus`, `part_orders`, `part_order_items` |
| RF12 | Emitir orçamentos com itens de peça e mão de obra, desconto, validade e impressão | Essencial | `/budgets`, `BudgetController`, `BudgetStatus`, `/budgets/{id}/print` |
| RF13 | Registrar a entrega do veículo, com modalidade, responsável e KM de saída | Essencial | `/service-orders/{id}/delivery`, `service_order_deliveries` |
| RF14 | Manter histórico consultável e registrar ações sensíveis em auditoria | Importante | `service_order_events`, `audit_logs`, `/audit` |
| RF15 | Notificar usuários sobre eventos da operação | Desejável | `notifications`, `/notifications`, `/api/notifications` |
| RF16 | Trocar mensagens internas por OS em tickets, com anexos e leitura controlada | Importante | `/tickets`, `TicketService`, `ticket_messages`, `ticket_reads`, `agent_attachments` |
| RF17 | Emitir relatórios por período e exportar CSV | Importante | `/reports`, `/reports/export`, `ReportController` |
| RF18 | Usar assistente de IA para consultas, preenchimento e comunicação, com aprovação humana das alterações | Desejável | `/assistant/*`, `AgentRunner`, `ProposalService`, `agent_proposals` |

### Requisitos parcialmente atendidos

| ID | Situação |
|---|---|
| RF10 | O estoque é registrado (`parts.stock`, `min_stock`), mas a baixa automática na execução da OS ainda não está ligada ao fluxo. Existe a tabela `part_stock_movements`, sem uso pela interface. |
| RF16 | O anexo por áudio depende de chave da Groq; sem a chave, o recurso fica indisponível e a interface avisa. |
| RF18 | O assistente depende de `LLM_API_KEY`. Sem a chave, o chat não responde e o restante do sistema continua funcionando. |

## Requisitos não funcionais

| ID | Requisito | Prioridade | Evidência |
|---|---|---|---|
| RNF01 | Armazenar senhas com hash e nunca em texto puro | Essencial | `password_hash`/`password_verify` em `Auth`; rehash automático |
| RNF02 | Proteger a sessão contra fixação e acesso por script | Essencial | `Session::regenerate`, `session.use_strict_mode`, `HttpOnly`, `SameSite=Lax` |
| RNF03 | Aplicar autorização no servidor, e não apenas esconder botões | Essencial | `requireRole`, guardas no `CrudController`, 403 |
| RNF04 | Oferecer interface responsiva, com tema configurável e navegação por perfil | Importante | Bootstrap 5, `settings.theme_color`, menu montado por módulo |
| RNF05 | Responder em tempo aceitável com paginação no servidor e índices | Importante | `datatable` server-side, migration `0014_add_performance_indexes` |
| RNF06 | Subir o ambiente de forma reproduzível | Essencial | `Dockerfile`, `docker-compose.yml`, healthcheck do MySQL |
| RNF07 | Preservar a integridade dos dados | Essencial | Chaves estrangeiras, campos `NOT NULL`, validações de formulário |
| RNF08 | Rastrear quem fez o quê e quando | Importante | `audit_logs` (479 registros) e `service_order_events` (1.339 registros) |
| RNF09 | Permitir backup e restauração do banco | Importante | **Parcial**: não há rotina no sistema; usa-se `mysqldump` e o volume do Docker |
| RNF10 | Manter o código organizado e evolutivo | Importante | PSR-4, módulos com `module.json`, 32 migrations versionadas |
| RNF11 | Funcionar nos navegadores modernos, sem instalação no cliente | Importante | Aplicação web; ES modules; sem plugin |
| RNF12 | Permitir crescimento por novos módulos | Desejável | `Registry` descobre módulos e resolve dependências |

## Matriz de rastreabilidade (resumo)

| Requisito | Rotas principais | Tabelas |
|---|---|---|
| RF01, RF03 | `/login`, `/logout` | `users`, `login_tentativas` |
| RF02 | `/users` | `users` |
| RF04 | `/clients` | `clients` |
| RF05 | `/vehicles`, `/api/fipe/*` | `vehicles`, `fipe_*` |
| RF06–RF09, RF13, RF14 | `/service-orders` | `service_orders`, `service_order_*` |
| RF10, RF11 | `/parts`, `/part-orders` | `parts`, `part_categories`, `suppliers`, `part_orders`, `part_order_items` |
| RF12 | `/budgets` | `budgets`, `budget_items` |
| RF15 | `/notifications` | `notifications` |
| RF16 | `/tickets` | `tickets`, `ticket_messages`, `ticket_reads` |
| RF17 | `/reports` | consultas agregadas |
| RF18 | `/assistant` | `agent_*` |
| Auditoria | `/audit` | `audit_logs` |

## Fora do escopo desta versão

- Portal do cliente para acompanhar a OS.
- Aplicativo móvel nativo.
- Módulo financeiro (contas a pagar/receber e fluxo de caixa).
- Emissão de NF-e/NFS-e.
- Envio automático de WhatsApp ou e-mail (o sistema gera o texto para cópia).
- Assinatura digital e termo de entrega.
- Multiempresa: uma instalação atende uma oficina.
