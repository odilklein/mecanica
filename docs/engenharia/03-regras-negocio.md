# Regras de negócio

As regras abaixo saíram dos controllers, dos serviços e das classes de status.
Cada linha indica onde ela está no código.

## Acesso e sessão

| ID | Regra | Implementação |
|---|---|---|
| RN01 | O login exige e-mail e senha de usuário ativo. Senha incorreta não revela se o e-mail existe. | `Auth::attempt` |
| RN02 | Após 5 falhas do mesmo e-mail no mesmo IP em 15 minutos, o login é bloqueado até a janela expirar. O limite é configurável por `LOGIN_MAX_TENTATIVAS` e `LOGIN_JANELA_MINUTOS`. | `LoginRateLimit::check` |
| RN03 | Cada falha adiciona um atraso crescente na resposta, até cerca de 2,5 s. | `LoginRateLimit::registerFailure` |
| RN04 | Ao autenticar, o identificador da sessão é regenerado (proteção contra fixação de sessão). | `Session::regenerate` |
| RN05 | Toda requisição `POST` exige token CSRF válido. | `Controller::guardCsrf` |
| RN06 | A senha é armazenada com `password_hash` e atualizada automaticamente se o custo do algoritmo mudar. | `Auth::attempt` |

## Perfis e permissões

| Recurso | `admin` | `secretary` | `lead_mechanic` | `mechanic` |
|---|---|---|---|---|
| Funcionários (`/users`) | sim | não | não | não |
| Configurações (`/settings`) | sim | não | não | não |
| Auditoria (`/audit`) | sim | não | não | não |
| Clientes | sim | sim | sim | não |
| Veículos | sim | sim | sim | não |
| Peças, categorias e fornecedores | sim | sim | sim | não |
| Orçamentos | sim | sim | sim | não |
| Ordens de serviço (ver) | sim | sim | sim | sim |
| Criar/editar OS | sim | sim | sim | não |
| Pedidos de peças | sim | sim | sim | sim |
| Tickets | todos | próprios/atribuídos | próprios/atribuídos | próprios/atribuídos |
| Assistente de IA | sim | sim | sim | sim |

A verificação é feita no servidor (`Auth::requireRole` e `Resource::roles()`).
Esconder um botão na interface é apenas consequência; a rota também recusa.

## Ordem de serviço

| ID | Regra | Implementação |
|---|---|---|
| RN07 | A OS nasce com status `received` e recebe número sequencial. | `ServiceOrderController::store` |
| RN08 | Somente `admin`, `secretary` e `lead_mechanic` abrem OS. O mecânico executa as OS às quais está vinculado. | `ServiceOrderResource::createRoles`, `guardOrderAccess` |
| RN09 | O status só avança pelas transições permitidas; `delivered` e `cancelled` são finais. | `ServiceOrderStatus::next` |
| RN10 | Toda mudança de status gera um evento no histórico da OS. | `service_order_events` |
| RN11 | A entrega só pode ser registrada quando a OS está em `ready_for_delivery`. | `ServiceOrderController::registerDelivery` |
| RN12 | Cada OS aceita apenas um registro de entrega/retirada. | consulta a `service_order_deliveries` |
| RN13 | O KM de saída não pode ser menor que o KM atual do veículo; ao registrar, o KM do veículo é atualizado. | `registerDelivery`, transação |
| RN14 | Tarefas aceitam apenas `pending`, `in_progress` e `done`. | `updateTaskStatus` |
| RN15 | O mecânico só enxerga e altera OS em que é responsável, líder ou criador. | `guardOrderAccess` |

### Fluxo de status da OS

```
received ──► in_analysis ──► awaiting_approval ──► awaiting_part ──► in_progress ──► ready_for_delivery ──► delivered
    │             │                  │                  │                 │                    │
    └─────────────┴──────────────────┴──────────────────┴─────────────────┴────────────────────┴──► cancelled
```

`received → in_analysis | cancelled`
`in_analysis → awaiting_approval | in_progress | cancelled`
`awaiting_approval → awaiting_part | in_progress | cancelled`
`awaiting_part → in_progress | cancelled`
`in_progress → ready_for_delivery | cancelled`
`ready_for_delivery → delivered | cancelled`

