# Guia de execução com Docker

O `docker-compose.yml` sobe dois serviços: o Apache + PHP 8.2 com o sistema
(`app`, porta 8080) e o MySQL 8.4 (`db`, sem porta publicada). O phpMyAdmin é
opcional e fica fora do `up` padrão.

## Pré-requisitos

- Docker Engine 24+ ou Podman 4+ com `docker compose`/`podman compose`
- ~600 MB de espaço para as imagens
- Porta 8080 livre (e 8081, se usar o phpMyAdmin)

## 1. Configuração

```bash
cp .env.example .env
```

O mínimo para subir:

```dotenv
DB_PASS=uma-senha-forte-aqui      # obrigatória
APP_KEY=hex-de-64-caracteres      # php -r "echo bin2hex(random_bytes(32));"
```

O restante é opcional e está comentado no `.env.example`:

| Variável | Efeito |
| --- | --- |
| `APP_DEBUG` | `true` mostra erros na resposta (só em desenvolvimento). |
| `SESSION_IDLE_TIMEOUT` | Encerra a sessão após N minutos de inatividade (0 desativa). |
| `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` | Admin criado pela migration inicial. |
| `GROQ_API_KEY` | Habilita transcrição de áudio e o assistente de IA. |
| `LLM_*` | Modelo, limites de tokens e temperatura do assistente. |
| `TURNSTILE_*` | Anti-bot no login (desativado se as chaves estiverem vazias). |

> O `docker-compose.yml` usa `${DB_PASS:?...}`: sem `DB_PASS` o compose **não sobe**.

## 2. Subir

```bash
docker compose up -d --build
docker compose ps
```

O `app` escuta em <http://localhost:8080>. O `db` fica sem porta publicada — só a
rede interna do compose alcança o MySQL.

## 3. Criar o schema e o administrador

```bash
docker compose exec app php bin/migrate.php migrate
docker compose exec app php bin/create_admin.php --email=admin@mecanica.com
```

O `create_admin.php` gera uma senha aleatória e imprime no terminal; para definir
uma senha específica, use `--password='...'` (12+ caracteres). Também é possível
definir `SEED_ADMIN_PASSWORD` antes do primeiro `migrate`.

## 4. Dados de demonstração (opcional)

```bash
# cenário básico (clientes, veículos, OS, orçamentos)
docker compose exec app php bin/seed_demo.php

# simula meses de operação — 3 meses e ~150 OS por padrão
docker compose exec app php bin/seed_operacao.php
docker compose exec app php bin/seed_operacao.php --months=6 --os=220
docker compose exec app php bin/seed_operacao.php --fresh   # recria do zero
docker compose exec app php bin/seed_operacao.php --clean   # só remove
```

A senha das contas criadas pelo seed vem de `--password=` ou `SEED_PASSWORD`; sem
isso, é gerada e impressa ao final.

Para redefinir qualquer conta depois:

```bash
docker compose exec app php bin/create_admin.php --email=secretaria@mecanica.com --role=secretary
```

## 5. Integrações (opcional)

```bash
docker compose exec app php bin/fipe.php sync          # marcas/modelos/anos/preços
docker compose exec app php bin/import_parts.php all   # catálogo Canal da Peça
```

As duas dependem de rede e podem demorar. O assistente de IA só responde com
`GROQ_API_KEY` válida no `.env`.

## 6. phpMyAdmin (opcional)

```bash
docker compose --profile dev up -d phpmyadmin
```

Disponível em <http://localhost:8081>, escutando apenas em `127.0.0.1`. Credenciais:
usuário `root` e a `DB_PASS` do `.env`.

## Comandos do dia a dia

| Tarefa | Comando |
| --- | --- |
| Ver logs | `docker compose logs -f app` |
| Abrir um shell | `docker compose exec app bash` |
| Reiniciar | `docker compose restart app` |
| Parar | `docker compose down` |
| Parar e apagar o banco | `docker compose down -v` |
| Status das migrations | `docker compose exec app php bin/migrate.php status` |
| Acessar o MySQL | `docker compose exec db mysql -u root -p"$DB_PASS" mecanica` |

O código do workspace é montado em `/var/www/html`, então alterações em `src/`,
`public/` e `bin/` valem na hora — não precisa rebuildar. Rebuild só quando mudar o
`Dockerfile` ou as dependências do `composer.json`:

```bash
docker compose build app && docker compose up -d app
```

## O que o `Dockerfile` prepara

- `php:8.2-apache` com `pdo_mysql`, `mbstring` e `zip`
- `poppler-utils` (extração de texto de PDF no assistente)
- document root em `/var/www/html/public`
- `mod_rewrite` e `AllowOverride All`
- erros fora da resposta, log ligado, sessão `HttpOnly`/`SameSite=Lax`, upload de 25 MB
- `storage/` criado e com dono `www-data`

## Problemas comuns

**`port is already allocated` (8080/8081)**
Outro serviço está usando a porta. Pare-o ou troque o mapeamento em
`docker-compose.yml` (`"8090:80"`) e acesse `http://localhost:8090`.

**`defina DB_PASS no .env`**
O compose exige `DB_PASS`. Copie o `.env.example` e preencha.

**Página em branco ou 500 no primeiro acesso**
Rode as migrations. Sem schema, o login consulta uma tabela que não existe.

**`Permission denied` em `storage/`**
O container roda como `www-data`. Em Linux com SELinux, o volume já usa `:Z`;
fora isso, rode `docker compose exec app chown -R www-data:www-data storage`.

**Mudanças no CSS/JS não aparecem**
O `asset()` adiciona `?v=` pelo `mtime` do arquivo, mas o navegador pode manter o
HTML em cache. Force o recarregamento (Ctrl+Shift+R).

**MySQL reiniciou e perdeu dados**
Os dados ficam no volume `db_data`. `docker compose down` preserva; `down -v`
apaga.

## Docker x InfinityFree

A demonstração pública roda em hospedagem compartilhada e tem limitações que não
existem aqui (sem SSH, `exec` bloqueado, 60 s por requisição, upload de 20 MB,
sem cron). O guia daquele ambiente está em
[`DEPLOY_INFINITYFREE.md`](DEPLOY_INFINITYFREE.md). Para desenvolver e para a
apresentação completa, use o Docker.
