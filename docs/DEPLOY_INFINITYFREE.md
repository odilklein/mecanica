# Deploy na InfinityFree

Ambiente de demonstração do sistema (acesso do professor) na hospedagem gratuita
InfinityFree.

> [!WARNING]
> **Isto é uma demonstração, não um ambiente de produção.**
> O plano gratuito não tem SSH, bloqueia `exec` (o PDF do assistente não extrai
> texto), limita o upload a 20 MB e a execução a 60 s, não oferece cron, recusa
> arquivos de FTP acima de ~10 MB e mostra um desafio JavaScript na primeira
> visita. Para o sistema completo e sem esses limites, use o
> [guia Docker](DOCKER.md).
>
> Acesso público: <https://torque-zenitec.page.gd> · `juliana.prado@mecanica.com` /
> `Demo@Mecanica2026` (secretaria). O administrador não é publicado.

| Item | Valor |
| --- | --- |
| URL | https://torque-zenitec.page.gd |
| Conta | `if0_XXXXXXXX` (painel da InfinityFree) |
| Docroot | `<dominio>/htdocs` |
| Banco | `<conta>_torquedb` em `sqlXXX.infinityfree.com` |
| PHP | 8.4 (Apache com `mod_php`) |

Credenciais de FTP, MySQL e do administrador ficam **apenas no `.env` do
servidor** (fora do versionamento). O arquivo `.env` de produção é uma cópia do
`.env.example` com `DB_*` apontando para o MySQL da InfinityFree e
`SEED_ADMIN_PASSWORD` definida.

## Estrutura no servidor

A InfinityFree fixa o document root em `htdocs` e limita o PHP com
`open_basedir` a essa pasta, então não é possível deixar `src/`/`vendor/` fora
do webroot. O layout ficou assim:

```
htdocs/
├── .env                 # configuração de produção (bloqueada por .htaccess)
├── .htaccess            # bloqueios + HTTPS + rewrite para o front controller
├── index.php            # ancora SCRIPT_NAME e chama public/index.php
├── public/              # front controller e assets (css, js, assets/)
├── src/                 # código da aplicação (bloqueado)
├── vendor/              # dependências do Composer (bloqueado)
├── bin/                 # scripts CLI (bloqueado)
└── storage/             # cache, logs e anexos do assistente (bloqueado)
```

O `.htaccess` da raiz (versionado em
[`deploy/infinityfree/htdocs.htaccess`](../deploy/infinityfree/htdocs.htaccess)):

- aplica `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, CSP e HSTS a **todas** as respostas — inclusive
  `/login`, que passa pelo `index.php` da raiz;
- redireciona HTTP para HTTPS;
- responde 403 para `src/`, `vendor/`, `bin/`, `storage/`, `composer.*`,
  arquivos iniciados por ponto e `README.md`;
- encaminha `/assets`, `/css` e `/js` para `public/`;
- encaminha todo o resto para `index.php`.

> [!IMPORTANT]
> O `public/.htaccess` só vale para arquivos servidos de `/public`. Cabeçalhos
> de segurança de páginas dinâmicas precisam estar no `.htaccess` da raiz;
> caso contrário o navegador recebe CSP/HSTS apenas nos assets e ignora ambos
> no documento.

`index.php` existe porque o roteador (`bramus/router`) calcula a rota a partir
de `SCRIPT_NAME`. Como o front controller real mora em `public/`, o arquivo da
raiz fixa `SCRIPT_NAME = /index.php` antes de incluí-lo.

## Primeira instalação (resumo)

1. Empacotar `src/`, `vendor/`, `bin/`, `public/` e a estrutura de `storage/`.
2. Enviar por FTP para `torque-zenitec.page.gd/htdocs`.
3. Criar o `.env` de produção.
4. Rodar as migrations (não há SSH; ver abaixo).
5. Enviar os arquivos de deploy (`index.php` da raiz e `.htaccess`).

O FTP da InfinityFree recusa arquivos acima de ~10 MB. Para pacotes grandes,
divida o `.tar.gz` em partes de 8 MB (`split -b 8000000`) e junte no servidor
com PHP antes de extrair.

## Rodar migrations sem SSH

Não há terminal no plano gratuito. O caminho é um script temporário acessível
por HTTP, protegido por token e removido no fim. Esqueleto:

```php
<?php
// public/_deploy.php — apagar depois do uso.
if (($_GET['t'] ?? '') !== 'TOKEN_ALEATORIO') { http_response_code(404); exit; }

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($base)->safeLoad();

