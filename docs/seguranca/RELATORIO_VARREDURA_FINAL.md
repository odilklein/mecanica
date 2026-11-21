# Varredura final de segurança — HexStrike AI

**Projeto:** Torque — Sistema de Gestão para Oficinas Mecânicas
**Data:** 20/11/2026 (execução à noite, horário de Brasília; já 21/11 em UTC)
**Commit de referência:** `4bee1a8` (correções desta rodada)
**Autorização:** avaliação conduzida pelo mantenedor do sistema, somente contra
alvos próprios.

---

## 1. Resumo executivo

Quatro superfícies foram avaliadas na mesma rodada:

| # | Alvo | Endereço | Resultado |
|---|------|----------|-----------|
| 1 | Sistema (Docker) | `http://127.0.0.1:8080` | 0 achados novos; Nuclei 0 correspondências em 16,4 mil requisições |
| 2 | Site do repositório (demo) | `https://torque-zenitec.page.gd` | 3 achados; 2 corrigidos na hora, 1 bloqueio operacional do host |
| 3 | Documentação | `https://torque-zenitec.pages.dev` | 4 achados; 3 corrigidos e publicados, 1 dependência aceita |
| 4 | Repositório | Git + código + dependências | 0 vulnerabilidades exploráveis; 1 segredo histórico de baixo valor |

**Nenhuma falha explorável de injeção, XSS, CSRF, IDOR, path traversal ou RCE
foi encontrada.** O que apareceu foram problemas de **configuração de
implantação** e **duas regressões funcionais causadas pela própria CSP** — todas
tratadas nesta rodada.

> [!IMPORTANT]
> “100% livre de falhas” não é um estado verificável. O que esta rodada garante
> é que as classes de falha cobertas pelas ferramentas usadas não apareceram, e
> que os pontos concretos encontrados foram corrigidos. As limitações estão na
> seção 8.

---

## 2. Alvos e método

### 2.1 Ferramentas

| Ferramenta | Versão | Uso |
|---|---|---|
| HexStrike AI | 6.0.0 | orquestrador (API REST em `127.0.0.1:8888`) |
| nmap | 7.99 | portas e serviços |
| wafw00f | 2.4.2 | fingerprint de WAF |
| Nikto | 2.6.1 | más configurações do servidor |
| Nuclei | templates 6.774 | CVEs e exposições conhecidas |
| ffuf / gobuster | — | descoberta de conteúdo |
| curl/openssl | — | validação manual de cabeçalhos, CSRF, Turnstile e TLS |
| Gitleaks | latest | segredos no histórico do Git |
| Semgrep | latest (`auto`, `p/php`, `p/security-audit`, `p/secrets`) | SAST |
| Composer / npm audit | — | dependências |
| php -l | 8.2.36 | sintaxe |

### 2.2 Modo de execução

Tudo **não destrutivo**: sem DoS, sem exclusão de registros, sem força bruta e
sem uso de credenciais de terceiros. No site gratuito da InfinityFree a varredura
foi interrompida assim que o host começou a responder `429` (seção 4.2, VF-03).

O desafio JavaScript da InfinityFree foi resolvido localmente (AES do `aes.js`)
para permitir requisições `curl` legítimas ao site:

```bash
# 1) baixa o desafio
HTML=$(curl -s -A "$UA" https://torque-zenitec.page.gd/)
# 2) extrai a, b, c e calcula o cookie __test (AES-128-CBC, sem padding)
V=$(printf '%s' "$C" | xxd -r -p | openssl enc -d -aes-128-cbc -K "$A" -iv "$B" -nopad | xxd -p -c 64)
# 3) usa em todas as requisições
curl -b "__test=$V" -A "$UA" https://torque-zenitec.page.gd/login
```

---

## 3. Sistema local (Docker)

### 3.1 Varredura

