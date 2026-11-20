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
- **Preenchimento com IA na nova OS**: descreva ou fale o atendimento e o wizard é preenchido para revisão — CEP continua vindo do ViaCEP e marca/modelo/ano da FIPE.
- **Queixa assistida e texto para o cliente**: ditado que vira uma queixa clara e mensagem de WhatsApp gerada a partir do orçamento, com cópia.
- **Assistente de IA** em `/assistant`: chat com streaming, consultas por linguagem natural, widgets do sistema, histórico de conversas com exclusão e propostas de alteração que só são aplicadas após aprovação humana (ver [`docs/ASSISTENTE.md`](docs/ASSISTENTE.md)).
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
bin/                    migrate.php, create_admin.php, fipe.php, seed_demo.php, seed_operacao.php, import_parts.php
docs/                   Relatórios e material de apresentação do TCC
storage/                Cache e logs em runtime (ignorado no Git)
vendor/                 Dependências Composer (ignorado no Git)
```

## Como rodar

### Docker / Podman (recomendado)

```bash
cp .env.example .env
# DB_PASS é obrigatória: escolha uma senha forte antes de subir
# (ex.: openssl rand -hex 24). O compose não sobe sem ela.

docker compose up -d --build

# cria/atualiza o schema (migrations)
docker compose exec app php bin/migrate.php migrate

# cria/redefine o administrador (gera senha se omitir --password)
docker compose exec app php bin/create_admin.php --email=admin@mecanica.com

# opcional: dados de demonstração (senha gerada e impressa, ou --password=...)
docker compose exec app php bin/seed_demo.php
docker compose exec app php bin/seed_operacao.php

# opcional: sincroniza marcas/modelos/anos/preços da FIPE
docker compose exec app php bin/fipe.php sync
```

Acesse **http://localhost:8080**.
O phpMyAdmin não sobe no `up` padrão e escuta apenas em localhost:

```bash
docker compose --profile dev up -d phpmyadmin   # http://localhost:8081
```

> Em uma base nova, as migrations criam o schema e **um** administrador. A senha vem de `SEED_ADMIN_PASSWORD`; sem ela, é gerada e impressa uma única vez no console da migration. O `docker-compose.yml` não importa dump SQL automaticamente; o schema é responsabilidade de `bin/migrate.php`.

### Instalação local (Apache/XAMPP)

- Aponte o document root para `public/`.
- Ative `mod_rewrite` e permita `AllowOverride All` (o `public/.htaccess` faz o roteamento).
- Instale as dependências com `composer install`.
- Configure o `.env` e rode `php bin/migrate.php migrate`.

## Acesso inicial

Não existe mais senha padrão. Crie (ou redefina) o administrador com:

```bash
docker compose exec app php bin/create_admin.php --email=admin@mecanica.com
# a senha é gerada e impressa; para definir uma específica:
docker compose exec app php bin/create_admin.php --email=admin@mecanica.com --password='SuaSenhaForte'
```

O comando exige senha de 12+ caracteres. Contas de demonstração criadas por
`bin/seed_demo.php` / `bin/seed_operacao.php` recebem uma senha aleatória
impressa ao final do seed (ou a definida em `SEED_PASSWORD`/`--password=`).
**Troque as senhas e remova os dados de exemplo antes de expor o sistema
publicamente.**

## Comandos

| Comando | Descrição |
| --- | --- |
| `php bin/migrate.php migrate` | Aplica as migrations pendentes. |
| `php bin/migrate.php status` | Mostra o status das migrations. |
| `php bin/create_admin.php` | Cria/redefine usuário e senha (12+ caracteres). |
| `php bin/fipe.php sync` | Sincroniza marcas/modelos/anos/preços da FIPE. |
| `php bin/seed_demo.php` | Cria dados básicos de demonstração (`--password=` opcional). |
| `php bin/seed_operacao.php` | Simula meses de operação da oficina (`--password=` opcional). |
| `php bin/import_parts.php all` | Importa veículos/produtos do catálogo Canal da Peça. |

## Segurança

- CSRF em formulários e ações POST (token fail-closed, gerado no início da sessão).
- Senhas com `password_hash`/`password_verify`; nenhuma senha padrão no código.
- Rate-limit de login por e-mail + IP, por IP e por conta (`login_tentativas`).
- Cloudflare Turnstile opcional (ativado apenas com site key e secret).
- Sessão com `HttpOnly`, `SameSite=Lax`, `use_strict_mode`, expiração por
  inatividade (`SESSION_IDLE_TIMEOUT`) e revalidação de `is_active`/papel.
- CSP e HSTS (HSTS só em HTTPS); `ServerTokens Prod`.
- Banco sem porta publicada; phpMyAdmin apenas em `127.0.0.1` e sob profile `dev`.
- Controle de acesso por papel e escopo de OS para mecânicos.
- Auditoria de ações sensíveis em `audit_logs`, sem credenciais/tokens.

O relatório da varredura de segurança (HexStrike + revisão de código) está em
[`docs/seguranca/RELATORIO_PENTEST_HEXSTRIKE.md`](docs/seguranca/RELATORIO_PENTEST_HEXSTRIKE.md).
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

- **Documentação online:** [`docs/site/`](docs/site/) — site em React + Tailwind + React Flow, com os 14 diagramas interativos. Rode `bash docs/build/site.sh dev` e abra `http://localhost:5173`.
- [`docs/README.md`](docs/README.md) — mapa da documentação (engenharia, entrega, diagramas, build).
- [`docs/engenharia/`](docs/engenharia/) — fonte de verdade técnica: stack, requisitos, regras, modelo de dados, arquitetura, integrações e ADRs.
- [`docs/entrega/`](docs/entrega/) — texto acadêmico, uma seção por arquivo do template da FATEC.
- [`docs/saida/`](docs/saida/) — documento oficial em DOCX e PDF.
- [`docs/ASSISTENTE.md`](docs/ASSISTENTE.md) — assistente de IA: arquitetura, segurança, ferramentas e widgets.
- [`docs/RELATORIO_AUDITORIA_SISTEMA.md`](docs/RELATORIO_AUDITORIA_SISTEMA.md) — auditoria, correções, regras e validações.
- [`docs/RELATORIO_CANAL_DA_PECA.md`](docs/RELATORIO_CANAL_DA_PECA.md) — integração com o catálogo Canal da Peça.
- [`docs/PLANO_FINALIZACAO_APRESENTACAO.md`](docs/PLANO_FINALIZACAO_APRESENTACAO.md) — plano de finalização e checklist da apresentação.
- [`docs/apresentacao/`](docs/apresentacao/) — material **histórico** da trigger/view (não usado na apresentação atual).

## Licença

MIT — consulte [`LICENSE`](LICENSE).
