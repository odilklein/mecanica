<div align="center">

# Torque

**Sistema de gestão para oficinas mecânicas — da recepção do veículo à entrega.**

Projeto Integrador · DSM · FATEC Araras · Team Zenitec

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![Documentação](https://img.shields.io/badge/docs-online-B8270F)](https://torque-zenitec.pages.dev)
[![License: MIT](https://img.shields.io/badge/license-MIT-2ea44f)](LICENSE)

[Sistema (demo)](https://torque-zenitec.page.gd) · [Documentação](https://torque-zenitec.pages.dev) · [Documento (PDF)](https://torque-zenitec.pages.dev/downloads/PI-ES2-2026.pdf) · [Diagramas](https://torque-zenitec.pages.dev/diagramas) · [Segurança](https://torque-zenitec.pages.dev/engenharia/07-seguranca-ofensiva)

</div>

---

> [!WARNING]
> **O sistema em produção é uma demonstração na InfinityFree (plano gratuito).**
> O endereço <https://torque-zenitec.page.gd> roda com dados fictícios e serve para
> mostrar o sistema funcionando sem instalar nada. O plano gratuito impõe limites que
> **não existem no Docker**: sem SSH, `exec` bloqueado (PDF do assistente não extrai
> texto), upload de 20 MB, execução máxima de 60 s, sem cron, FTP recusando arquivos
> acima de ~10 MB e um desafio JavaScript na primeira visita. Para o sistema completo,
> rode com Docker. Detalhes em [`docs/DEPLOY_INFINITYFREE.md`](docs/DEPLOY_INFINITYFREE.md).

## Sumário

- [Visão geral](#visão-geral)
- [Demonstração online](#demonstração-online)
- [Funcionalidades](#funcionalidades)
- [Fluxos principais](#fluxos-principais)
- [Perfis e permissões](#perfis-e-permissões)
- [Stack](#stack)
- [Como rodar](#como-rodar)
- [Estrutura](#estrutura)
- [Comandos](#comandos)
- [Segurança](#segurança)
- [Documentação](#documentação)
- [Equipe](#equipe)
- [Licença](#licença)

## Visão geral

O **Torque** é um sistema interno para a operação de uma oficina mecânica de pequeno
porte, desenvolvido para a **Auto Mecânica Leme** como Projeto Integrador do curso de
Desenvolvimento de Software Multiplataforma (DSM) da FATEC Araras.

A aplicação cobre o atendimento completo: clientes e veículos, ordens de serviço,
orçamentos, peças, fornecedores, pedidos de peças, tickets, notificações, relatórios,
configurações e auditoria — com um assistente de IA que propõe alterações e só as aplica
depois da aprovação de um usuário.

A arquitetura é modular:

- `public/` é o **front controller** e o document root do Apache;
- `src/Kernel/` concentra autenticação, sessão, roteamento, CRUD, banco, menu e views;
- `src/Modules/Core/` reúne login, dashboard, clientes, funcionários, configurações, auditoria e perfil;
- `src/Modules/Mechanic/` reúne veículos, OS, orçamentos, peças, fornecedores, pedidos, tickets e relatórios;
- `src/Modules/Agent/` reúne o assistente de IA, as ferramentas, as propostas e o streaming;
- `bin/` contém comandos de migrations, seeds, sincronização FIPE e importação de catálogo.

## Demonstração online

| | |
| --- | --- |
| Sistema | <https://torque-zenitec.page.gd> |
| Documentação | <https://torque-zenitec.pages.dev> |
| Acesso de demonstração | `juliana.prado@mecanica.com` / `Demo@Mecanica2026` (secretaria) |
| Outros perfis | `carlos.souza@mecanica.com` (mecânico-chefe) e `fernanda.lima@mecanica.com` (mecânica) usam a mesma senha |

Os dados são fictícios e recriáveis com `php bin/seed_operacao.php`. O acesso de
administrador não é publicado: é criado no deploy com
[`bin/create_admin.php`](bin/create_admin.php).

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

## Fluxos principais

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

Acesse **http://localhost:8080**. O phpMyAdmin não sobe no `up` padrão e escuta
apenas em localhost:

```bash
docker compose --profile dev up -d phpmyadmin   # http://localhost:8081
```

> Em uma base nova, as migrations criam o schema e **um** administrador. A senha vem de
> `SEED_ADMIN_PASSWORD`; sem ela, é gerada e impressa uma única vez no console da
> migration. O `docker-compose.yml` não importa dump SQL automaticamente; o schema é
> responsabilidade de `bin/migrate.php`.

O guia completo (variáveis, volumes, logs, reset do banco e problemas comuns) está em
[`docs/DOCKER.md`](docs/DOCKER.md).

### Instalação local (Apache/XAMPP)

- Aponte o document root para `public/`.
- Ative `mod_rewrite` e permita `AllowOverride All` (o `public/.htaccess` faz o roteamento).
- Instale as dependências com `composer install`.
- Configure o `.env` e rode `php bin/migrate.php migrate`.

### Hospedagem compartilhada (InfinityFree)

A demonstração pública roda em hospedagem compartilhada, sem SSH. O passo a passo,
o layout de arquivos, os limites do plano e a rotina de atualização estão em
[`docs/DEPLOY_INFINITYFREE.md`](docs/DEPLOY_INFINITYFREE.md).

## Acesso inicial

Não existe senha padrão. Crie (ou redefina) o administrador com:

```bash
docker compose exec app php bin/create_admin.php --email=admin@mecanica.com
# a senha é gerada e impressa; para definir uma específica:
docker compose exec app php bin/create_admin.php --email=admin@mecanica.com --password='SuaSenhaForte'
```

O comando exige senha de 12+ caracteres. Contas de demonstração criadas por
`bin/seed_demo.php` / `bin/seed_operacao.php` recebem uma senha aleatória impressa ao
final do seed (ou a definida em `SEED_PASSWORD`/`--password=`).
**Troque as senhas e remova os dados de exemplo antes de expor o sistema publicamente.**

## Estrutura

```text
public/                 Front controller, assets, CSS e JS servidos pelo Apache
src/Kernel/             Kernel, Auth, Session, Router, CRUD, Database, View, Menu
src/Modules/Core/       Login, dashboard, clientes, funcionários, configurações, auditoria, perfil
src/Modules/Mechanic/   Veículos, OS, orçamentos, peças, fornecedores, pedidos, tickets, relatórios
src/Modules/Agent/      Assistente de IA: ferramentas, propostas, sessões e streaming
bin/                    migrate.php, create_admin.php, fipe.php, seed_demo.php, seed_operacao.php, import_parts.php
docs/                   Documentação técnica, entrega, diagramas e site
storage/                Cache e logs em runtime (ignorado no Git)
vendor/                 Dependências Composer (ignorado no Git)
```

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

As migrations rodam por módulo respeitando as dependências do `module.json`
(`Core → Mechanic → Agent`), o que permite criar um banco vazio do zero.

## Segurança

- CSRF em formulários e ações POST (token fail-closed, gerado no início da sessão).
- Senhas com `password_hash`/`password_verify`; nenhuma senha padrão no código.
- Rate-limit de login por e-mail + IP, por IP e por conta (`login_tentativas`).
- Cloudflare Turnstile opcional (ativado apenas com site key e secret).
- Sessão com `HttpOnly`, `SameSite=Lax`, `use_strict_mode`, expiração por inatividade
  (`SESSION_IDLE_TIMEOUT`) e revalidação de `is_active`/papel.
- CSP e HSTS (HSTS só em HTTPS); `ServerTokens Prod`.
- Banco sem porta publicada; phpMyAdmin apenas em `127.0.0.1` e sob profile `dev`.
- Controle de acesso por papel e escopo de OS para mecânicos.
- Auditoria de ações sensíveis em `audit_logs`, sem credenciais/tokens.
- Cabeçalhos de segurança em `public/.htaccess`; `.env` e credenciais fora do versionamento.

O relatório da varredura com **HexStrike AI + Kali** e revisão de código está em
[`docs/seguranca/RELATORIO_PENTEST_HEXSTRIKE.md`](docs/seguranca/RELATORIO_PENTEST_HEXSTRIKE.md).
A versão da banca é o Anexo IV do documento oficial; a versão técnica está em
[`docs/engenharia/07-seguranca-ofensiva.md`](docs/engenharia/07-seguranca-ofensiva.md).

### Checklist de produção

1. `APP_DEBUG=false` no `.env`.
2. Trocar as senhas padrão e remover seeds/dados de demonstração.
3. Configurar HTTPS e, se possível, HSTS.
4. Configurar `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET` e `TURNSTILE_HOSTNAMES`.
5. Configurar backup automático do MySQL e monitoramento de logs.
6. Restringir o phpMyAdmin ou não expô-lo publicamente.
7. Rodar `php bin/migrate.php migrate` no deploy e manter o `vendor/` instalado.

## Documentação

A documentação completa fica em dois lugares: o site publicado e os arquivos deste
repositório. O site lê os mesmos Markdown de `docs/` e recria os 14 diagramas em
React Flow, com zoom e navegação.

### No site — <https://torque-zenitec.pages.dev>

| Seção | O que tem | Link |
| --- | --- | --- |
| **Visão geral** | Resumo do projeto, recursos, capturas e equipe | [/](https://torque-zenitec.pages.dev/) |
| **Documento** | Documento oficial do PI, seção a seção, com requisitos RF/RNF | [/documento](https://torque-zenitec.pages.dev/documento) |
| **Diagramas** | 14 diagramas interativos (casos de uso, classes, sequência, ER, arquitetura e fluxo da OS) | [/diagramas](https://torque-zenitec.pages.dev/diagramas) |
| **Engenharia** | Referência técnica: stack, requisitos, regras, modelo de dados, arquitetura, integrações e ADRs | [/engenharia](https://torque-zenitec.pages.dev/engenharia) |
| **Segurança** | Pentest com HexStrike AI, 18 achados, correções e reprodução | [/engenharia/07-seguranca-ofensiva](https://torque-zenitec.pages.dev/engenharia/07-seguranca-ofensiva) |

O PDF e o DOCX do documento oficial ficam em `docs/saida/` e podem ser baixados pelo
próprio site.

### Diagramas

| # | Diagrama | Fonte |
| --- | --- | --- |
| 1–4 | Casos de uso: administrador, secretaria, mecânico-chefe e mecânico | [`useCases.ts`](docs/site/src/diagrams/useCases.ts) |
| 5 | Diagrama de classes | [`classes.ts`](docs/site/src/diagrams/classes.ts) |
| 6 | Fluxo de status da OS | [`architecture.ts`](docs/site/src/diagrams/architecture.ts) |
| 7–9 | Sequência: abertura da OS, aprovação do orçamento, entrega do veículo | [`sequences.ts`](docs/site/src/diagrams/sequences.ts) |
| 10–13 | Modelo conceitual e modelos lógicos (cadastros, operação, orçamentos e pedidos) | [`er.ts`](docs/site/src/diagrams/er.ts) |
| 14 | Arquitetura da solução | [`architecture.ts`](docs/site/src/diagrams/architecture.ts) |

As fontes PlantUML/D2 usadas no documento oficial ficam em
[`docs/diagramas/fontes/`](docs/diagramas/fontes/); o site usa a versão interativa.

### No repositório

- [`docs/README.md`](docs/README.md) — mapa da documentação (engenharia, entrega, diagramas, build).
- [`docs/engenharia/`](docs/engenharia/) — fonte de verdade técnica: stack, requisitos, regras de negócio, dicionário de dados, arquitetura, integrações, segurança e [ADRs](docs/engenharia/adr/).
- [`docs/entrega/`](docs/entrega/) — texto acadêmico, uma seção por arquivo do template da FATEC, com os anexos.
- [`docs/saida/`](docs/saida/) — documento oficial em DOCX e PDF.
- [`docs/seguranca/`](docs/seguranca/) — relatório de pentest, artefatos brutos e scripts de reprodução.
- [`docs/ASSISTENTE.md`](docs/ASSISTENTE.md) — assistente de IA: arquitetura, segurança, ferramentas e widgets.
- [`docs/RELATORIO_AUDITORIA_SISTEMA.md`](docs/RELATORIO_AUDITORIA_SISTEMA.md) — auditoria do sistema, correções e validações.
- [`docs/RELATORIO_CANAL_DA_PECA.md`](docs/RELATORIO_CANAL_DA_PECA.md) — integração com o catálogo Canal da Peça.
- [`docs/DOCKER.md`](docs/DOCKER.md) — guia de execução com Docker.
- [`docs/DEPLOY_INFINITYFREE.md`](docs/DEPLOY_INFINITYFREE.md) — deploy na hospedagem compartilhada e limites do plano gratuito.
- [`docs/PLANO_FINALIZACAO_APRESENTACAO.md`](docs/PLANO_FINALIZACAO_APRESENTACAO.md) — plano de finalização e checklist da apresentação.
- [`docs/site/`](docs/site/) — fonte do site (React + Tailwind + React Flow).

Para gerar o documento oficial e o site:

```bash
bash docs/build/render.sh diagramas   # PlantUML/D2 via Docker
bash docs/build/render.sh documento   # DOCX + PDF em docs/saida/
bash docs/build/site.sh dev           # site em http://localhost:5173
```

## Equipe

| Integrante | Papel | Frentes |
| --- | --- | --- |
| Odil Klein da Silva Neto | Desenvolvimento & Arquitetura | Núcleo do sistema, módulos de negócio, banco, integrações e redação técnica |
| Nícolas da Cunha de Godoy | Documentação & Requisitos | Levantamento e revisão de requisitos, conferência do texto e organização da entrega |
| Andreo Rafael | Documentação & Modelagem | Revisão do modelo de dados, conferência das tabelas e apoio nos diagramas |
| Alex Rodrigo Candido | Documentação & Interfaces | Revisão das telas, conferência das referências e apoio na apresentação |

Orientação: FATEC Araras · Curso de Desenvolvimento de Software Multiplataforma.

## Licença

MIT — consulte [LICENSE](LICENSE).

Os dados exibidos na demonstração e nas capturas são fictícios: nomes, CPF, placas e
valores não correspondem a pessoas ou veículos reais.
