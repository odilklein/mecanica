# Modelo de dados

Dicionário gerado automaticamente do schema do banco `mecanica` (MySQL 8.4).
Para regenerar: `python3 docs/build/gerar_dicionario.py`.

## Visão geral

| Grupo | Tabelas |
|---|---|
| Acesso, configuração e rastreabilidade | 6 |
| Cadastros e dados veiculares | 7 |
| Operação da oficina | 5 |
| Orçamentos | 2 |
| Peças, fornecedores e pedidos | 6 |
| Catálogo externo (Canal da Peça) | 5 |
| Atendimento e comunicação | 3 |
| Assistente de IA | 5 |
| **Total da aplicação** | **39** |

O banco tem ainda 9 tabelas remanescentes do protótipo procedural, mantidas
apenas como histórico e **não utilizadas** pela aplicação atual. Elas estão
listadas no fim deste documento.

## Domínios

O modelo se organiza em quatro domínios, ligados pela OS:

1. **Cadastros** — `clients` e `vehicles` são a base do atendimento.
2. **Operação** — `service_orders` concentra o serviço; tarefas, responsáveis,
   eventos e entrega pendem dela.
3. **Comercial** — `budgets` e `part_orders` documentam o que foi orçado e o
   que precisou ser comprado.
4. **Apoio** — usuários, auditoria, notificações, catálogo externo e assistente.

## Acesso, configuração e rastreabilidade

### `users`

Funcionários e usuários do sistema, com perfil de acesso (`admin`, `secretary`, `lead_mechanic`, `mechanic`).

Registros na base de demonstração: **9**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `email` | `varchar(120)` | NO | UNI |  |  |
| `password` | `varchar(255)` | NO |  |  |  |
| `role` | `enum('admin','secretary','lead_mechanic','mechanic')` | NO | MUL |  | mechanic |
| `cpf` | `varchar(14)` | YES | UNI |  |  |
| `phone` | `varchar(20)` | YES |  |  |  |
| `salary` | `decimal(10,2)` | YES |  |  |  |
| `hire_date` | `date` | YES |  |  |  |
| `job_title` | `varchar(100)` | YES |  |  |  |
| `is_active` | `tinyint(1)` | NO |  |  | 1 |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

### `login_tentativas`

Falhas de login por e-mail e IP, usadas pelo bloqueio temporário.

Registros na base de demonstração: **0**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `email` | `varchar(120)` | NO | MUL |  |  |
| `ip` | `varchar(45)` | NO |  |  |  |
| `criado_em` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

### `settings`

Configuração chave-valor da oficina (nome, contato, tema, textos de orçamento e SEO).

Registros na base de demonstração: **13**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `setting_key` | `varchar(80)` | NO | PRI |  |  |
| `setting_value` | `text` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

### `notifications`

Notificações endereçadas a um usuário, com marcação de leitura.

Registros na base de demonstração: **92**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `user_id` | `int` | NO | MUL |  |  |
| `created_by` | `int` | YES | MUL |  |  |
| `type` | `varchar(50)` | NO |  |  |  |
| `message` | `varchar(255)` | NO |  |  |  |
| `link` | `varchar(255)` | YES |  |  |  |
| `read_at` | `datetime` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `user_id` → `users.id` (`fk_notif_user`)
- `created_by` → `users.id` (`fk_notifications_creator`)

### `audit_logs`

Trilha de auditoria das ações sensíveis, com usuário, entidade, ação e dados em JSON.

Registros na base de demonstração: **479**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `user_id` | `int` | YES | MUL |  |  |
| `entity` | `varchar(60)` | NO | MUL |  |  |
| `entity_id` | `bigint` | YES |  |  |  |
| `action` | `varchar(40)` | NO |  |  |  |
| `data` | `json` | YES |  |  |  |
| `created_at` | `timestamp` | YES | MUL | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `user_id` → `users.id` (`fk_audit_user`)

### `migrations`

Controle das migrations aplicadas e do lote de execução.