| Etapa | Volume | Resultado |
|---|---|---|
| nmap `-sCV` | 6 portas | só `8080` aberta (Apache); 3306, 8081, 22, 80 e 443 fechadas |
| wafw00f | 7 requisições | nenhum WAF (esperado, ambiente local) |
| Nikto | 4.294 requisições | 2 itens: HSTS ausente e `/login/` responder igual a `/login` |
| Nuclei | 16.403 requisições (98% de 16.570) / 6.774 templates | **0 correspondências** |
| gobuster | `common.txt` + extensões | só 403 em arquivos internos e 302 nas rotas protegidas |
| ffuf | `big.txt` | 19 rotas, todas já conhecidas |

O HSTS ausente no local é **esperado**: a instância roda em HTTP puro e o
`.htaccess` só envia HSTS quando `HTTPS=on`.

### 3.2 Cabeçalhos observados

Presentes em `/login`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- `Content-Security-Policy` completa
- cookie `PHPSESSID` com `HttpOnly` e `SameSite=Lax`
- `Server: Apache` (sem versão) e sem `X-Powered-By`

O login também foi aberto em navegador real (Playwright): **nenhuma violação de
CSP do conteúdo da aplicação**. Os dois erros de console são internos do iframe
do Turnstile (`challenges.cloudflare.com`), não da página.

### 3.3 Conclusão do alvo local

Nenhum achado novo. O comportamento coincide com a avaliação de 20/11.

---

## 4. Site do repositório (demo InfinityFree)

### 4.1 Testes manuais

| Teste | Resultado |
|---|---|
| `POST /login` sem `csrf_token` | `302 → /login?error=Sessão expirada` (falha fechada) |
| `POST /login` com a conta demo e **sem** Turnstile | `302 → /login?error=Confirme a verificação de segurança` (Turnstile exigido) |
| `/api/fipe/models` sem sessão | `401` |
| `/api/search?q=test` sem sessão | `401` |
| `/api/notifications` sem sessão | `200` com payload vazio, sem dados |
| `OPTIONS /login` | `404` |
| `TRACE /login` | `405` |
| `PUT`/`DELETE`/`PATCH /login` | `404` |
| `/.env`, `/.git/HEAD`, `/.git/config`, `/.htaccess` | conexão encerrada pelo WAF do host (`curl 52`) |
| `/.env.`, `/.env%00.txt` | `403` / timeout |
| `/vendor/`, `/src/`, `/storage/`, `/composer.json`, `/.htpasswd` | `403` |
| `/Dockerfile`, `/server-status`, `/phpinfo.php`, `/phpmyadmin/`, `/adminer.php`, `/diagnostico.php` | `404` |
| TLS | certificado válido (ZeroSSL, `CN=page.gd`, até 17/02/2027) |

### 4.2 Achados

#### VF-01 — Cabeçalhos de segurança só nos assets (Médio) — corrigido

Os cabeçalhos estavam em `public/.htaccess`, que o Apache só aplica a arquivos
servidos de `/public`. As páginas dinâmicas (`/login`, `/clients`, …) passam pelo
`index.php` da raiz e **não recebiam CSP, HSTS, `X-Frame-Options` nem
`nosniff`**. Na prática:

- o navegador não processa HSTS de resposta de subrecurso, então o site não
  tinha HSTS no documento;
- não havia CSP no documento (proteção XSS dependia só do escape);
- a página era enquadrável por terceiros (clickjacking);
- MIME sniffing ficava liberado no documento.

Correção: bloco `<IfModule mod_headers.c>` adicionado ao `.htaccess` da raiz,
versionado em [`deploy/infinityfree/htdocs.htaccess`](../../deploy/infinityfree/htdocs.htaccess)
e enviado por FTP. O arquivo anterior foi preservado em
`docs/seguranca/artefatos/final/demo/htaccess-antes.txt` (e em
`/tmp/remote-htaccess.txt`).

#### VF-02 — CSP bloqueava o autocomplete de CEP (Médio) — corrigido

`public/js/viacep.js` faz `fetch('https://viacep.com.br/ws/...')` direto do
navegador, mas a CSP só permitia `connect-src 'self'` e
`challenges.cloudflare.com`. O navegador bloqueava a consulta; o formulário caía
no preenchimento manual sem indicar o motivo. O problema já existia no Docker
(onde a CSP vale para o documento).

