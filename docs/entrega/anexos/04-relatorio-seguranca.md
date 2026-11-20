# Anexo IV — Relatório de segurança

Este anexo registra a avaliação de segurança feita no sistema Torque em 20/11/2026. O
trabalho combinou varredura dinâmica com o HexStrike AI v6.0, em container Kali Linux, e
revisão do código-fonte. O teste foi autorizado e executado pelo próprio mantenedor, contra
uma instância local da aplicação.

## IV.1 Resumo

A aplicação passou por 16.570 requisições automatizadas de templates de vulnerabilidade,
4.215 requisições do Nikto, fuzzing de conteúdo e cerca de 80 testes manuais de sessão,
CSRF, autorização e injeção. O núcleo do sistema resistiu: não foi encontrada falha
explorável de SQL Injection, XSS, SSRF, RCE, path traversal, mass assignment ou open
redirect.

Os problemas reais estavam na forma de implantação e na gestão de credenciais. Foram
registrados 18 achados, distribuídos como na Tabela 3.

Tabela 3 – Achados por severidade

| Severidade | Quantidade | Destaques |
|---|---|---|
| Crítico | 2 | contas padrão ativas e banco publicado com senha padrão |
| Alto | 4 | chave de API em texto claro, sessão sem expiração, CSRF fail-open e chave de aplicação com fallback previsível |
| Médio | 5 | ausência de CSP e HSTS, limite de login contornável, rota FIPE sem autenticação, vazamento de exceção e CSV injection |
| Baixo | 7 | validação de upload, código morto sem autorização, cURL sem restrição de protocolo, entre outros |

A maior parte foi corrigida no mesmo dia. A única pendência administrativa é a rotação da
chave do provedor de IA, que depende do painel do fornecedor.

## IV.2 Escopo e autorização

O alvo foi a aplicação local em `http://127.0.0.1:8080`, com MySQL em `127.0.0.1:3306`,
no commit `a5dd3e7`. Nenhum teste foi direcionado a serviços de terceiros. O ambiente é o
mesmo usado na demonstração do projeto, com dados fictícios.

Foram testados os quatro perfis do sistema: administrador, secretaria, mecânico-chefe e
mecânico. Cada perfil foi usado para validar autorização, acesso a ordens de serviço e
separação de dados.

## IV.3 Ambiente e ferramentas

Tabela 4 – Ambiente de teste

| Componente | Configuração |
|---|---|
| Orquestrador | HexStrike AI v6.0, container Kali Linux, API em `127.0.0.1:8888` e integração por MCP |
| Alvo | aplicação PHP 8.2 / Apache 2.4.69 / MySQL 8.4.13 em Docker |
| Ferramentas disponíveis | nmap, Nikto, Nuclei, sqlmap, ffuf, gobuster, whatweb, wafw00f, curl |
| Rede | container em modo host, restrito à máquina local |

Tabela 5 – Ferramentas usadas

| Ferramenta | Objetivo | Execução |
|---|---|---|
| nmap | portas e identificação de serviços | portas 22, 80, 443, 8080, 3306 e 8081 |
| wafw00f | detecção de WAF | 7 requisições |
| Nikto | más configurações do servidor web | 4.215 requisições |
| Nuclei | CVEs e exposições conhecidas | 6.774 templates e 16.570 requisições |
| ffuf | descoberta de conteúdo | wordlist `big.txt`, 19 rotas |
| gobuster | enumeração de diretórios | wordlist `common.txt` |
| curl | validação manual de sessão, CSRF, IDOR, injeção e cabeçalhos | cerca de 80 requisições |

## IV.4 Metodologia

A avaliação teve três frentes:

- **Caixa-preta:** fingerprint de serviços, detecção de WAF, varredura de más
  configurações, fuzzing de diretórios e templates de vulnerabilidade, sem credenciais.
- **Sessão autenticada:** três perfis usados para testar autorização, IDOR e acesso a
  ordens de serviço de terceiros.