Registros na base de demonstração: **31**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `migration` | `varchar(255)` | NO | UNI |  |  |
| `batch` | `int` | NO |  |  | 1 |
| `ran_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

## Cadastros e dados veiculares

### `clients`

Clientes da oficina, com CPF, contato, endereço e exclusão lógica.

Registros na base de demonstração: **46**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `name` | `varchar(120)` | NO | MUL |  |  |
| `cpf` | `varchar(14)` | YES | UNI |  |  |
| `phone` | `varchar(20)` | YES | MUL |  |  |
| `email` | `varchar(120)` | YES | MUL |  |  |
| `zip` | `varchar(10)` | YES |  |  |  |
| `address` | `varchar(255)` | YES |  |  |  |
| `address_number` | `varchar(20)` | YES |  |  |  |
| `neighborhood` | `varchar(80)` | YES |  |  |  |
| `city` | `varchar(80)` | YES |  |  |  |
| `state` | `varchar(2)` | YES |  |  |  |
| `notes` | `text` | YES |  |  |  |
| `is_active` | `tinyint(1)` | NO |  |  | 1 |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

### `vehicles`

Veículos vinculados a um cliente, com placa, dados FIPE, KM e chassi.

Registros na base de demonstração: **67**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `client_id` | `int` | YES | MUL |  |  |
| `plate` | `varchar(10)` | YES | UNI |  |  |
| `fipe_brand_code` | `varchar(10)` | YES |  |  |  |
| `fipe_model_code` | `varchar(20)` | YES |  |  |  |
| `fipe_year_code` | `varchar(20)` | YES |  |  |  |
| `fipe_year` | `int` | YES |  |  |  |
| `fipe_value` | `decimal(10,2)` | YES |  |  |  |
| `fipe_reference` | `varchar(20)` | YES |  |  |  |
| `brand` | `varchar(80)` | YES |  |  |  |
| `model` | `varchar(120)` | YES |  |  |  |
| `year` | `smallint` | YES |  |  |  |
| `color` | `varchar(30)` | YES |  |  |  |
| `current_km` | `int` | NO |  |  | 0 |
| `chassis` | `varchar(30)` | YES |  |  |  |
| `notes` | `text` | YES |  |  |  |
| `is_active` | `tinyint(1)` | NO | MUL |  | 1 |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `client_id` → `clients.id` (`fk_vehicles_client`)

### `fipe_brands`

Cache de marcas da tabela FIPE.

Registros na base de demonstração: **105**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `code` | `varchar(10)` | NO | UNI |  |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

### `fipe_models`

Cache de modelos da tabela FIPE, por marca.

Registros na base de demonstração: **7313**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `brand_id` | `int` | NO | MUL |  |  |
| `code` | `varchar(20)` | NO |  |  |  |
| `name` | `varchar(150)` | NO |  |  |  |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `brand_id` → `fipe_brands.id` (`fk_fipe_models_brand`)

### `fipe_years`

Cache de anos/versões de cada modelo FIPE.

Registros na base de demonstração: **93**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `model_id` | `int` | NO | MUL |  |  |
| `code` | `varchar(20)` | NO |  |  |  |
| `name` | `varchar(60)` | NO |  |  |  |
| `year` | `int` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `model_id` → `fipe_models.id` (`fk_fipe_years_model`)

### `fipe_prices`

Cache de preços FIPE por modelo, ano e combustível.

Registros na base de demonstração: **10**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `model_id` | `int` | NO | MUL |  |  |
| `year_code` | `varchar(20)` | NO |  |  |  |
| `year` | `int` | YES |  |  |  |
| `fuel` | `varchar(30)` | YES |  |  |  |
| `value` | `decimal(10,2)` | YES |  |  |  |
| `fipe_code` | `varchar(20)` | YES |  |  |  |
| `reference_month` | `varchar(20)` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `model_id` → `fipe_models.id` (`fk_fipe_prices_model`)

### `vehicle_specs`

Especificações técnicas por marca/modelo/ano (óleo, filtros, pneus), usadas pelo assistente.

Registros na base de demonstração: **2**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `make` | `varchar(80)` | NO | MUL |  |  |
| `model` | `varchar(120)` | NO |  |  |  |
| `year_from` | `smallint` | YES |  |  |  |
| `year_to` | `smallint` | YES |  |  |  |
| `engine` | `varchar(60)` | YES |  |  |  |
| `oil_viscosity` | `varchar(20)` | YES |  |  |  |
| `oil_capacity_l` | `decimal(4,2)` | YES |  |  |  |
| `oil_spec` | `varchar(120)` | YES |  |  |  |
| `oil_filter_code` | `varchar(60)` | YES |  |  |  |
| `air_filter_code` | `varchar(60)` | YES |  |  |  |
| `cabin_filter_code` | `varchar(60)` | YES |  |  |  |
| `fuel_filter_code` | `varchar(60)` | YES |  |  |  |
| `tire_size` | `varchar(40)` | YES |  |  |  |
| `notes` | `text` | YES |  |  |  |
| `source` | `varchar(255)` | YES |  |  |  |
| `confidence` | `enum('high','medium','low')` | NO |  |  | medium |
| `updated_by` | `int` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `updated_by` → `users.id` (`fk_vehicle_specs_user`)

## Operação da oficina

### `service_orders`

Ordens de serviço: cliente, veículo, status, prioridade, queixa, diagnóstico e líder técnico.

Registros na base de demonstração: **182**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `number` | `int` | NO | UNI |  |  |
| `client_id` | `int` | YES | MUL |  |  |
| `vehicle_id` | `int` | YES | MUL |  |  |
| `status` | `enum('received','in_analysis','awaiting_approval','awaiting_part','in_progress','ready_for_delivery','delivered','cancelled')` | NO | MUL |  | received |
| `priority` | `enum('low','normal','high','urgent')` | NO |  |  | normal |
| `initial_complaint` | `text` | YES |  |  |  |
| `diagnosis` | `text` | YES |  |  |  |
| `internal_notes` | `text` | YES |  |  |  |
| `entry_date` | `date` | YES | MUL |  |  |
| `completion_date` | `date` | YES | MUL |  |  |
| `lead_mechanic_id` | `int` | YES | MUL |  |  |
| `created_by` | `int` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `vehicle_id` → `vehicles.id` (`fk_so_vehicle`)
- `lead_mechanic_id` → `users.id` (`fk_so_lead`)
- `client_id` → `clients.id` (`fk_so_client`)
- `created_by` → `users.id` (`fk_so_created`)

### `service_order_tasks`

Tarefas de cada OS, com responsável, prazo e status.

Registros na base de demonstração: **525**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `order_id` | `int` | NO | MUL |  |  |
| `description` | `varchar(255)` | NO |  |  |  |
| `status` | `enum('pending','in_progress','done')` | NO |  |  | pending |
| `assigned_to` | `int` | YES | MUL |  |  |
| `due_date` | `date` | YES |  |  |  |
| `notes` | `text` | YES |  |  |  |
| `completed_at` | `datetime` | YES |  |  |  |
| `completed_by` | `int` | YES | MUL |  |  |
| `created_by` | `int` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `assigned_to` → `users.id` (`fk_sot_assigned`)
- `completed_by` → `users.id` (`fk_sot_completed`)
- `order_id` → `service_orders.id` (`fk_sot_order`)
- `created_by` → `users.id` (`fk_sot_user`)

### `service_order_responsibles`

Equipe alocada em cada OS e data de conclusão individual.

Registros na base de demonstração: **298**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `order_id` | `int` | NO | MUL |  |  |
| `user_id` | `int` | NO | MUL |  |  |
| `assigned_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `completed_at` | `datetime` | YES |  |  |  |

