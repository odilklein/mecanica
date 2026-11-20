# Arquitetura do sistema

## Visão geral

O Torque é um **monólito modular** em PHP 8.2, servido pelo Apache, sem
framework de aplicação. O núcleo (`src/Kernel/`) guarda o que todos os módulos usam. Cada domínio fica em `src/Modules/`.

```
Navegador (HTML + Bootstrap 5 + JS)
        │  HTTP
        ▼
Apache  ──►  public/index.php (front controller)
                 │
                 ▼
              Kernel::boot()
                 ├── Session
                 ├── Container (league/container)
                 ├── Router (bramus/router)
                 ├── Auth · ViewFactory · Menu
                 └── Registry → descobre e registra os módulos
                 │
                 ▼
              Router::run()
                 │
                 ▼
        Controller (Core | Mechanic | Agent)
                 │
        ┌────────┴────────┐
        ▼                 ▼
   View (Plates)      PDO (MySQL 8.4)
        │                 │
        └──────► HTML ◄───┘
```

## Camadas

| Camada | Pasta | Responsabilidade |
|---|---|---|
| Entrada | `public/` | `index.php`, `.htaccess`, assets e uploads públicos |
| Núcleo | `src/Kernel/` | Container, router, sessão, autenticação, CRUD, banco, views, menu |
| Módulos | `src/Modules/` | Regras de negócio por domínio |
| Infraestrutura | `bin/`, `docker-compose.yml` | Migrations, seeds, sincronização e ambiente |
| Armazenamento | `storage/` | Cache de integrações e logs |

### Núcleo (`src/Kernel/`)

| Componente | Arquivo | Papel |
|---|---|---|
| `Kernel` | `Kernel.php` | Orquestra o boot: sessão, container, router e módulos |
| `Container` | `Container/Container.php` | Embrulha o `league/container` com autowiring |
| `Router` | `Http/Router.php` | Wrapper do `bramus/router`; resolve controllers pelo container e converte o retorno em resposta |
| `Session` | `Http/Session.php` | Sessão, flash messages e token CSRF |
| `Auth` | `Auth/Auth.php` | Login, hash de senha, papéis e `requireRole` |
| `Controller` | `Http/Controller.php` | Base com guarda de CSRF, helpers de view e resposta |
| `CrudController` | `Crud/CrudController.php` | CRUD genérico dirigido por `Resource` |
| `Resource` | `Crud/Resource.php` | Declara campos, colunas, filtros, papéis e ações de cada recurso |
| `Registry` | `Modules/Registry.php` | Lê `module.json`, ordena por dependência e registra providers |
| `ViewFactory` | `View/ViewFactory.php` | Plates com uma pasta por módulo (`core::`, `mechanic::`, `agent::`) |
| `Menu` | `Menu/Menu.php` | Menu compartilhado, montado pelos módulos |

### Módulos

| Módulo | Depende de | Conteúdo |
|---|---|---|
| `core` | — | Login, dashboard, clientes, funcionários, configurações, auditoria, perfil, notificações, busca e transcrição |
| `mechanic` | `core` | Veículos, OS, orçamentos, peças, categorias, fornecedores, pedidos, tickets e relatórios |
| `agent` | `core`, `mechanic` | Assistente de IA, propostas, anexos e ferramentas |

Cada módulo tem `module.json`, `*Provider.php` (registro de rotas e menu),
`Controllers/`, `Services/`, `Resources/`, `views/` e `migrations/`.

## Ciclo de uma requisição

1. O Apache recebe a requisição. Se o caminho não for um arquivo real, o
   `.htaccess` reescreve para `public/index.php`.
2. `index.php` carrega o autoload do Composer e o `.env`.
3. `Kernel::boot()` inicia a sessão, monta o container, registra os serviços
   compartilhados e chama o `Registry`.
4. O `Registry` varre `src/Modules/*/module.json`, ordena os módulos por
   dependência e executa o `register()` de cada provider, que declara as rotas.
5. `Router::run()` encontra a rota e resolve o controller pelo container.
6. O controller valida CSRF, confere o papel do usuário, executa a regra de
   negócio com PDO e devolve uma view ou um redirecionamento.
7. A view é renderizada pelo Plates dentro do layout `kernel::layouts/app`.

## Padrões adotados

| Padrão | Onde | Por quê |
|---|---|---|
| Front controller | `public/index.php` | Ponto único de entrada e de configuração |
| Injeção de dependência | `Container` | Controllers e serviços recebem o que precisam no construtor |
| Registry + manifesto | `module.json` | Novo módulo entra sem alterar o núcleo |
| Template Method | `CrudController` + `Resource` | CRUD repetitivo fica declarativo; regra específica sobrescreve |
| Repository informal | Controllers + PDO | SQL explícito, sem ORM e sem mágica |
| Service layer | `Services/` | Integrações e regras que não pertencem ao controller |
| DTO/estado de status | `Support/*Status` | Fluxos e rótulos em um único lugar |

## Segurança

| Controle | Implementação |
|---|---|
| Senhas | `password_hash` com `PASSWORD_DEFAULT` e rehash automático |
| Sessão | `use_strict_mode`, `HttpOnly`, `SameSite=Lax`, regeneração no login |
| CSRF | Token por sessão, validado em todo `POST` |
| Autorização | `requireRole` no servidor; `Resource::roles()` por recurso |
| SQL injection | PDO com *prepared statements* e `EMULATE_PREPARES = false` |
| XSS | Escape na saída (`e()`) nas views |
| Cabeçalhos | `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` |
| Login | Rate limit por e-mail + IP (5 falhas / 15 min) |
| Upload | Limite de 25 MB e validação por tipo |
| Auditoria | `audit_logs` para ações sensíveis |
| IA | Propostas com aprovação humana e verificação de papel |

## Banco de dados

- 32 migrations em PHP, aplicadas por `bin/migrate.php` e registradas na tabela
  `migrations`.
- 39 tabelas da aplicação, com chaves estrangeiras e índices de desempenho
  (`0014_add_performance_indexes`).
- Não há ORM: cada consulta é SQL escrito à mão. As listagens paginam no
  servidor, não no navegador.

## Limitações conhecidas

1. Não há suíte de testes automatizados implementada, apesar de o PHPUnit estar
   declarado no `composer.json`. A validação é manual e exploratória.
2. O controle de estoque não baixa automaticamente na execução da OS.
3. Não há rotina de backup dentro do sistema; o backup é feito por
   `mysqldump`/volume do Docker.
4. A aplicação é mono-oficina: não há isolamento por empresa.
5. O assistente depende de chave externa da Groq; sem ela, o módulo fica inativo
   e o restante do sistema continua funcionando.