- **Revisão de código:** leitura do núcleo (`Auth`, `Session`, `Router`, `QueryBuilder`,
  `Repository`, `ViewFactory`), dos três módulos, das migrations, das views, do
  `Dockerfile`, do `docker-compose.yml` e do `public/.htaccess`.

Os testes foram não destrutivos: não houve carga de negação de serviço, exclusão de dados
de negócio nem força bruta real. O limite de login foi avaliado pelo código.

## IV.5 Resultados da varredura

### IV.5.1 Portas e serviços

Tabela 6 – Portas e serviços

| Porta | Estado | Serviço | Observação |
|---|---|---|---|
| 8080 | aberto | Apache 2.4.69 | aplicação, com redirecionamento para o login |
| 3306 | aberto | MySQL 8.4.13 | aceitava conexão da rede, não só do container da aplicação |
| 8081 | fechado | phpMyAdmin | container parado no momento do teste |
| 22, 80 e 443 | fechados | — | sem serviço exposto |

### IV.5.2 Cabeçalhos HTTP

Estavam presentes `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`, `Cache-Control: no-store` nas respostas autenticadas e o cookie de
sessão com `HttpOnly` e `SameSite=Lax`. Faltavam `Content-Security-Policy` e
`Strict-Transport-Security`. O servidor anunciava a versão exata do Apache e não enviava
`X-Powered-By`.

### IV.5.3 Varreduras automatizadas

O Nikto apontou os dois cabeçalhos ausentes e uma diferença de resposta entre `/login` e
`/login/`, sem impacto de exploração. O Nuclei não encontrou correspondência nos 6.774
templates. O resultado indica ausência de CVEs e exposições conhecidas cobertas por
template; não substitui a revisão de falhas lógicas, feita no código.

### IV.5.4 Fuzzing e rotas

O ffuf encontrou 19 caminhos, entre eles `assets/`, `assistant`, `audit`, `clients`,
`login`, `notifications`, `parts`, `profile`, `reports`, `settings`, `suppliers`,
`tickets`, `users` e `vehicles`. A Tabela 7 mostra o comportamento sem sessão.

Tabela 7 – Rotas testadas sem sessão

| Caminho | Sem sessão | Observação |
|---|---|---|
| `/login` | 200 | única página pública |
| `/clients` e demais módulos | 302 para `/login` | proteção correta |
| `/.env`, `/.git/`, `/vendor/`, `/src/`, `/storage/` | 404 | fora do diretório público |
| `/.htaccess`, `/.htpasswd`, `/server-status` | 403 | acesso negado |
| `/assets/`, `/css/`, `/js/` | 403 | listagem desabilitada |

### IV.5.5 Testes manuais

Os testes confirmaram a regeneração do ID de sessão no login, a exigência de CSRF nos
formulários, a negação de acesso entre perfis e o escopo de OS por responsável. Testes de
injeção em filtros, ordenação e busca não alteraram o resultado nem atrasaram a resposta.
Um mecânico tentou abrir três ordens: duas em que participava foram liberadas e a terceira
foi negada.

## IV.6 Achados e correções

A Tabela 8 resume os 18 achados e a situação após as correções.

Tabela 8 – Achados e situação após a correção