**Chaves estrangeiras**

- `order_id` → `service_orders.id` (`fk_sor_order`)
- `user_id` → `users.id` (`fk_sor_user`)

### `service_order_events`

Histórico cronológico de eventos e mudanças de status da OS.

Registros na base de demonstração: **1339**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `order_id` | `int` | NO | MUL |  |  |
| `type` | `varchar(40)` | NO |  |  |  |
| `description` | `varchar(255)` | NO |  |  |  |
| `created_by` | `int` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `order_id` → `service_orders.id` (`fk_soe_order`)
- `created_by` → `users.id` (`fk_soe_user`)

### `service_order_deliveries`

Registro de entrega ou retirada do veículo, com modalidade, responsável e KM de saída.

Registros na base de demonstração: **153**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `order_id` | `int` | NO | MUL |  |  |
| `modality` | `enum('pickup','delivery')` | NO |  |  | pickup |
| `responsible` | `varchar(120)` | YES |  |  |  |
| `destination` | `varchar(255)` | YES |  |  |  |
| `exit_km` | `int` | YES |  |  |  |
| `status` | `varchar(40)` | NO |  |  | registered |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `order_id` → `service_orders.id` (`fk_sod_order`)

## Orçamentos

### `budgets`

Orçamentos vinculados a cliente, veículo e, opcionalmente, a uma OS.

