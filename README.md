# Auto Mecânica — Sistema de Gestão de Oficina

Projeto Integrador (PI) de um sistema web para gestão de oficina mecânica.
Stack simples e funcional: **PHP (puro) + MySQL + JavaScript + HTML/CSS + Bootstrap 5**.

## Funcionalidades

- **Autenticação** com perfis: administrador, secretaria, mecânico-chefe e mecânico.
- **Clientes, veículos e funcionários** (CRUD).
- **Ordens de serviço** com wizard, diagnóstico, tarefas (com áudio), responsáveis,
  histórico de eventos e controle de status.
- **Pedidos de peças** com recebimento parcial e vínculo à OS.
- **Orçamentos** (editor, impressão e relatórios).
- **Estoque** de peças com alerta de mínimo e movimentações.
- **Notificações** internas em tempo real.
- **Auditoria** de ações sensíveis e **backup** do banco (admin).

## Requisitos

- PHP 8.1+ (extensões `pdo_mysql` e `mbstring`)
- MySQL 5.7+ / MariaDB 10.4+
- Apache com `.htaccess` habilitado (XAMPP serve)

## Instalação (XAMPP)

1. Copie o projeto para `htdocs/mecanica`.
2. Crie um banco vazio e importe `sql/mecanica_teste.sql` pelo phpMyAdmin.
3. Configure as credenciais do banco (escolha uma das opções abaixo).
4. Acesse `http://localhost/mecanica/` no navegador.

### Credenciais do banco: duas opções

A precedência é: **`config/config.local.php` → `.env` → padrões**.

- **Opção A (`.env`, recomendada):** copie `.env.example` para `.env` e preencha.
- **Opção B (`config/config.local.php`):** copie `config/config.local.example.php`
  para `config/config.local.php` e preencha.

Ambos os arquivos estão no `.gitignore` — **nunca** suba credenciais para o Git.
Sem nenhum deles, o sistema usa os padrões (`localhost`, `root`, sem senha,
banco `mecanica`).

> Em hospedagem compartilhada (ex.: InfinityFree) o `putenv()` costuma ser
> bloqueado; o sistema já lê o `.env` sem depender dele. Se preferir simplicidade,
> use a Opção B: é PHP puro, editável direto no servidor.

## Credenciais de teste

Todos os usuários usam a senha **admin123**:

| Perfil          | E-mail                    |
|-----------------|---------------------------|
| Administrador   | admin@mecanica.com        |
| Secretaria      | secretaria@mecanica.com   |
| Mecânico-chefe  | chefe@mecanica.com        |
| Mecânico        | mecanico1@mecanica.com    |
| Mecânico        | mecanico2@mecanica.com    |

## Estrutura

```
config/      conexão e configuração da empresa
includes/    sessão, header, sidebar, footer, helpers
pages/       módulos do sistema (um diretório por área)
api/         endpoints JSON (ex.: veículos por cliente)
css/ js/     estilos e scripts
sql/         script de criação do banco com dados de demonstração
uploads/     áudios das tarefas e backups gerados
```

## Segurança

- Senhas com `password_hash` (bcrypt).
- Prepared statements (PDO) em todas as consultas.
- Token CSRF nos formulários e endpoints de escrita.
- Controle de acesso por perfil (`exigir_papel`).
- `.htaccess` bloqueando `.env`, `.sql`, logs e acesso direto a `config/` e `sql/`.
- **Cloudflare Turnstile** no login (ver abaixo).

## Cloudflare Turnstile (anti-bot no login)

O widget já está embutido no formulário de login e o backend valida o token
via `siteverify` antes de conferir as credenciais.

1. No painel da Cloudflare → **Turnstile** → seu widget, confira os domínios
   (inclua `localhost` e `127.0.0.1` para desenvolvimento e o domínio de produção).
2. Copie a **Secret Key** do widget.
3. Preencha no `.env` (nunca commite este arquivo — ele já está no `.gitignore`):

   ```
   TURNSTILE_SITE_KEY=<sua-site-key>
   TURNSTILE_SECRET=<sua-secret-key>
   TURNSTILE_HOSTNAMES=localhost,127.0.0.1
   ```

   Em produção, use `TURNSTILE_HOSTNAMES` apenas com o domínio real
   (remova `localhost`/`127.0.0.1`).

Enquanto a configuração estiver incompleta (falta site key ou secret), a verificação
fica **desativada** e o login continua funcionando. Ela é aplicada quando os dois
valores existem **e** o domínio de acesso está no widget e em `TURNSTILE_HOSTNAMES`.

Arquivos: `config/turnstile.php` (site key pública), `includes/turnstile.php`
(siteverify server-side) e `pages/auth/login.php` / `login_processar.php`
(widget + gate).

## Deploy em produção (checklist)

1. Suba os arquivos do projeto (inclua os `.htaccess`, `config/` e `uploads/`).
2. No servidor, crie o `.env` (ou `config/config.local.php`) com as credenciais
   **do host** e os valores do Turnstile. Não suba o `.env` do ambiente local.
3. No painel da Cloudflare → Turnstile → widget, aceite o **domínio de produção**
   (mantenha `localhost`/`127.0.0.1` só se for testar local).
4. No servidor, `TURNSTILE_HOSTNAMES` = **apenas o domínio de produção**.
5. Confirme que `https://SEU-DOMINIO/.env` retorna 403/404 (nunca o conteúdo).
6. Ajuste e importe `sql/mecanica_teste.sql` em um banco vazio; troque as senhas
   de teste.
7. Remova o `diagnostico.php` após usar.

Se o login acusar *"Confirme a verificação..."*, o desafio não foi concluído.
Se acusar *"Falha na verificação..."*, confira o `error.log` (motivo exato) ou
rode o `diagnostico.php`: os casos mais comuns são domínio ausente no widget ou
em `TURNSTILE_HOSTNAMES`, e secret desatualizado.