| ID | Severidade | Achado | Situação |
|---|---|---|---|
| SEC-01 | Crítico | contas padrão com senha única, criadas pela migration e publicadas na documentação | corrigido |
| SEC-02 | Crítico | MySQL e phpMyAdmin publicados, com senha padrão | corrigido |
| SEC-03 | Alto | chave do provedor de IA em texto claro no ambiente local | pendente de rotação |
| SEC-04 | Alto | sessão sem expiração por inatividade e sem revalidar usuário e papel | corrigido |
| SEC-05 | Alto | verificação de CSRF aceitava token vazio em sessão sem token | corrigido |
| SEC-06 | Alto | chave da aplicação com fallback previsível, permitindo forjar tokens | corrigido |
| SEC-07 | Médio | ausência de CSP e HSTS e cookie sem `Secure` atrás de proxy | corrigido |
| SEC-08 | Médio | limite de login por e-mail e IP, contornável por password spraying | corrigido |
| SEC-09 | Médio | rotas `/api/fipe/*` acessíveis sem autenticação | corrigido |
| SEC-10 | Médio | mensagem interna de exceção devolvida ao usuário | corrigido |
| SEC-11 | Médio | CSV injection nas exportações | corrigido |
| SEC-12 | Baixo | upload de transcrição sem validação do tipo real | corrigido |
| SEC-13 | Baixo | controller sem checagem de acesso à OS, hoje sem rota | corrigido |
| SEC-14 | Baixo | cURL com redirecionamento sem restrição de protocolo | corrigido |
| SEC-15 | Baixo | lista de funcionários exposta a qualquer usuário autenticado | mitigado |
| SEC-16 | Baixo | caminho de anexo sem `realpath()` | corrigido |
| SEC-17 | Baixo | logout sem destruir a sessão e sem regenerar o ID | corrigido |
| SEC-18 | Baixo | banner do servidor com a versão exata do Apache | corrigido |

### IV.6.1 Achados críticos e altos

**SEC-01 — contas padrão.** A migration criava três contas com a mesma senha, e a
documentação publicava essa senha. Qualquer instalação nova nascia com acesso
administrativo conhecido. A criação foi retirada da migration, o seed passou a gerar uma
senha aleatória ou a ler uma variável de ambiente, e um comando `bin/create_admin.php`
foi criado para cadastrar ou redefinir credenciais. As contas existentes foram rotacionadas
e a senha saiu do README e do relatório de auditoria.

**SEC-02 — banco publicado.** O `docker-compose.yml` expunha a porta 3306 e o phpMyAdmin
com senha padrão. A porta deixou de ser publicada, a senha do banco passou a ser
obrigatória e o phpMyAdmin foi movido para um perfil de desenvolvimento com acesso restrito
a `127.0.0.1`. As senhas de root e da aplicação foram trocadas.

**SEC-03 — chave do provedor de IA.** A chave estava no `.env` local, fora do Git e da
imagem Docker, mas dentro do diretório de trabalho. O arquivo não é versionado e não entra
na entrega. A rotação da chave é a única ação que depende do painel do fornecedor.

**SEC-04 — sessão.** O sistema não tinha tempo de inatividade nem consultava o banco depois
do login. Um usuário desativado continuava com o papel antigo até sair. A sessão passou a
expirar após 120 minutos e a revalidar situação, papel, nome e e-mail a cada requisição.

**SEC-05 — CSRF.** A comparação de token aceitava `hash_equals('', '')` quando a sessão
ainda não tinha token. A verificação passou a recusar token vazio e a gerar o token no
início da sessão.

**SEC-06 — chave da aplicação.** Sem `APP_KEY`, o segredo dos tokens de proposta era
derivado da senha do banco. A aplicação agora falha ao iniciar quando a chave não existe ou
tem menos de 32 bytes.

### IV.6.2 Achados médios e baixos

As correções incluíram CSP com `frame-ancestors 'self'` e HSTS sob HTTPS, limites de login
por IP e por conta, autenticação nas rotas da FIPE, mensagem genérica na criação de OS,
neutralização de fórmulas nas exportações CSV, validação do MIME real no upload de
transcrição, remoção do controller sem autorização, restrição de protocolo no cURL,
`realpath()` nos anexos, destruição da sessão no logout e redução do banner do Apache. A
lista de funcionários foi restringida aos papéis que podem atribuir atendimento.

## IV.7 Controles que resistiram

Tabela 9 – Controles que resistiram aos testes