## Orçamento

| ID | Regra | Implementação |
|---|---|---|
| RN16 | O orçamento só pode ser editado com status `open`. | `BudgetStatus::isEditable` |
| RN17 | As transições permitidas são: aberto → aprovado/reprovado/cancelado; aprovado e reprovado → aberto/cancelado; cancelado → aberto. | `BudgetStatus::TRANSITIONS` |
| RN18 | Os totais são recalculados no servidor a partir dos itens: peças + mão de obra − desconto (nunca negativo). | `BudgetController::recalculate` |
| RN19 | O desconto não pode ultrapassar a soma dos itens. | `min($discount, $parts + $labor)` |
| RN20 | O orçamento pode ser impresso em layout próprio. | `GET /budgets/{id}/print` |

## Pedido de peças

| ID | Regra | Implementação |
|---|---|---|
| RN21 | O fluxo é `requested → validated → ordered → on_the_way → received`; `cancelled` encerra em qualquer etapa anterior ao recebimento. | `PartOrderStatus::TRANSITIONS` |
| RN22 | Ao entrar em `received`, o sistema grava quem recebeu e quando. Edições posteriores preservam esses dados. | `PartOrderController::update` |
| RN23 | Toda mudança de status gera auditoria e notificação aos envolvidos na OS. | `record_audit`, `notifyPartOrder` |
| RN24 | O pedido pode ser vinculado a uma OS; ao mudar o vínculo, os tickets das duas OS são atualizados. | `refreshTicket` |
| RN25 | Peças de um pedido são substituídas em bloco dentro de uma transação. | `DELETE` + `saveItems` |

## Cadastros

| ID | Regra | Implementação |
|---|---|---|
| RN26 | CPF é validado pelos dígitos verificadores e é único entre clientes e funcionários. | `isValidCpf`, `unique` em `ClientResource`/`UserResource` |
| RN27 | Placa aceita o formato antigo (`ABC-1234`) e o Mercosul (`ABC1D23`) e é única. | `isValidPlate`, `VehicleResource` |
| RN28 | O CEP preenche logradouro, bairro e cidade via ViaCEP; o número é obrigatório. | `ClientResource`, JavaScript de formulário |
| RN29 | A FIPE é consultada por marca, modelo e ano, e o resultado fica em cache no banco. | `FipeService` |
| RN30 | Clientes, veículos, peças e fornecedores usam exclusão lógica (`is_active`), preservando o histórico. | `CrudController::destroy` |

## Tickets e notificações

| ID | Regra | Implementação |
|---|---|---|
| RN31 | O administrador vê todos os tickets; os demais veem apenas os que criaram ou lhes foram atribuídos. | `TicketService::visibleTickets` |
| RN32 | A leitura de cada ticket é registrada por usuário, para o contador de não lidas. | `ticket_reads` |
| RN33 | Mensagens de sistema (mudança de status, pedido recebido) são gravadas como `kind = system`. | `TicketService` |
| RN34 | Notificações têm estado lido/não lido por usuário. | `notifications.read_at` |

## Assistente de IA

| ID | Regra | Implementação |
|---|---|---|
| RN35 | O assistente nunca altera dados diretamente: ele cria uma **proposta** com status `pending`. | `ProposalService` |
| RN36 | A proposta só é aplicada após aprovação humana e verificação de permissão (`requires_role`). | `ProposalService::apply` |
| RN37 | Cada proposta tem impressão digital (`fingerprint`) e chave de idempotência, evitando duplicidade. | `agent_proposals` |
| RN38 | Propostas expiram e podem ser substituídas (`superseded`) por uma versão mais nova. | `agent_proposals.status` |
| RN39 | O uso de tokens é contabilizado por usuário e por dia. | `agent_usage` |

## Auditoria

São auditados: criação, alteração, exclusão e mudança de status de peças,
categorias, orçamentos, pedidos de peças, especificações de veículo e propostas
do assistente. O registro guarda usuário, entidade, identificador, ação, dados e
data (`audit_logs`). A base de demonstração tem 479 registros de auditoria e
1.339 eventos de OS.
