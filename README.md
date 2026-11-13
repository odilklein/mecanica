<div align="center">

# Auto Mecânica

**Sistema modular de gestão para oficinas mecânicas — da recepção do veículo à entrega.**

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-2ea44f)](LICENSE)

[Visão geral](#visão-geral) · [Funcionalidades](#funcionalidades) · [Como rodar](#como-rodar) · [Estrutura](#estrutura) · [Segurança](#segurança) · [Documentação](#documentação)

</div>

---

## Visão geral

O **Auto Mecânica** é um sistema interno para operação de uma oficina mecânica, desenvolvido como **Projeto Integrador (TCC)**. A aplicação cobre o atendimento completo: clientes e veículos, ordens de serviço, orçamentos, peças, fornecedores, pedidos de peças, tickets, notificações, relatórios, configurações e auditoria.

A arquitetura é modular:

- `public/` é o **front controller** e o document root do Apache;
- `src/Kernel/` concentra autenticação, sessão, roteamento, CRUD, banco, menu e views;
- `src/Modules/Core/` reúne login, dashboard, clientes, funcionários, configurações, auditoria e perfil;
- `src/Modules/Mechanic/` reúne veículos, OS, orçamentos, peças, fornecedores, pedidos, tickets e relatórios;
- `bin/` contém comandos de migrations, seeds, sincronização FIPE e importação de catálogo.

> O app antigo (PHP procedural na raiz) foi removido. O material do TCC sobre a trigger/view do banco legado está preservado em [`docs/apresentacao/`](docs/apresentacao/) e não é usado pela aplicação em execução.

## Funcionalidades

### Operação
- **Dashboard por perfil** com KPIs, faturamento, ticket médio, status semanal e fila de OS.
- **Clientes e veículos** com CPF, endereço/CEP (ViaCEP), FIPE, KM, chassi, observações e histórico de atendimentos.
- **Ordens de serviço** com wizard de abertura, diagnóstico, tarefas, equipe, prioridade, histórico e entrega/retirada.
- **Orçamentos** com itens de peça/mão de obra, busca no catálogo, desconto, impressão e histórico.
- **Peças e fornecedores** com catálogo, categorias, preço/custo, estoque, filtros e paginação.
- **Pedidos de peças** vinculados à OS, com quick view, quantidade, paginação e fluxo de status.
- **Tickets** internos por OS, com conversa, anexos por áudio (Groq opcional) e notificações.
- **Preenchimento com IA** nos formulários (cliente, veículo e nova OS): descreva ou fale e o formulário é preenchido para revisão — CEP continua vindo do ViaCEP e marca/modelo/ano da FIPE.
- **Assistente de IA** em `/assistant`: chat com streaming, consultas por linguagem natural, widgets do sistema e propostas de alteração que só são aplicadas após aprovação humana (ver [`docs/ASSISTENTE.md`](docs/ASSISTENTE.md)).
- **Relatórios** por período, com tabela de orçamentos, ticket médio e exportação CSV.
- **Configurações** de oficina, tema, SEO, validade e termos de orçamento.
- **Auditoria** com filtros, detalhes legíveis e links para os registros.
- **Funcionários** com perfis, salário, admissão e controle de acesso.
- **Perfil** do usuário com dados de contato e troca de senha.

### Fluxos principais

**Ordem de serviço**

```text
Recebida → Em análise → Aguardando aprovação / Aguardando peça
        → Em execução → Pronta para entrega → Entregue
```

- `Entregue` só é concluído pelo registro de **entrega/retirada**.
- A entrega exige a OS em **Pronta para entrega**, não permite duplicidade e atualiza o KM do veículo.
- A edição não permite pular etapas; status finais ficam somente leitura.
- O mecânico só acessa as OS em que participa.

**Orçamento**

```text
Aberto → Aprovado / Reprovado / Cancelado
Aprovado / Reprovado / Cancelado → Aberto (reabrir para editar)
```

- A edição é permitida apenas com status **Aberto**.
- O desconto é limitado ao subtotal de peças + mão de obra.

**Pedido de peças**

```text
Solicitado → Validado → Pedido → A caminho → Recebido
```

- O mecânico apenas **solicita** a peça (OS + itens); fornecedor, previsão e andamento são da secretaria.
- A página de detalhe mostra itens, resumo, recebimento e histórico.

## Perfis e permissões

| Papel | Acesso |
| --- | --- |
| **Administrador** | Todo o sistema, incluindo configurações, funcionários e auditoria. |
| **Secretaria** | Dashboard, tickets, clientes, veículos, OS, orçamentos, peças, fornecedores, pedidos e relatórios. |
| **Mecânico-chefe** | Mesmo escopo operacional da secretaria; sem configurações, funcionários e auditoria. |
| **Mecânico** | Dashboard, tickets, OS em que participa e solicitação de peças. |

## Stack

- PHP 8.2 (Apache + PDO MySQL)
- MySQL 8.4
- Bootstrap 5 + tema base + ícones Tabler
- jQuery, Select2, DataTables, Chart.js e ApexCharts
- Composer: `bramus/router`, `league/container`, `league/plates`, `nyholm/psr7`, `vlucas/phpdotenv`
- Docker/Podman com `docker-compose.yml`

## Estrutura

```text
public/                 Front controller, assets, CSS e JS servidos pelo Apache
src/Kernel/             Kernel, Auth, Session, Router, CRUD, Database, View, Menu
src/Modules/Core/       Login, dashboard, clientes, funcionários, configurações, auditoria, perfil
src/Modules/Mechanic/   Veículos, OS, orçamentos, peças, fornecedores, pedidos, tickets, relatórios
src/Modules/Agent/      Assistente de IA: ferramentas, propostas, sessões e streaming
bin/                    migrate.php, fipe.php, seed_demo.php, seed_operacao.php, import_parts.php
docs/                   Relatórios e material de apresentação do TCC
storage/                Cache e logs em runtime (ignorado no Git)
vendor/                 Dependências Composer (ignorado no Git)
```

## Como rodar

### Docker / Podman (recomendado)

```bash
cp .env.example .env
# ajuste DB_HOST, DB_PORT, DB_USER, DB_PASS e DB_NAME se necessário

docker compose up -d --build

# cria/atualiza o schema (32 migrations)
docker compose exec app php bin/migrate.php migrate

# opcional: dados de demonstração
docker compose exec app php bin/seed_demo.php
docker compose exec app php bin/seed_operacao.php

# opcional: sincroniza marcas/modelos/anos/preços da FIPE
docker compose exec app php bin/fipe.php sync
```

Acesse **http://localhost:8080**. O phpMyAdmin fica em **http://localhost:8081**.

> Em uma base nova, as migrations criam o schema e os três usuários padrão. O `docker-compose.yml` não importa mais um dump SQL automaticamente; o schema é responsabilidade de `bin/migrate.php`.

### Instalação local (Apache/XAMPP)

- Aponte o document root para `public/`.
- Ative `mod_rewrite` e permita `AllowOverride All` (o `public/.htaccess` faz o roteamento).
- Instale as dependências com `composer install`.
- Configure o `.env` e rode `php bin/migrate.php migrate`.

## Logins padrão

Criados pela migration `Core/0002_seed_default_users` (senha `admin123`):

| Nome | E-mail | Perfil |
| --- | --- | --- |
| Administrador | `admin@mecanica.com` | Administrador |
| Secretária | `secretaria@mecanica.com` | Secretaria |
| Mecânico | `mecanico@mecanica.com` | Mecânico |

Os scripts de seed adicionam funcionários, clientes, veículos, OS e orçamentos de demonstração. **Troque as senhas e remova os dados de exemplo antes de expor o sistema publicamente.**

## Comandos

| Comando | Descrição |
| --- | --- |
| `php bin/migrate.php migrate` | Aplica as migrations pendentes. |
| `php bin/migrate.php status` | Mostra o status das migrations. |
| `php bin/fipe.php sync` | Sincroniza marcas/modelos/anos/preços da FIPE. |
| `php bin/seed_demo.php` | Cria dados básicos de demonstração. |
| `php bin/seed_operacao.php` | Simula meses de operação da oficina. |
| `php bin/import_parts.php all` | Importa veículos/produtos do catálogo Canal da Peça. |

## Segurança

- CSRF em formulários e ações POST.
- Senhas com `password_hash`/`password_verify`; mínimo de 8 caracteres em novos usuários.
- Rate-limit de login por e-mail + IP (`login_tentativas`).
- Cloudflare Turnstile opcional (ativado apenas com site key e secret).
- Sessão com `HttpOnly`, `SameSite=Lax` e `use_strict_mode`.
- Controle de acesso por papel e escopo de OS para mecânicos.
- Auditoria de ações sensíveis em `audit_logs`, sem credenciais/tokens.
- Cabeçalhos de segurança em `public/.htaccess`.
- `.env` e credenciais fora do versionamento.

## Checklist de produção

1. `APP_DEBUG=false` no `.env`.
2. Trocar as senhas padrão e remover seeds/dados de demonstração.
3. Configurar HTTPS e, se possível, HSTS.
4. Configurar `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET` e `TURNSTILE_HOSTNAMES`.
5. Configurar backup automático do MySQL e monitoramento de logs.
6. Restringir o phpMyAdmin ou não expô-lo publicamente.
7. Rodar `php bin/migrate.php migrate` no deploy e manter o `vendor/` instalado.

## Documentação

- [`docs/ASSISTENTE.md`](docs/ASSISTENTE.md) — assistente de IA: arquitetura, segurança, ferramentas e widgets.
- [`docs/RELATORIO_AUDITORIA_SISTEMA.md`](docs/RELATORIO_AUDITORIA_SISTEMA.md) — auditoria, correções, regras e validações.
- [`docs/RELATORIO_CANAL_DA_PECA.md`](docs/RELATORIO_CANAL_DA_PECA.md) — integração com o catálogo Canal da Peça.
- [`docs/apresentacao/ROTEIRO_APRESENTACAO.md`](docs/apresentacao/ROTEIRO_APRESENTACAO.md) — roteiro da trigger/view do TCC.
- [`docs/apresentacao/banco_legado_tcc.sql`](docs/apresentacao/banco_legado_tcc.sql) — schema legado de referência do TCC.

## Licença

MIT — consulte [`LICENSE`](LICENSE).