| Controle | Evidência |
|---|---|
| SQL Injection | PDO com prepared statements em toda a aplicação, `EMULATE_PREPARES` desligado, ordenação por allowlist e limites convertidos para inteiro. Testes com aspas, `OR 1=1`, `SLEEP` e `DROP` não tiveram efeito. |
| XSS | 806 saídas revisadas, escape com `ENT_QUOTES`, JSON com tags codificadas e uso de `textContent` no JavaScript. |
| CSRF | Token por sessão em todos os POSTs, inclusive AJAX, com comparação em tempo constante. |
| Autorização | Verificação de papel no servidor, escopo de OS por responsável e menu filtrado por perfil. |
| IDOR | Ordens acessíveis apenas por quem participa; notificações e anexos sempre filtrados pelo usuário. |
| Sessão | `use_strict_mode`, `HttpOnly`, `SameSite=Lax` e regeneração do ID no login. |
| Senhas | `password_hash` com bcrypt, `password_verify` e rehash automático; hash nunca vai para JSON nem para auditoria. |
| Upload | Validação por `finfo`, limites de tamanho, nome em hash e armazenamento fora de `public/`. |
| Segredos | `.env` fora do Git e da imagem, histórico sem chave real e `.env.example` limpo. |
| Infraestrutura | Docroot em `public/`, erros desligados, listagem de diretórios bloqueada e arquivos internos fora do alcance. |
| Assistente de IA | Apenas propostas, aprovação humana, verificação de papel, tokens com validade e anexos tratados como dado. |
| Dependências | Versões atuais de router, container, templates, PSR-7 e dotenv, sem pacote conhecidamente vulnerável. |

## IV.8 Verificação após as correções

Tabela 10 – Verificação após as correções

| Teste | Resultado |
|---|---|
| Login com a senha padrão antiga | rejeitado |
| Login com a senha rotacionada | aceito, com os módulos respondendo |
| POST de login com token CSRF vazio | rejeitado |
| Usuário desativado no banco durante a sessão | sessão encerrada na requisição seguinte |
| Reuso do cookie após logout | sessão inválida |
| `GET /api/fipe/models` sem sessão | 401 |
| Cabeçalho CSP | presente |
| Porta 3306 no host | não publicada |
| Sintaxe dos arquivos alterados | sem erros |

## IV.9 Pendências

1. Rotacionar a chave do provedor de IA no painel do fornecedor.
2. Migrar os scripts inline para CSP com nonce e remover `'unsafe-inline'`.
3. Incluir `composer audit` e a suíte do HexStrike no fluxo de integração contínua.
4. Repetir a varredura em um ambiente com HTTPS e proxy reverso antes de uma implantação
   real.

## IV.10 Como reproduzir

Os scripts ficam em `docs/seguranca/scripts/` e os resultados brutos em
`docs/seguranca/artefatos/`.

1. Subir o container do HexStrike e confirmar a API em `127.0.0.1:8888`.
2. Subir a aplicação com `docker compose up -d app` e confirmar o login em
   `http://127.0.0.1:8080/login`.
3. Executar `scan-hexstrike.sh` para rodar nmap, wafw00f, Nikto, Nuclei, gobuster e ffuf.
4. Conferir os arquivos `nmap.txt`, `nikto.txt`, `nuclei.txt`, `wafw00f.txt`,
   `gobuster.txt`, `ffuf.txt` e `headers.txt`.

A versão técnica, com os comandos e os detalhes de cada achado, está na página de
engenharia do site: https://torque-zenitec.pages.dev/#/engenharia/07-seguranca-ofensiva

## IV.11 Conclusão

O código tem controles de segurança acima da média para um projeto acadêmico. A varredura
dinâmica não encontrou injeção, XSS nem acesso cruzado entre perfis. O risco estava na
operação: credenciais padrão e banco exposto transformam um código seguro em um sistema
comprometível em minutos. Com as correções aplicadas, o risco residual cai para a rotação
da chave do provedor de IA e para a evolução da política de CSP.