Registros na base de demonstração: **159**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `number` | `int` | NO | UNI |  |  |
| `client_id` | `int` | YES | MUL |  |  |
| `vehicle_id` | `int` | YES | MUL |  |  |
| `service_order_id` | `int` | YES | MUL |  |  |
| `budget_date` | `date` | YES |  |  |  |
| `valid_until` | `date` | YES |  |  |  |
| `status` | `enum('open','approved','rejected','cancelled')` | NO | MUL |  | open |
| `parts_total` | `decimal(10,2)` | NO |  |  | 0.00 |
| `labor_total` | `decimal(10,2)` | NO |  |  | 0.00 |
| `discount` | `decimal(10,2)` | NO |  |  | 0.00 |
| `total` | `decimal(10,2)` | NO |  |  | 0.00 |
| `notes` | `text` | YES |  |  |  |
| `created_by` | `int` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `client_id` → `clients.id` (`fk_budget_client`)
- `service_order_id` → `service_orders.id` (`fk_budget_order`)
- `created_by` → `users.id` (`fk_budget_user`)
- `vehicle_id` → `vehicles.id` (`fk_budget_vehicle`)

### `budget_items`

Itens do orçamento, do tipo peça ou mão de obra, com quantidade, preço e subtotal.

Registros na base de demonstração: **472**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `budget_id` | `int` | NO | MUL |  |  |
| `type` | `enum('part','service')` | NO |  |  | part |
| `description` | `varchar(200)` | NO |  |  |  |
| `quantity` | `decimal(10,2)` | NO |  |  | 1.00 |
| `unit_price` | `decimal(10,2)` | NO |  |  | 0.00 |
| `subtotal` | `decimal(10,2)` | NO |  |  | 0.00 |

**Chaves estrangeiras**

- `budget_id` → `budgets.id` (`fk_item_budget`)

## Peças, fornecedores e pedidos

### `parts`

Catálogo de peças, com preço, custo, estoque, estoque mínimo e dados do catálogo externo.

Registros na base de demonstração: **2359**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `name` | `varchar(200)` | NO | MUL |  |  |
| `brand` | `varchar(120)` | YES | MUL |  |  |
| `part_code` | `varchar(80)` | YES | MUL |  |  |
| `sku` | `varchar(80)` | YES |  |  |  |
| `barcode` | `varchar(80)` | YES |  |  |  |
| `description` | `text` | YES |  |  |  |
| `image` | `varchar(500)` | YES |  |  |  |
| `images` | `json` | YES |  |  |  |
| `category` | `varchar(120)` | YES |  |  |  |
| `category_id` | `int` | YES | MUL |  |  |
| `price` | `decimal(10,2)` | YES |  |  |  |
| `cost_price` | `decimal(10,2)` | YES |  |  |  |
| `stock` | `int` | NO |  |  | 0 |
| `min_stock` | `int` | NO |  |  | 0 |
| `location` | `varchar(80)` | YES |  |  |  |
| `supplier` | `varchar(160)` | YES |  |  |  |
| `supplier_url` | `varchar(500)` | YES |  |  |  |
| `source` | `varchar(30)` | NO |  |  | manual |
| `cdp_sku` | `bigint` | YES | UNI |  |  |
| `is_active` | `tinyint(1)` | NO |  |  | 1 |
| `status` | `varchar(20)` | NO | MUL |  | published |
| `tags` | `varchar(255)` | YES |  |  |  |
| `applications` | `text` | YES |  |  |  |
| `oem_codes` | `json` | YES |  |  |  |
| `product_url` | `varchar(500)` | YES |  |  |  |
| `universal` | `tinyint(1)` | NO |  |  | 0 |
| `by_request` | `tinyint(1)` | NO |  |  | 0 |
| `by_budget` | `tinyint(1)` | NO |  |  | 0 |
| `weight` | `decimal(10,3)` | YES |  |  |  |
| `warranty_months` | `int` | YES |  |  |  |
| `notes` | `text` | YES |  |  |  |
| `created_by` | `int` | YES |  |  |  |
| `last_synced_at` | `datetime` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `category_id` → `part_categories.id` (`fk_parts_category`)

### `part_categories`

Categorias de peças, com ícone e cor.