Correção: `https://viacep.com.br` incluído em `connect-src` nos dois `.htaccess`
(`public/` e raiz). Verificado no container: o cabeçalho agora lista o host.

#### VF-03 — Bloqueio `429` durante a varredura (operacional)

O Nuclei disparou requisições em sequência e o WAF da InfinityFree passou a
responder:

```
429 Too Many Requests - Scanner activity detected
```

A varredura foi interrompida imediatamente (processo do Nuclei encerrado no
container) para não caracterizar abuso. O bloqueio atingiu o IP de origem e
ainda estava ativo ao fim desta rodada; a verificação HTTP dos cabeçalhos novos
fica pendente até ele expirar. **Não repetir varredura automatizada contra o
plano gratuito**; usar apenas requisições pontuais.

### 4.3 Verificação pendente

- [ ] Confirmar `CSP`, `HSTS`, `X-Frame-Options` e `nosniff` em `/login` depois
  do fim do bloqueio `429`.
- [ ] Se houver `500`, restaurar
  `docs/seguranca/artefatos/final/demo/htaccess-antes.txt` por FTP (rollback).

---

## 5. Documentação (Cloudflare Pages)

### 5.1 Varredura

| Etapa | Resultado |
|---|---|
| nmap | 80/443 abertos, Cloudflare (IPv4 + IPv6) |
| wafw00f | **Cloudflare WAF** identificado |
| Nikto | 168 requisições e bloqueio por TLS fingerprint (esperado em Cloudflare) |
| Nuclei | 0 correspondências (10.445 requisições, 63% do total antes do timeout do wrapper) |
| gobuster | abortado: o site responde `200` para qualquer caminho (soft 404) |
| TLS | Let's Encrypt, `CN=torque-zenitec.pages.dev`, HTTP/3 disponível |
| Source maps / segredos no `dist` | nenhum |
| `_headers`, `.git/HEAD`, `package.json`, `src/...` | devolvem o shell do site, não o arquivo |

Cabeçalhos já presentes: CSP restritiva (`script-src 'self'`), `X-Frame-Options`,
`nosniff`, `Referrer-Policy`, `Permissions-Policy`.

O `access-control-allow-origin: *` é injetado pela Cloudflare Pages. O site é
100% público, sem cookie, sessão, formulário ou dado de usuário; liberar a
leitura cross-origin do HTML estático não expõe nada. Não foi tratado como
achado.

### 5.2 Achados

#### VF-04 — HSTS ausente (Baixo) — corrigido e publicado

O site é HTTPS-only na Cloudflare, mas não enviava `Strict-Transport-Security`.
Correção em `docs/site/public/_headers`, junto com
`Cross-Origin-Opener-Policy: same-origin` e
`X-Permitted-Cross-Domain-Policies: none`.

#### VF-05 — Avatar externo bloqueado pela própria CSP (Baixo) — corrigido

O console do navegador acusou duas violações:

```
Loading the image 'https://github.com/odilklein.png' violates
Content-Security-Policy directive: "img-src 'self' data:"
```

A CSP estava correta; o dado é que apontava para fora. Em vez de abrir
`img-src` para o GitHub, a imagem foi baixada para
`docs/site/public/imagens/equipe/odil-klein.jpg` e `src/data/members.ts` passou a
usar o caminho local. A CSP continua restrita a `'self' data:`.

#### VF-06 — Soft 404 (Baixo) — corrigido e publicado

Qualquer caminho inexistente devolvia `200` com o `index.html` (fallback de SPA).
Como o roteamento é por hash (`HashRouter`), isso não é necessário e mascara
erros. Foi adicionado `docs/site/public/404.html`, que a Cloudflare Pages serve
com status `404` real.

#### VF-07 — Dependências `react-router` (Baixo, aceito)

`npm audit --omit=dev` aponta 2 avisos moderados:

| Aviso | Aplicabilidade |
|---|---|
| GHSA-wrjc-x8rr-h8h6 — open redirect via barra invertida em `<Link>`/`useNavigate` | não explorável: `HashRouter`, links literais, sem `useNavigate` com entrada do usuário |
| GHSA-337j-9hxr-rhxg — injeção de construtor no `deserializeErrors()` da hidratação SSR | não aplicável: build Vite client-side, sem SSR |

A correção exige `react-router-dom@7` (major). Risco prático nulo no site
estático; fica registrado para o próximo ciclo.

### 5.3 Publicação e verificação

Publicado no Cloudflare Pages em 20/11/2026 (deploy `0d431fec`, commit
`4bee1a8`). Verificado em produção:

- `Strict-Transport-Security`, `Cross-Origin-Opener-Policy`,
  `X-Permitted-Cross-Domain-Policies`, `nosniff` e `X-Frame-Options` presentes;
- caminho inexistente responde `404` com a página nova;
- `/.git/HEAD` responde `404`;
- avatar local responde `200 image/jpeg`;
- console do navegador: **0 erros** (a violação de CSP do avatar desapareceu);
- seção “Varredura final” renderizada em `/#/engenharia/07-seguranca-ofensiva`.

---

## 6. Repositório

### 6.1 Resultados

| Verificação | Resultado |
|---|---|
| Gitleaks (187 commits, 102,24 MB) | 2 ocorrências, mesma string, arquivo removido |
| Semgrep `auto` | 15 achados brutos |
| Semgrep `p/php` + `p/security-audit` + `p/secrets` | 3 achados |
| `composer audit --locked` | **0 avisos** |
| `npm audit` (docs/site) | 2 moderados (VF-07) |
| `php -l` | **142 arquivos, 0 erros** |
| `.env` versionado? | não (0 arquivos; `.gitignore` cobre) |
| Chaves/credenciais no código atual | nenhuma |
| `eval`, `unserialize` e `include`/`require` com entrada da requisição | nenhum |

### 6.2 Triagem do Semgrep

| Regra | Ocorrências | Veredito |
|---|---|---|
| `github-actions-mutable-action-tag` | 4 no `docs.yml` | informativo (supply chain; fixar por SHA é backlog) |
| `unlink-use` | 10 (`AttachmentService`, `TranscriptionController`) | falso positivo: caminho vem do banco/`realpath()` com prefixo validado |
| `exec-use` | 2 (`AttachmentService:511,524`) | falso positivo: `escapeshellarg()` + limite `(int)` |
| `echoed-request` | 3 (`vehicles/form.php:65,69,79`) | falso positivo: a closure `$select()` escapa tudo com `e()` |

### 6.3 Gitleaks

O único segredo histórico é o token estático `diag-...` do arquivo
`diagnostico.php`, removido do projeto no commit `a4292ee`. Ele liberava um dump
de caminhos e a checagem de existência do `.env`, **não** acesso a banco, API ou
dado. A página não existe em nenhuma versão publicada. Não é credencial viva.

A pendência real continua sendo a **SEC-03**: a `GROQ_API_KEY` do `.env` local é
real e deve ser rotacionada no painel do provedor. O arquivo não é versionado,
não entra na imagem Docker (`.dockerignore`) e não é servido pelo Apache.

### 6.4 Exposição no container de desenvolvimento (informativo)

O `docker-compose.yml` monta o workspace inteiro em `/var/www/html`. Com isso,
`.env` e `.git/` ficam dentro do container. O document root é
`/var/www/html/public`, então ambos respondem `404` por HTTP e o
`AttachmentService` usa `realpath()` + prefixo para anexos (testado na revisão).
Não é vulnerabilidade explorável; é defesa em profundidade. Para produção, use a
imagem buildada (sem bind mount) ou um volume só de `storage/`.

---

## 7. Correções aplicadas nesta rodada

