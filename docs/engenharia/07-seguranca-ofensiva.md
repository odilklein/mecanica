# Segurança ofensiva — HexStrike AI + Kali

Avaliação de segurança da aplicação Torque executada em 20/11/2026, no commit `a5dd3e7`,
contra a instância local em `http://127.0.0.1:8080`. O teste foi autorizado e conduzido pelo
mantenedor do sistema. O resumo para o documento oficial está no
[Anexo IV](/#/documento).

## Ambiente

| Componente | Configuração |
|---|---|
| Orquestrador | HexStrike AI v6.0 em container Kali Linux (`--network host`) |
| Acesso | API REST em `127.0.0.1:8888`, integrada por MCP ao ambiente de análise |
| Alvo | PHP 8.2, Apache 2.4.69, MySQL 8.4.13 em Docker |
| Rede | host local, sem contato com alvos externos |
| Base | dados fictícios da demonstração |

Ferramentas usadas: `nmap 7.99`, `wafw00f`, `nikto`, `nuclei`, `ffuf`, `gobuster`, `curl`,
`whatweb` e `sqlmap` disponíveis no container.

## Metodologia

1. **Caixa-preta**: varredura de portas e serviços, fingerprint de WAF, más configurações,
   templates de CVE e fuzzing de conteúdo, sem credenciais.
2. **Sessão autenticada**: três perfis (administrador, mecânico-chefe e mecânica) para
   testar RBAC, IDOR e escopo de OS.
3. **White-box**: leitura do Kernel (`Auth`, `Session`, `Router`, `CrudController`,
   `QueryBuilder`, `Repository`, `ViewFactory`), dos três providers, controllers, Resources
   e Services do módulo Agent, migrations, seeds, views, `Dockerfile`, `docker-compose.yml`,
   `public/.htaccess` e `composer.lock`.

O teste foi não destrutivo. Não houve DoS, exclusão de dados, alteração de registros de
negócio nem força bruta real. O rate-limit foi avaliado por código.

## Números da execução

| Etapa | Volume | Resultado |
|---|---|---|
| nmap `-sCV` | 6 portas | 8080 (Apache) e 3306 (MySQL) abertas |
| wafw00f | 7 requisições | nenhum WAF identificado |
| Nikto | 4.215 requisições | 2 cabeçalhos ausentes e 1 divergência de rota |
| Nuclei | 6.774 templates / 16.570 requisições | 0 correspondências |
| ffuf | wordlist `big.txt` | 19 rotas descobertas |
| gobuster | wordlist `common.txt` | enumeração de diretórios e extensões |
| curl | ~80 requisições | sessão, CSRF, IDOR, injeção, cabeçalhos e métodos |
| Revisão de código | 96 rotas mapeadas | 806 saídas `<?=` revisadas |

## Cabeçalhos

Presentes: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`,
`Cache-Control: no-store` em respostas autenticadas e cookie `PHPSESSID` com `HttpOnly` e
`SameSite=Lax`.

Ausentes no momento da varredura: `Content-Security-Policy`, `Strict-Transport-Security` e
`Secure` no cookie (esperado em HTTP local). O banner anunciava `Apache/2.4.69 (Debian)`;
`X-Powered-By` já estava desligado.

## Rotas e arquivos sensíveis

Teste sem sessão:

| Caminho | Resposta | Leitura |
|---|---|---|
| `/login` | 200 | única página pública |
| `/clients`, `/vehicles`, `/parts`, `/suppliers`, `/tickets` | 302 → `/login` | protegido |
| `/reports`, `/settings`, `/users`, `/audit`, `/notifications`, `/profile`, `/assistant` | 302 → `/login` | protegido |
| `/.env`, `/.git/HEAD`, `/vendor/`, `/src/`, `/storage/`, `/composer.json`, `/Dockerfile` | 404 | fora do docroot |
| `/.htaccess`, `/.htpasswd`, `/server-status` | 403 | negado |
| `/assets/`, `/css/`, `/js/` | 403 | listagem desabilitada |

O fuzzing também encontrou `server-status` e `assets/json/`, ambos sem exposição de
conteúdo. Nenhuma rota administrativa respondeu sem sessão.

## Testes manuais

| Teste | Resultado |
|---|---|
| Login com credencial padrão antiga | sucesso antes da correção (SEC-01) |
| Regeneração do ID de sessão no login | ocorre |
| Logout com CSRF válido | sessão invalidada |
| POST sem token CSRF | rejeitado |
| SQLi em `search[value]`, `order[column]` e `order[dir]` | sem efeito |
| SQLi em `/api/parts/search?q='` | resposta normal |
| XSS refletido em `/api/search?q=<script>` | JSON com `<\/script>` |
| IDOR entre OS 398, 399 e 400 | 398 e 400 liberadas, 399 negada |
| Mecânico em `/users`, `/settings`, `/audit`, `/clients` | 302 para o dashboard |
| `/api/notifications` sem sessão | 200 com lista vazia, sem dados |
| `/api/search` e `/api/parts/search` sem sessão | 401 |
| `/api/fipe/models` sem sessão | 200 antes da correção (SEC-09) |
| `/api/transcribe` sem sessão | 401 |

## Achados

| ID | Severidade | Achado | Situação |
|---|---|---|---|
| SEC-01 | Crítico | contas padrão com senha única e publicada | corrigido |
| SEC-02 | Crítico | MySQL 3306 e phpMyAdmin publicados com senha padrão | corrigido |
| SEC-03 | Alto | chave do provedor de IA em texto claro no workspace | pendente de rotação |
| SEC-04 | Alto | sessão sem expiração e sem revalidação de usuário e papel | corrigido |
| SEC-05 | Alto | CSRF fail-open com token vazio | corrigido |
| SEC-06 | Alto | `APP_KEY` com fallback derivado da senha do banco | corrigido |
| SEC-07 | Médio | sem CSP e HSTS; `Secure` não considerava proxy TLS | corrigido |
| SEC-08 | Médio | rate-limit por e-mail e IP contornável; Turnstile desligado | corrigido |
| SEC-09 | Médio | `/api/fipe/*` público e com requisição externa | corrigido |
| SEC-10 | Médio | exceção interna devolvida na criação de OS | corrigido |
| SEC-11 | Médio | CSV injection nas exportações | corrigido |
| SEC-12 | Baixo | upload de transcrição sem validação de MIME real | corrigido |
| SEC-13 | Baixo | controller sem checagem de acesso à OS | corrigido |
| SEC-14 | Baixo | cURL com redirecionamento sem restrição de protocolo | corrigido |
| SEC-15 | Baixo | lista de funcionários exposta a qualquer autenticado | mitigado |
| SEC-16 | Baixo | caminho de anexo sem `realpath()` | corrigido |
| SEC-17 | Baixo | logout sem destruir sessão e sem regenerar ID | corrigido |
| SEC-18 | Baixo | banner do Apache com versão exata | corrigido |

### Detalhes relevantes

- **SEC-01**: a senha padrão saiu da migration e dos seeds. O comando
  `bin/create_admin.php` cria ou redefine credenciais e a migration `0015` rotacionou as
  contas existentes.
- **SEC-02**: a porta 3306 não é mais publicada, `DB_PASS` é obrigatória e o phpMyAdmin
  ficou em `profiles: [dev]`, com bind em `127.0.0.1`.
- **SEC-04**: `Auth::check()` aplica `SESSION_IDLE_TIMEOUT` e revalida `is_active`, papel,
  nome e e-mail no banco.
- **SEC-05**: `Session::verifyCsrf()` recusa token vazio e sessão sem token; o token é
  gerado em `Session::start()`.
- **SEC-06**: `OfferToken::secret()` falha quando `APP_KEY` está ausente ou tem menos de
  32 bytes.
- **SEC-11**: o helper `csv_cell()` neutraliza `=`, `+`, `-`, `@`, tabulação e retorno de
  carro nas exportações.

## Controles positivos

| Controle | Evidência |
|---|---|
| SQL Injection | PDO com prepared statements, `EMULATE_PREPARES=false`, allowlist de ordenação e `(int)` em limites |
| XSS | 806 saídas revisadas, `e()` com `ENT_QUOTES`, JSON com `JSON_HEX_TAG` e `textContent` no JS |
| CSRF | `guardCsrf` em todos os POSTs, inclusive AJAX, com `hash_equals` |
| RBAC | `guardRoles`, `OrderAccess`, `TicketService::canAccess` e `AgentStore` com escopo |
| IDOR | OS e anexos filtrados por participante; notificações por `user_id` |
| Sessão | `use_strict_mode`, `HttpOnly`, `SameSite=Lax` e regeneração no login |
| Senhas | bcrypt com custo 12, rehash automático e hash ausente de JSON e auditoria |
| Upload | `finfo`, allowlist, limites de 10 MB e 5 MB, nome em hash e armazenamento fora de `public/` |
| IA | propostas com aprovação humana, tokens com TTL e anexos tratados como dado |
| Dependências | versões atuais, sem pacote conhecidamente vulnerável no `composer.lock` |

## Correções e verificação

Depois das correções, os testes de regressão confirmaram:

- login com a senha padrão antiga rejeitado;
- login com a senha rotacionada aceito;
- POST de login com `csrf_token` vazio rejeitado;
- usuário desativado perde a sessão na requisição seguinte;
- cookie reutilizado após logout não abre sessão;
- `/api/fipe/models` sem sessão retorna 401;
- CSP presente e HSTS condicionado a HTTPS;
- porta 3306 não publicada no host;
- `php -l` sem erros nos arquivos alterados.

## Pendências

1. Rotacionar a chave do provedor de IA no painel do fornecedor (SEC-03).
2. Trocar os scripts inline por CSP com nonce e remover `'unsafe-inline'`.
3. Incluir `composer audit --locked` e a suíte do HexStrike no CI.
4. Repetir a avaliação com HTTPS, proxy reverso e WAF antes de produção.

## Reprodução

```bash
# 1. HexStrike (container Kali com API em 127.0.0.1:8888)
bash docs/seguranca/scripts/hexstrike-up.sh
curl -s http://127.0.0.1:8888/health

# 2. Aplicação
docker compose up -d app
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/login

# 3. Suíte completa
bash docs/seguranca/scripts/scan-hexstrike.sh
```

Os resultados brutos ficam em `docs/seguranca/artefatos/`:

| Arquivo | Conteúdo |
|---|---|
| `nmap.txt` | portas, versões e certificado do MySQL |
| `nikto.txt` | más configurações e cabeçalhos ausentes |
| `nuclei.txt` | estatísticas dos templates |
| `wafw00f.txt` | ausência de WAF |
| `gobuster.txt` | diretórios e extensões |
| `ffuf.txt` | rotas descobertas |
| `headers.txt` | cabeçalhos, métodos e caminhos sensíveis |

## Varredura final (20/11/2026)

Uma última rodada foi executada depois das correções, em quatro alvos:
o sistema local em Docker, o site de demonstração na InfinityFree, a
documentação na Cloudflare Pages e o próprio repositório.

| Alvo | Destaque |
|---|---|
| Docker `127.0.0.1:8080` | Nuclei 0 correspondências em 16,4 mil requisições; nenhum achado novo |
| Demo `torque-zenitec.page.gd` | CSP/HSTS/XFO só nos assets (corrigido); CSP bloqueava a ViaCEP (corrigido); Turnstile e CSRF falham fechados |
| Docs `torque-zenitec.pages.dev` | Cloudflare WAF; HSTS ausente (corrigido); avatar externo bloqueado pela CSP (corrigido com imagem local); soft 404 (corrigido) |
| Repositório | Gitleaks sem credencial viva; Semgrep sem vulnerabilidade confirmada; Composer 0 avisos; `php -l` 142/0 |

O relatório completo, com os artefatos, está em
`docs/seguranca/RELATORIO_VARREDURA_FINAL.md`.

## Limitações

- Alvo em HTTP local, sem TLS e sem WAF. HSTS e `Secure` foram avaliados por configuração.
- Nuclei foi executado sem autenticação; 0 correspondências não prova ausência de falha
  lógica.
- O teste não cobriu carga, DoS, engenharia social nem infraestrutura de produção.
- A chave do provedor de IA não foi usada e não é reproduzida neste documento.