Registros na base de demonstração: **14**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `name` | `varchar(120)` | NO | MUL |  |  |
| `slug` | `varchar(120)` | NO | UNI |  |  |
| `description` | `text` | YES |  |  |  |
| `icon` | `varchar(50)` | NO |  |  | package |
| `color` | `varchar(20)` | NO |  |  | primary |
| `is_active` | `tinyint(1)` | NO |  |  | 1 |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

### `suppliers`

Fornecedores de peças.

Registros na base de demonstração: **18**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `name` | `varchar(160)` | NO | UNI |  |  |
| `document` | `varchar(30)` | YES |  |  |  |
| `phone` | `varchar(30)` | YES |  |  |  |
| `email` | `varchar(120)` | YES |  |  |  |
| `notes` | `text` | YES |  |  |  |
| `is_active` | `tinyint(1)` | NO |  |  | 1 |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

### `part_stock_movements`

Movimentações de estoque (estrutura criada; ainda sem uso pela interface).

Registros na base de demonstração: **0**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `part_id` | `int` | NO | MUL |  |  |
| `type` | `varchar(20)` | NO |  |  | adjustment |
| `quantity` | `int` | NO |  |  |  |
| `balance_after` | `int` | NO |  |  | 0 |
| `unit_cost` | `decimal(10,2)` | YES |  |  |  |
| `reason` | `varchar(200)` | YES |  |  |  |
| `reference` | `varchar(120)` | YES |  |  |  |
| `user_id` | `int` | YES |  |  |  |
| `created_at` | `timestamp` | YES | MUL | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `part_id` → `parts.id` (`fk_part_movements_part`)

### `part_orders`

Pedidos de peças vinculados a uma OS, com fornecedor, previsão e status.

Registros na base de demonstração: **100**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `service_order_id` | `int` | NO | MUL |  |  |
| `supplier` | `varchar(120)` | YES |  |  |  |
| `status` | `enum('requested','validated','ordered','on_the_way','received','cancelled')` | NO | MUL |  | requested |
| `expected_date` | `date` | YES | MUL |  |  |
| `notes` | `text` | YES |  |  |  |
| `created_by` | `int` | YES | MUL |  |  |
| `received_by` | `int` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `received_at` | `datetime` | YES |  |  |  |

**Chaves estrangeiras**

- `received_by` → `users.id` (`fk_po_received`)
- `service_order_id` → `service_orders.id` (`fk_po_order`)
- `created_by` → `users.id` (`fk_po_created`)

### `part_order_items`

Itens de cada pedido de peças, com SKU, quantidade e preço.

Registros na base de demonstração: **158**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `part_order_id` | `int` | NO | MUL |  |  |
| `description` | `varchar(200)` | NO |  |  |  |
| `sku` | `varchar(40)` | YES |  |  |  |
| `quantity` | `int` | NO |  |  | 1 |
| `unit_price` | `decimal(10,2)` | YES |  |  |  |
| `supplier` | `varchar(160)` | YES |  |  |  |
| `product_url` | `varchar(255)` | YES |  |  |  |
| `catalog_notes` | `varchar(255)` | YES |  |  |  |

**Chaves estrangeiras**

- `part_order_id` → `part_orders.id` (`fk_poi_order`)

## Catálogo externo (Canal da Peça)

### `cdp_products`

Cache de produtos do catálogo Canal da Peça.

Registros na base de demonstração: **2385**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `sku_id` | `bigint` | NO | UNI |  |  |
| `brand` | `varchar(120)` | YES | MUL |  |  |
| `part_code` | `varchar(80)` | YES | MUL |  |  |
| `name` | `varchar(255)` | NO | MUL |  |  |
| `name_simple` | `varchar(200)` | YES |  |  |  |
| `description` | `text` | YES |  |  |  |
| `category` | `varchar(200)` | YES |  |  |  |
| `applications` | `text` | YES |  |  |  |
| `oem_codes` | `json` | YES |  |  |  |
| `images` | `json` | YES |  |  |  |
| `product_url` | `varchar(255)` | YES |  |  |  |
| `universal` | `tinyint(1)` | NO |  |  | 0 |
| `by_request` | `tinyint(1)` | NO |  |  | 0 |
| `by_budget` | `tinyint(1)` | NO |  |  | 0 |
| `raw_json` | `json` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

### `cdp_offers`

Ofertas de vendedores por produto do Canal da Peça, com preço e estoque.