$pdo = Kernel\Database\Connection::fromEnv();
$migrator = new Kernel\Database\Migrator($pdo, $base);
$ran = $migrator->migrate();

echo 'Migrations: ' . count($ran);
@unlink(__FILE__);
```

Para os seeds (`bin/seed_operacao.php`, `bin/seed_demo.php`) o mesmo padrão
funciona: `require` do script com `$argv` montado antes.

## Atualizar o site (rotina)

O código novo entra por FTP, sobrescrevendo os arquivos alterados:

- `src/**` → `htdocs/src/**`
- `public/**` → `htdocs/public/**` (assets, css, js e `index.php`)
- `vendor/**` só quando o `composer.json`/`composer.lock` mudar
- migrations novas → depois do upload, rodar o script temporário acima

Para um lote de arquivos, o caminho mais rápido é gerar um `.tar.gz` pequeno e
extrair no servidor com o script temporário (o mesmo padrão do primeiro deploy):
`tar czf update.tar.gz src public && split -b 8000000` se passar de 10 MB.

Arquivos que existem só no servidor e nunca vêm do repositório: `.env`,
`.htaccess` da raiz e `index.php` da raiz.

O `asset()` adiciona `?v=` pelo `mtime`, então CSS/JS atualizados não ficam
presos em cache.

### Seed de demonstração

Para recriar os dados fictícios no servidor, rode `bin/seed_operacao.php` pelo
mesmo script temporário, com `$argv = ['seed', '--password=...']` antes do
`require`. O seed padrão gera 3 meses de operação (≈150 OS) e leva poucos
segundos; `--clean` remove tudo o que foi criado.

## Turnstile no login

O login usa Cloudflare Turnstile. As chaves ficam no `.env` do servidor
(`TURNSTILE_KEY` ou `TURNSTILE_SITE_KEY`, mais `TURNSTILE_SECRET`) e
`TURNSTILE_HOSTNAMES` precisa listar o domínio do site. Sem site key e secret, a
verificação é ignorada e o login volta ao normal.

- O widget é renderizado dentro do formulário (`data-action="login"`); o token
  vai como `cf-turnstile-response` e é validado no servidor.
- Navegadores automatizados/headless são barrados pela Cloudflare (erro `600*`),
  então testes com Playwright não conseguem autenticar. Em navegador comum o
  desafio passa sozinho.
- Para desativar rapidamente (sem internet na banca, por exemplo), deixe as duas
  chaves vazias no `.env`.

## Limitações do plano gratuito

- `exec`, `shell_exec`, `proc_open`, `symlink` e `chmod` estão desabilitados.
  Consequência prática: **PDF do assistente não extrai texto** (depende de
  `pdftotext`); imagens, texto/CSV e áudio continuam funcionando.
- `upload_max_filesize` = 20 MB e `post_max_size` = 30 MB.
- `max_execution_time` = 60 s: seeds longos podem precisar ser fatiados.
- Sem cron: FIPE e catálogo de peças só sincronizam por ação manual.
- Sessões ficam em `/php_sessions` (compartilhado do host), não em `storage/`.
- A primeira visita passa por um desafio JavaScript (`aes.js`) da InfinityFree;
  navegadores resolvem sozinhos, `curl`/bots recebem a página do desafio.
- O WAF do host bloqueia algumas palavras no caminho da URL (HTTP 403), mesmo em
  arquivos estáticos. `app-chat.css` do template era bloqueado; o arquivo foi
  renomeado para `app-conversa.css`. Evite nomes como `chat` em rotas e assets.
- Conexões de saída funcionam (Groq, ViaCEP, FIPE, Canal da Peça), mas o IP é
  compartilhado — se algum provedor bloquear, a integração falha.

## Checklist deste deploy

- [x] HTTPS com HSTS e redirect de HTTP.
- [x] Migrations aplicadas (33) e admin criado.
- [x] `src/`, `vendor/`, `bin/`, `storage/`, `.env` e `composer.*` bloqueados.
- [x] `storage/cache`, `storage/logs` e `storage/agent` graváveis.
- [x] Turnstile ativo no login (chaves no `.env` do servidor).
- [ ] Seed de demonstração (opcional).