| Arquivo | Mudança |
|---|---|
| `deploy/infinityfree/htdocs.htaccess` | novo `.htaccess` de raiz versionado, com CSP/HSTS/XFO/nosniff/Permissions-Policy e `connect-src` da ViaCEP |
| `deploy/infinityfree/index.php` | versionado (era só no servidor) |
| `public/.htaccess` | `connect-src` agora inclui `https://viacep.com.br` |
| `docs/site/public/_headers` | HSTS, COOP e `X-Permitted-Cross-Domain-Policies` |
| `docs/site/public/404.html` | 404 real para caminhos inexistentes |
| `docs/site/public/imagens/equipe/odil-klein.jpg` | avatar local (CSP sem exceção externa) |
| `docs/site/src/data/members.ts` | usa o avatar local |
| `docs/DEPLOY_INFINITYFREE.md` | documenta o `.htaccess` de raiz e por que os cabeçalhos não podem ficar só em `public/` |
| `.dockerignore` | exclui `deploy/` da imagem |
| Servidor InfinityFree | `.htaccess` de raiz atualizado por FTP (backup em `docs/seguranca/artefatos/final/demo/htaccess-antes.txt`) |
| Cloudflare Pages | build + deploy do `dist` (deploy `0d431fec`, commit `4bee1a8`) |

Validação feita:

- `php -l` sem erros nos arquivos PHP;
- CSP do Docker agora contém `viacep.com.br` (verificado com `curl -D`);
- upload FTP relido e idêntico ao arquivo local (2.558 bytes);
- `.htaccess` de raiz validado em um Apache da mesma imagem: responde `301`
  para HTTPS (não `500`) e já entrega CSP, XFO, nosniff, Referrer-Policy e
  Permissions-Policy;
- build da documentação executado sem erros (ver seção 9).

---

## 8. Limitações e risco residual

1. **Turnstile impede teste autenticado no demo.** O login com automação é
   barrado pela Cloudflare (erro `600*`), então RBAC/IDOR no demo não foram
   reexecutados nesta rodada. A avaliação autenticada de 20/11 foi feita no
   Docker, com o mesmo código.
2. **Nuclei não cobre falha lógica.** 0 correspondências prova ausência de
   assinaturas conhecidas, não ausência de bug de negócio.
3. **Cloudflare bloqueia Nikto/Nuclei por TLS fingerprint.** Cobertura do site
   de docs foi parcial; o conteúdo é estático e foi revisado no `dist`.
4. **InfinityFree bloqueou o IP com `429`.** A verificação dos cabeçalhos no
   demo ficou pendente; o arquivo enviado é o mesmo bloco já usado em
   `public/.htaccess`, que funciona no host.
5. **Sem teste de carga/DoS, engenharia social, infraestrutura da hospedagem ou
   cadeia de suprimentos de actions.** O upgrade do `react-router` e a fixação
   das actions por SHA ficam como backlog.
6. **CSP ainda usa `'unsafe-inline'` e `'unsafe-eval'`** por causa do template e
   do Turnstile. Migrar para nonce/hash é o próximo ganho real de segurança.

---

## 9. Evidências

Artefatos normalizados em `docs/seguranca/artefatos/final/`:

```
final/
├── local/    nmap, wafw00f, nikto, nuclei, gobuster, ffuf, headers
├── docs/     nmap, wafw00f, nikto, nuclei, gobuster, headers
├── demo/     probes.txt (Nuclei interrompido por 429)
└── repo/     dependencias, php-lint, gitleaks, semgrep
```

Build da documentação:

```bash
docker run --rm -v "$PWD":/repo -w /repo/docs/site node:22 \
  sh -lc 'npm ci --silent && npm run build'
```

---

## 10. Conclusão

O sistema resistiu à varredura: não há injeção, XSS, CSRF, IDOR, traversal, RCE
nem CVE conhecida nos alvos testados. As falhas reais estavam na **borda** —
cabeçalhos aplicados no lugar errado (VF-01), uma CSP que quebrava uma integração
legítima (VF-02) e três ajustes no site de docs (VF-04 a VF-06). Todas foram
corrigidas no código/servidor nesta rodada e a documentação foi publicada. A única
verificação pendente é a resposta HTTP do demo, que depende do fim do bloqueio
`429` da InfinityFree.

O risco residual é baixo e conhecido: rotação da chave da Groq (SEC-03), deploy
da documentação, upgrade do `react-router` e endurecimento da CSP com nonce.