Registros na base de demonstração: **3551**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `sku_id` | `bigint` | NO | MUL |  |  |
| `seller_name` | `varchar(160)` | YES |  |  |  |
| `seller_type` | `varchar(40)` | YES |  |  |  |
| `city` | `varchar(120)` | YES |  |  |  |
| `state` | `char(2)` | YES |  |  |  |
| `price` | `decimal(10,2)` | YES | MUL |  |  |
| `stock` | `int` | YES |  |  |  |
| `lead_time` | `int` | YES |  |  |  |
| `packing_time` | `int` | YES |  |  |  |
| `partners_part_code` | `varchar(80)` | YES |  |  |  |
| `is_best` | `tinyint(1)` | NO |  |  | 0 |
| `consulted_at` | `datetime` | NO |  |  |  |

### `cdp_vehicle_makes`

Cache de marcas de veículo do Canal da Peça.

Registros na base de demonstração: **9**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `vehicle_type` | `varchar(30)` | NO | MUL |  |  |
| `slug` | `varchar(80)` | NO |  |  |  |
| `name` | `varchar(120)` | NO |  |  |  |

### `cdp_vehicle_models`

Cache de modelos do Canal da Peça.

Registros na base de demonstração: **53**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `make_id` | `int` | NO | MUL |  |  |
| `slug` | `varchar(120)` | NO |  |  |  |
| `name` | `varchar(160)` | NO |  |  |  |

**Chaves estrangeiras**

- `make_id` → `cdp_vehicle_makes.id` (`fk_cdp_model_make`)

### `cdp_vehicle_years`

Cache de anos compatíveis do Canal da Peça.

Registros na base de demonstração: **1032**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `model_id` | `int` | NO | MUL |  |  |
| `year` | `smallint` | NO |  |  |  |

**Chaves estrangeiras**

- `model_id` → `cdp_vehicle_models.id` (`fk_cdp_year_model`)

## Atendimento e comunicação

### `tickets`

Tickets de conversa interna, geralmente vinculados a uma OS.

Registros na base de demonstração: **180**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `int` | NO | PRI | auto_increment |  |
| `subject` | `varchar(200)` | NO |  |  |  |
| `service_order_id` | `int` | YES | MUL |  |  |
| `created_by` | `int` | NO | MUL |  |  |
| `assigned_to` | `int` | YES | MUL |  |  |
| `status` | `enum('open','closed')` | NO | MUL |  | open |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `updated_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `assigned_to` → `users.id` (`fk_tickets_assigned`)
- `created_by` → `users.id` (`fk_tickets_creator`)
- `service_order_id` → `service_orders.id` (`fk_tickets_order`)

### `ticket_messages`

Mensagens dos tickets, incluindo mensagens de sistema.

Registros na base de demonstração: **173**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `ticket_id` | `int` | NO | MUL |  |  |
| `user_id` | `int` | NO | MUL |  |  |
| `message` | `text` | NO |  |  |  |
| `kind` | `enum('message','system')` | NO |  |  | message |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `ticket_id` → `tickets.id` (`fk_tm_ticket`)
- `user_id` → `users.id` (`fk_tm_user`)

### `ticket_reads`

Última leitura de cada ticket por usuário.

Registros na base de demonstração: **376**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `ticket_id` | `int` | NO | PRI |  |  |
| `user_id` | `int` | NO | PRI |  |  |
| `last_read_at` | `datetime` | YES |  |  |  |
| `last_read_message_id` | `bigint` | YES |  |  |  |

**Chaves estrangeiras**

- `ticket_id` → `tickets.id` (`fk_tr_ticket`)
- `user_id` → `users.id` (`fk_tr_user`)

## Assistente de IA

### `agent_sessions`

Sessões de conversa do assistente de IA.

Registros na base de demonstração: **6**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `user_id` | `int` | NO | MUL |  |  |
| `title` | `varchar(160)` | YES |  |  |  |
| `status` | `enum('active','archived')` | NO |  |  | active |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |
| `last_activity_at` | `timestamp` | YES |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `user_id` → `users.id` (`fk_agent_session_user`)

### `agent_messages`

Mensagens do assistente, com papel, ferramentas chamadas e consumo de tokens.

Registros na base de demonstração: **34**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `session_id` | `bigint` | NO | MUL |  |  |
| `role` | `enum('user','assistant','tool','system')` | NO |  |  |  |
| `content` | `mediumtext` | YES |  |  |  |
| `tool_name` | `varchar(80)` | YES |  |  |  |
| `tool_calls` | `json` | YES |  |  |  |
| `meta` | `json` | YES |  |  |  |
| `tokens_in` | `int` | NO |  |  | 0 |
| `tokens_out` | `int` | NO |  |  | 0 |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `session_id` → `agent_sessions.id` (`fk_agent_message_session`)

### `agent_attachments`

Anexos enviados ao assistente (imagem, PDF, texto ou áudio).

Registros na base de demonstração: **0**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `user_id` | `int` | NO | MUL |  |  |
| `session_id` | `bigint` | YES | MUL |  |  |
| `message_id` | `bigint` | YES | MUL |  |  |
| `filename` | `varchar(255)` | NO |  |  |  |
| `mime` | `varchar(120)` | NO |  |  |  |
| `kind` | `enum('image','pdf','text','audio')` | NO |  |  |  |
| `bytes` | `int` | NO |  |  | 0 |
| `sha256` | `char(64)` | NO |  |  |  |
| `storage_path` | `varchar(255)` | NO |  |  |  |
| `extracted_text` | `mediumtext` | YES |  |  |  |
| `pages` | `int` | YES |  |  |  |
| `duration_seconds` | `int` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `session_id` → `agent_sessions.id` (`fk_agent_att_session`)
- `message_id` → `agent_messages.id` (`fk_agent_att_message`)
- `user_id` → `users.id` (`fk_agent_att_user`)

### `agent_proposals`

Propostas de alteração geradas pelo assistente, sujeitas a aprovação humana.

Registros na base de demonstração: **2**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `id` | `bigint` | NO | PRI | auto_increment |  |
| `session_id` | `bigint` | YES | MUL |  |  |
| `user_id` | `int` | NO | MUL |  |  |
| `tool_name` | `varchar(80)` | NO |  |  |  |
| `intent` | `varchar(120)` | YES |  |  |  |
| `fingerprint` | `char(64)` | YES |  |  |  |
| `payload` | `json` | NO |  |  |  |
| `preview` | `json` | NO |  |  |  |
| `status` | `enum('pending','approved','rejected','expired','applied','failed','superseded')` | NO | MUL |  | pending |
| `requires_role` | `varchar(40)` | YES |  |  |  |
| `expires_at` | `datetime` | YES |  |  |  |
| `approved_by` | `int` | YES | MUL |  |  |
| `superseded_by` | `bigint` | YES |  |  |  |
| `decided_at` | `datetime` | YES |  |  |  |
| `applied_at` | `datetime` | YES |  |  |  |
| `result` | `json` | YES |  |  |  |
| `error` | `varchar(255)` | YES |  |  |  |
| `idempotency_key` | `varchar(64)` | NO | UNI |  |  |
| `created_at` | `timestamp` | YES |  | DEFAULT_GENERATED | CURRENT_TIMESTAMP |

**Chaves estrangeiras**

- `approved_by` → `users.id` (`fk_agent_proposal_approver`)
- `session_id` → `agent_sessions.id` (`fk_agent_proposal_session`)
- `user_id` → `users.id` (`fk_agent_proposal_user`)

### `agent_usage`

Consumo diário de tokens por usuário.

Registros na base de demonstração: **6**.

| Coluna | Tipo | Nulo | Chave | Extra | Padrão |
|---|---|---|---|---|---|
| `user_id` | `int` | NO | PRI |  |  |
| `day` | `date` | NO | PRI |  |  |
| `turns` | `int` | NO |  |  | 0 |
| `tokens_in` | `bigint` | NO |  |  | 0 |
| `tokens_out` | `bigint` | NO |  |  | 0 |

**Chaves estrangeiras**

- `user_id` → `users.id` (`fk_agent_usage_user`)

## Tabelas legadas (fora de uso)

| Tabela | Situação |
|---|---|
| `clientes` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `usuarios` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `veiculos` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `ordens_servico` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `os_eventos` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `os_responsaveis` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `os_tarefas` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `pedidos_pecas` | Remanescente do protótipo procedural; não é lida pela aplicação. |
| `vw_ordens_servico_detalhada` | Remanescente do protótipo procedural; não é lida pela aplicação. |
