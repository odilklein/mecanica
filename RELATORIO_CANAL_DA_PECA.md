# Relatório técnico e plano de integração — Canal da Peça

**Alvo:** `https://www.canaldapeca.com.br/`
**Data do scan:** 07/11/2026
**Objetivo:** verificar a existência de API pública/interna para busca de peças e definir a melhor forma de integrar a busca ao sistema Auto Mecânica.

---

## 1. Resumo executivo

1. **Não existe API pública de catálogo** para consumidores. A API oficial da plataforma (**CWS Platform / `ws.autorei.net`**) é B2B: estoque, preço, pedidos, clientes, contratos e orçamentos de varejistas/distribuidores. Exige credenciais de parceiro (sandbox sob solicitação) e **não** oferece busca de catálogo para uma oficina.
2. O site é **server-side rendering** sobre a plataforma **CWS Platform** (meta `generator = CWS Platform`). Não é SPA/Next.js; não há `__NEXT_DATA__`, Angular ou Nuxt.
3. O site expõe **endpoints JSON internos sem autenticação** que entregam exatamente o que a oficina precisa: busca estruturada, filtro por veículo (marca/modelo/ano), código de peça/OEM, preços e estoque por vendedor.
4. **Não é necessário Puppeteer/Playwright.** Os dados vêm em JSON (ou HTML já renderizado no servidor). Um cliente HTTP + parsing de JSON resolve; Cheerio/DOMDocument só é necessário para o endpoint de paginação que devolve fragmento HTML.
5. **Ponto de atenção jurídico:** o `robots.txt` proíbe `/busca`, `/autocomplete` e `/api/`. Os Termos de Uso proíbem reprodução do conteúdo sem autorização escrita e o uso de software para interferir no sistema. O caminho correto é **pedir autorização/parceria**; se o projeto seguir como scraping, deve ser de baixo volume, com cache, sem redistribuir o catálogo e sem contornar bloqueios.

**Recomendação:** construir um **proxy backend em PHP** (`api/pecas.php`) que consome os endpoints JSON, normaliza, cacheia e serve o front-end do sistema. Fazer contato formal com o Canal da Peça em paralelo (autorização + eventual feed/parceria) e manter o scraper isolado atrás de uma interface, para trocar por API oficial se ela for liberada.

---

## 2. Como o scan foi feito

- Requisições HTTP com `curl` e user-agent de navegador, sem cookies persistidos.
- Inspeção de `robots.txt`, sitemaps, HTML da home, da busca e da página de produto.
- Leitura dos bundles JS (`application-*.js`, `search-*.js`, `y-vehicle-*.js`) para descobrir as rotas AJAX.
- Testes funcionais dos endpoints encontrados (status, formato, paginação, filtros, volume e tempo de resposta).
- Teste de bloqueio: 15 requisições rápidas seguidas → todas `200`, sem captcha, sem WAF, sem `429`.

---

## 3. Infraestrutura observada

| Item | Observação |
| --- | --- |
| Plataforma | CWS Platform (`cws.digital`) |
| Protocolo | HTTP/2, HSTS, CSP `frame-ancestors` |
| Sessão | Cookie `SESSION` (shadow session), **não exigido** pelos endpoints de catálogo |
| CDN de assets | `assets.cws.digital` |
| Imagens | `images.cws.digital` (hotlink funciona, sem Referer obrigatório) |
| Anti-bot | Nenhum challenge observado (sem Cloudflare/Akamai/captcha) |
| Tempo de resposta | ~0,7 s a 1,5 s por requisição |
| Bloqueio por user-agent | Nenhum (testado `curl`, `python-requests`, sem UA) |
| Rate limit observado | Nenhum em 15 req. rápidas — **não significa que não exista**; assumir limite e usar backoff |

### Volume de catálogo (via sitemaps)

| Sitemap | URLs de produto |
| --- | ---: |
| `autopecas.xml.gz` | 26.304 |
| `componentes-industriais.xml.gz` | 5.905 |
| `acessorios-tuning.xml.gz` | 448 |
| `maquinas-ferramentas.xml.gz` | 243 |
| `oleos-fluidos.xml.gz` | 51 |
| `equipamentos-de-protecao-uniformes.xml.gz` | 4 |
| **Total** | **32.955** |

Cada sitemap traz `<loc>` e `<lastmod>` (datas até 2026-10), útil para sincronização incremental. Há também `categories.xml.gz` com 49 URLs de categoria.

---

## 4. Endpoints descobertos

> Todos testados em 07/11/2026. Nenhum exige autenticação. `BASE = https://www.canaldapeca.com.br`.

### 4.1 Busca estruturada (melhor opção) — `GET /searchProductsSuggestions`

```
GET /searchProductsSuggestions?q=filtro%20de%20oleo
GET /searchProductsSuggestions?q=filtro%20de%20oleo&page=2
GET /searchProductsSuggestions?q=amortecedor&vehicle=carro&make=volkswagen&model=gol&year=2005
GET /searchProductsSuggestions?q=amortecedor&brand=cofap
GET /searchProductsSuggestions?q=030115561AB
```

- **Formato:** JSON.
- **Retorno:** **6 SKUs por página** + `total`, `page`, `filters` (marcas, atributos, categorias), `matchLevel`.
- **Parâmetros confirmados:** `q`, `page`, `vehicle`, `make`, `model`, `year`, `brand`.
- **Busca por código OEM funciona** (ex.: `030115561AB` retorna 1 SKU).
- `limit`, `size`, `rows`, `perPage` são ignorados (fixo em 6).
- Paginação válida até a última página (testado `page=334` com 2004 resultados; `page=335` retorna 0).

**Campos por SKU:**

| Campo | Exemplo / observação |
| --- | --- |
| `id` | `1096523` (ID do SKU) |
| `brand` | `Wega` |
| `partCode` | `WO-341` (código do fabricante) |
| `name` / `nameSimple` | nome completo |
| `description` | descrição HTML |
| `assemblerCodes` | `{"Volkswagen":["030115561AB","030115561AD"]}` — **códigos OEM por montadora** |
| `applications` | `"IBIZA 1994/1997 - GOL 2000/2003 - PARATI 2000/2004"` |
| `category1..5` | `Motor e Partes` / `Filtro de Óleo Lubrificante` |
| `imageSet` | `baseUrl` + `paths[]` (múltiplas imagens) |
| `barcode`, `ncm`, `unitOfMeasure`, `minimumAmount`, `packageType` | dados fiscais/logísticos |
| `universal`, `mfrId`, `mfrWarranty`, `gridChildren` | metadados |
| `price` | **apenas flags** `byRequest` / `byBudget` — **não traz preço** |

> O preço não vem nesse endpoint. Ele é carregado separadamente por SKU (item 4.3).

### 4.2 Busca paginada + facetas — `GET /busca/addMore`

```
GET /busca/addMore?q=filtro+de+oleo&pathName=/busca&isInfiniteScroll=false&page=2&hasAggregations=true&isDynamicLoad=true
GET /busca/addMore?pathName=/busca/veiculo-carro/montadora-volkswagen/modelo-gol&page=2&year=2005
```

- **Formato:** JSON com **fragmentos HTML**.
- **Retorno:** `updates.content` (24 cards HTML), `pagination.content`, `sidebar.content` (facetas), `quantityFound`.
- **24 produtos/página** (vs. 6 do endpoint anterior).
- Paginação validada até o fim (ex.: 3.404 resultados → 142 páginas; `page=143` vazio).
- Usa o mesmo esquema de caminho da busca por veículo: `/busca/veiculo-{tipo}/montadora-{marca}/modelo-{modelo}?year={ano}`.

> É a rota usada pelo *infinite scroll* do site. Serve para varredura em massa, mas exige parser HTML (DOMDocument no PHP ou Cheerio no Node).

### 4.3 Preço e estoque por vendedor — `GET /sku/price`

```
GET /sku/price?skuId=1096523
GET /sku/price?skusIdList=1096523,4484300,4484553
GET /sku/price?skusIdList=...&zipcode=01310-100&city=Sao%20Paulo&state=SP&origin=search
```

- **Formato:** JSON.
- **Single:** `{ "<skuId>": { best, filtered_prices[], sellers, ... } }`.
- **Lote:** `skusIdList` com vírgulas (o plural correto é **`skusIdList`**, não `skuIds`). Testado com 48 SKUs em uma chamada → retorno dos 48.
- **Campos por oferta (`filtered_prices[]`):**

| Campo | Exemplo |
| --- | --- |
| `sellerName` | `Om Distribuidora` |
| `price` | `19.18` |
| `stockTotal` / `quantity` | `2` |
| `city` / `state` | `Londrina` / `PR` |
| `leadTime` / `packingTime` | dias |
| `partnersPartCode` | código do vendedor |
| `sellerType` | `SELLER` |
| `best` | melhor oferta consolidada (mesma estrutura) |

- Se o produto for "sob orçamento", pode não haver `best`/`filtered_prices`.
- CEP/cidade/estado influenciam frete/prazo; para preço puro, os parâmetros são opcionais.

### 4.4 Cascata de veículos — `GET /veiculos/...`

```
GET /veiculos                              -> tipos (Carro, Caminhão, Moto, Trator, Ônibus)
GET /veiculos/carro                        -> 102 marcas
GET /veiculos/carro/volkswagen             -> 59 modelos
GET /veiculos/carro/volkswagen/gol         -> 46 anos
```

- **Formato:** JSON (`items[]`, `filtered[]`, `total`).
- `filterName`: `vehicle` → `make` → `model` → `year`.
- O parâmetro `?t=<timestamp>` é usado pelo site como cache-buster; **não é obrigatório**.

### 4.5 Sugestões e autocomplete

| Endpoint | Formato | Uso |
| --- | --- | --- |
| `GET /searchSuggestions?q=filtro` | JSON `[{value}]` | sugestões de termo |
| `GET /autocomplete?term=filtro` | JSON `{suggestions:[{value}]}` | autocomplete do header |

> Ambos listados como `Disallow` no `robots.txt`.

### 4.6 Página de produto — `GET /p/{sku}/{slug}`

- HTML server-side com `schema.org/Product` (`sku`, `name`, `brand`, `offers`), breadcrumb, imagens e tabela **"Informações técnicas"**.
- Exemplo de dados extraídos: `Marca`, `Código do Produto`, `Rosca`, `Modelos`, `Altura`, `Juntas`, `Conteúdo`, `Diâmetro Externo`, `Válvulas`, `Código da Montadora`.
- Aplicações vêm em um config JS (`colProductApplcs`) com separador `-||-`, no formato `MARCA MODELO ANO | VERSÃO`.
- `/p/{id}` sem slug → `404`; `/p/{id}/{slug-errado}` → `301` para o slug canônico. **Sempre usar o link retornado pela busca.**
- Preços **não** vêm no HTML (templates Handlebars preenchidos via AJAX).

### 4.7 Sitemaps

```
GET /sitemap.xml
GET /sitemap/{categoria}.xml.gz
```

Lista completa de URLs de produto com `lastmod`. Melhor caminho para carga inicial e sincronização incremental do catálogo.

---

## 5. Matriz de conformidade

| Recurso | `robots.txt` | Termos de Uso | Leitura |
| --- | --- | --- | --- |
| `/busca`, `/busca/`, `/busca/*?*` | **Disallow** | reprodução proibida | evitar scraping direto da busca |
| `/autocomplete` | **Disallow** | — | evitar |
| `/api/` | **Disallow** | — | não usar |
| `/orcamento-express`, `/vendedores`, `/carrinho`, `/checkout` | **Disallow** | — | não usar |
| `/p/{sku}/{slug}` | Permitido | reprodução proibida | uso pontual, com atribuição |
| `/searchProductsSuggestions` | Não listado | reprodução proibida | **zona cinzenta** — pedir autorização |
| `/sku/price` | Não listado | reprodução proibida | **zona cinzenta** — pedir autorização |
| `/veiculos/...` | Não listado | — | zona cinzenta |
| `/sitemap*` | Não listado | — | ok para descoberta |

**Termos de Uso (trechos relevantes):**
- "O uso indevido e a reprodução total ou parcial dos referidos conteúdos são proibidos, salvo com a autorização expressa e escrita do CANAL DA PEÇA."
- "não utilizar nenhum dispositivo, software, ou outro recurso para interferir nas atividades e operações do SISTEMA."
- O acesso pode ser **bloqueado** em caso de suspeita de mau uso.

**LGPD:** o catálogo não envolve dados pessoais. Não scrapear área logada, carrinho, checkout ou dados de clientes.

**Conclusão prática:** tecnicamente é simples; juridicamente o uso comercial continuado deve ser formalizado. O plano abaixo assume **autorização em andamento** e um MVP de baixo volume para avaliação interna.

---

## 6. API oficial (alternativa "correta")

| Item | Detalhe |
| --- | --- |
| Documentação | https://api-docs.cws.digital/ e https://www.cws-platform.com/desenvolvedores/ |
| Base sandbox | `https://api.sandbox.cws.digital` (docs antigas: `ws.sandbox.autorei.net`) |
| Base produção | `https://ws.autorei.net` |
| Auth | OAuth2 `client_credentials` → `access_token` (12 h) → `Authorization: Bearer` |
| Escopo | 13 grupos / 78 endpoints: estoque e preço, pedidos, clientes, limite de crédito, contratos, regra de preço, **orçamentos**, carrinho personalizado, módulo vendedor, prioridade de SKU, cashback, webhooks |
| Credenciais | solicitar ao suporte (`suporte-api@cws.digital`) |
| Limitação para a oficina | **não há endpoint público de busca de catálogo**; a API serve para quem vende/gerencia estoque |

**Caminhos institucionais possíveis:**
- **Clube Canal da Peça** (`clube.canaldapeca.com.br`) — para varejistas/distribuidores/oficinas venderem e integrarem estoque. Pode render um feed de preços/estoque.
- **Parceria comercial / afiliado** — solicitar autorização escrita e, se possível, feed/API de catálogo.
- Contato: `consumidor@canaldapeca.com.br` (atendimento) e `suporte-api@cws.digital` (API).

---

## 7. Riscos e limitações do scraping

| Risco | Impacto | Mitigação |
| --- | --- | --- |
| Bloqueio por IP/rate limit | scraper para de funcionar | cache agressivo, 1 req/s, backoff em 403/429, UA identificável |
| Mudança de HTML/rotas | parser quebra | contrato de saída + testes de fumaça diários; preferir endpoints JSON |
| Bloqueio jurídico | risco legal/reputacional | autorização escrita, uso interno, sem redistribuir conteúdo |
| Preço desatualizado | orçamento errado | TTL curto para preço (5–10 min) e exibir "consultado em" |
| Produto sob orçamento | sem preço | tratar `byRequest`/`byBudget` na UI |
| Disponibilidade regional | estoque varia por CEP | consultar com CEP da oficina e mostrar vendedor/cidade |
| Dependência externa | site fora do ar | fallback "busca manual" + cache |

---

## 8. Plano de implementação

### Fase 0 — Autorização e escopo (1–3 dias, paralelo)

- Enviar e-mail formal ao Canal da Peça descrevendo: uso interno da oficina, volume estimado, endpoints, cache, ausência de redistribuição.
- Definir escopo: categorias (Autopeças + Óleos & Fluidos), CEP de referência, frequência de atualização.
- **Critério de saída:** autorização registrada ou decisão consciente de operar em modo avaliação de baixo volume.

### Fase 1 — Proxy backend em PHP (2–3 dias)

Criar `api/pecas.php` (mesmo padrão de `api/fipe.php`: sessão + JSON), com ações:

| Ação | Endpoint interno | Fonte externa |
| --- | --- | --- |
| `buscar` | `GET api/pecas.php?acao=buscar&q=...&pagina=1` | `/searchProductsSuggestions` |
| `buscar_veiculo` | `...&acao=buscar&veiculo=carro&marca=...&modelo=...&ano=...` | idem |
| `detalhe` | `...?acao=detalhe&sku=1096523` | `/p/{sku}/{slug}` (HTML) |
| `precos` | `...?acao=precos&skus=1,2,3` | `/sku/price?skusIdList=` |
| `veiculos` | `...?acao=veiculos&tipo=carro&marca=...&modelo=...` | `/veiculos/...` |
| `sincronizar` | CLI/cron | sitemaps + `/p/...` |

**Requisitos técnicos:**
- cURL com `CURLOPT_ENCODING` (gzip), timeout 20 s, retry 1x com backoff.
- Cache em arquivo/`APCu`/tabela: busca 10 min, preço 5 min, metadados 24 h, árvore de veículos 24 h.
- Normalização para um contrato único (ver §9).
- Rate limit interno (1 req/s) e log de erros.
- Não expor URLs externas cruas ao front; sempre servir o JSON normalizado.

### Fase 2 — Integração na UI (2–3 dias)

- Botão **"Buscar peça"** no wizard de OS e em `pages/pedidos_pecas/listar.php`.
- Modal com:
  - campo de texto (nome ou código OEM);
  - cascata Veículo → Marca → Modelo → Ano (reutiliza o padrão de `js/veiculo-fipe.js`);
  - lista de resultados com imagem, marca, código, aplicação e preço "a partir de";
  - botão "Adicionar ao pedido" → grava em `pedido_pecas_itens` (`descricao`, `quantidade`, `custo_unitario`, `observacoes` com SKU/fornecedor/link).
- Exibir sempre **vendedor, cidade/UF, prazo e data/hora da consulta**.

### Fase 3 — Cache persistente e sincronização (3–5 dias)

Tabelas sugeridas:

```sql
CREATE TABLE cdp_produtos (
  sku_id BIGINT PRIMARY KEY,
  marca VARCHAR(120),
  codigo_fabricante VARCHAR(80),
  nome VARCHAR(255),
  descricao TEXT,
  categoria VARCHAR(120),
  aplicacoes TEXT,
  codigos_oem JSON,
  imagens JSON,
  url VARCHAR(255),
  atualizado_em DATETIME
);

CREATE TABLE cdp_ofertas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku_id BIGINT NOT NULL,
  vendedor VARCHAR(160),
  cidade VARCHAR(120),
  uf CHAR(2),
  preco DECIMAL(10,2),
  estoque INT,
  prazo_dias INT,
  consultado_em DATETIME,
  INDEX (sku_id)
);

CREATE TABLE cdp_veiculos (
  tipo VARCHAR(40), marca VARCHAR(80), modelo VARCHAR(80), ano SMALLINT,
  PRIMARY KEY (tipo, marca, modelo, ano)
);
```

- Carga inicial via sitemaps (32.955 URLs) com fila e concorrência baixa.
- Atualização incremental por `lastmod`.
- Refresh de preços dos SKUs "quentes" (usados em pedidos/orçamentos) a cada 10 min; catálogo completo semanal.

### Fase 4 — Robustez e observabilidade (2 dias)

- Testes de contrato (schemas JSON) rodando 1x/dia.
- Alertas: taxa de erro > 5%, mudança de schema, resposta vazia inesperada.
- Fallback: se o provedor falhar, UI mostra "busca indisponível" e mantém cache.
- Feature flag `modulo_canal_peca` (seguir padrão `config_flag` do projeto).

### Fase 5 — Troca para API oficial (quando disponível)

- Manter `CanalDaPecaProvider` atrás de uma interface (`buscar`, `precos`, `detalhe`).
- Se surgir API/feed autorizado, implementar `CwsApiProvider` e trocar por configuração, sem mexer na UI.

### Estimativas

| Fase | Esforço |
| --- | --- |
| 0 — autorização/escopo | 1–3 dias (paralelo) |
| 1 — proxy PHP | 2–3 dias |
| 2 — UI | 2–3 dias |
| 3 — cache + sync | 3–5 dias |
| 4 — robustez | 2 dias |
| **MVP (fases 1–2)** | **~1 semana** |
| **Completo (1–4)** | **~3 semanas** |

---

## 9. Arquitetura proposta

```
┌──────────────────────────────┐
│ Front-end (Bootstrap/JS)     │
│ modal de busca de peças      │
└──────────────┬───────────────┘
               │ JSON interno (sessão + CSRF)
┌──────────────▼───────────────┐
│ api/pecas.php                │
│ - normaliza                  │
│ - cache (10min/5min/24h)     │
│ - rate limit + retry         │
└──────────────┬───────────────┘
               │ HTTPS
┌──────────────▼───────────────┐
│ canaldapeca.com.br           │
│ /searchProductsSuggestions   │
│ /sku/price                   │
│ /veiculos/...                │
│ /p/{sku}/{slug}              │
└──────────────────────────────┘
        ▲
        │ cron (sitemaps + refresh)
┌───────┴──────────────────────┐
│ cdp_produtos / cdp_ofertas   │
│ cdp_veiculos                 │
└──────────────────────────────┘
```

**Contrato de saída normalizado (exemplo):**

```json
{
  "consulta": "filtro de oleo",
  "pagina": 1,
  "total": 3404,
  "resultados": [
    {
      "sku": "1096523",
      "nome": "Filtro de Óleo - Wega - WO-341 - Unitário",
      "marca": "Wega",
      "codigo": "WO-341",
      "codigos_oem": {"Volkswagen": ["030115561AB", "030115561AD", "030115561AN"]},
      "aplicacao": "IBIZA 1994/1997 - GOL 2000/2003 - PARATI 2000/2004",
      "categoria": "Motor e Partes > Filtro de Óleo Lubrificante",
      "imagem": "https://images.cws.digital/produtos/m/23/65/filtro-de-oleo-1096523-1747352004011.jpeg",
      "url": "https://www.canaldapeca.com.br/p/1096523/filtro-de-oleo-wega-wo-341-unitario",
      "preco": {
        "a_partir_de": 18.35,
        "moeda": "BRL",
        "vendedor": "MAUCOR LUBRIFICANTES",
        "cidade": "São José",
        "uf": "SC",
        "estoque": 2,
        "prazo_dias": 2,
        "ofertas": 3,
        "consultado_em": "2026-11-07T21:45:00-03:00"
      }
    }
  ]
}
```

---

## 10. PoC — como consumir

### 10.1 cURL (validado)

```bash
# 1) Busca estruturada (6 por página)
curl -sG 'https://www.canaldapeca.com.br/searchProductsSuggestions' \
  --data-urlencode 'q=filtro de óleo' \
  --data 'page=1'

# 2) Busca por veículo
curl -sG 'https://www.canaldapeca.com.br/searchProductsSuggestions' \
  --data-urlencode 'q=amortecedor' \
  --data 'vehicle=carro' --data 'make=volkswagen' \
  --data 'model=gol' --data 'year=2005'

# 3) Busca por código OEM
curl -sG 'https://www.canaldapeca.com.br/searchProductsSuggestions' \
  --data-urlencode 'q=030115561AB'

# 4) Preço/estoque em lote (até 50+ por chamada)
curl -s 'https://www.canaldapeca.com.br/sku/price?skusIdList=1096523,4484300,4484553'

# 5) Cascata de veículos
curl -s 'https://www.canaldapeca.com.br/veiculos/carro/volkswagen/gol'

# 6) Página de produto (HTML + schema.org)
curl -s 'https://www.canaldapeca.com.br/p/1096523/filtro-de-oleo-wega-wo-341-unitario'
```

### 10.2 Cliente PHP (esqueleto pronto para `src/`)

```php
<?php
// src/Modules/Pecas/CanalDaPecaClient.php
final class CanalDaPecaClient
{
    private const BASE = 'https://www.canaldapeca.com.br';

    public function __construct(private string $cacheDir)
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    /** Busca estruturada: 6 itens/página. */
    public function buscar(string $termo, array $filtros = [], int $pagina = 1): array
    {
        $qs = array_filter(array_merge([
            'q' => $termo,
            'page' => max(1, $pagina),
        ], $filtros), static fn ($v) => $v !== null && $v !== '');

        return $this->getJson('/searchProductsSuggestions?' . http_build_query($qs), 600);
    }

    /** Preços/estoque em lote. */
    public function precos(array $skus, array $localizacao = []): array
    {
        $skus = array_values(array_unique(array_map('intval', $skus)));
        $saida = [];

        foreach (array_chunk($skus, 50) as $lote) {
            $qs = array_merge([
                'skusIdList' => implode(',', $lote),
                'origin' => 'search',
            ], $localizacao);

            $saida += $this->getJson('/sku/price?' . http_build_query($qs), 300);
        }

        return $saida;
    }

    /** Cascata veículo -> marca -> modelo -> ano. */
    public function veiculos(string $tipo = '', string $marca = '', string $modelo = ''): array
    {
        $path = '/veiculos';
        foreach ([$tipo, $marca, $modelo] as $parte) {
            if ($parte !== '') {
                $path .= '/' . rawurlencode($parte);
            }
        }

        return $this->getJson($path, 86400);
    }

    private function getJson(string $path, int $ttl): array
    {
        $arquivo = rtrim($this->cacheDir, '/') . '/' . sha1($path) . '.json';

        if (is_file($arquivo) && (time() - filemtime($arquivo)) < $ttl) {
            $cache = json_decode((string) file_get_contents($arquivo), true);
            if (is_array($cache)) {
                return $cache;
            }
        }

        $ch = curl_init(self::BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'AutoMecanica/1.0 (uso interno; contato@oficina)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $corpo = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($corpo === false || $status >= 400) {
            throw new RuntimeException("Canal da Peça: HTTP {$status} {$erro}");
        }

        $dados = json_decode((string) $corpo, true);
        if (!is_array($dados)) {
            throw new RuntimeException('Canal da Peça: resposta inválida');
        }

        file_put_contents($arquivo, json_encode($dados, JSON_UNESCAPED_UNICODE));

        return $dados;
    }
}
```

### 10.3 Node + Cheerio (somente se for microserviço à parte)

```js
// npm i undici cheerio
import { request } from 'undici';
import * as cheerio from 'cheerio';

const BASE = 'https://www.canaldapeca.com.br';

export async function buscarHtml(page = 1, q = 'filtro de oleo') {
  const url = `${BASE}/busca/addMore?` + new URLSearchParams({
    q,
    pathName: '/busca',
    isInfiniteScroll: 'false',
    page: String(page),
    hasAggregations: 'false',
    isDynamicLoad: 'true',
  });

  const { body } = await request(url, {
    headers: { 'user-agent': 'AutoMecanica/1.0', accept: 'application/json' },
  });
  const json = await body.json();
  const $ = cheerio.load(json.updates.content);

  const itens = $('[itemprop="sku"]').map((_, el) => {
    const card = $(el).closest('.y-item');
    return {
      sku: $(el).attr('content'),
      nome: card.find('[itemprop="name"]').attr('content'),
      url: card.find('[itemprop="url"]').attr('href'),
      codigo: card.find('.produto-mfrPartCode').text().replace('Cód.', '').trim(),
    };
  }).get();

  return { total: json.quantityFound, itens };
}
```

> **Observação:** Cheerio/Puppeteer só são necessários para o endpoint `addMore` (HTML) ou se o site passar a renderizar no cliente. Para o MVP, `searchProductsSuggestions` + `sku/price` em PHP já cobrem busca, preço e estoque.

---

## 11. Testes de aceite do MVP

- [ ] Busca por nome retorna resultados normalizados e pagina corretamente.
- [ ] Busca por código OEM (`030115561AB`) encontra o SKU correto.
- [ ] Filtro veículo → marca → modelo → ano retorna apenas aplicações compatíveis.
- [ ] Preço em lote de 50 SKUs retorna todos, com vendedor/UF/prazo.
- [ ] Cache evita chamadas repetidas (medir hit rate).
- [ ] Falha do provedor não derruba a OS (fallback para busca manual).
- [ ] Nenhum dado sensível/token exposto no front.
- [ ] Logs com status, latência e SKUs consultados.
- [ ] Revisão jurídica registrada (autorização ou decisão formal).

---

## 12. Decisão recomendada

| Opção | Esforço | Qualidade | Risco | Veredito |
| --- | --- | --- | --- | --- |
| **Scraping dos endpoints JSON internos** | baixo | alta | jurídico/mudança | **MVP interno agora**, com autorização em paralelo |
| **Playwright/Puppeteer** | alto | alta | alto | desnecessário — site é SSR |
| **API oficial CWS** | médio | altíssima | baixo | não cobre busca de catálogo; útil se virar parceiro |
| **Parceria/feed autorizado** | médio | alta | baixo | **melhor destino de longo prazo** |

**Próximo passo sugerido:** aprovar a Fase 1 (proxy PHP) e, em paralelo, disparar o contato de autorização. Se a autorização vier com feed/API, a Fase 3 é substituída pela ingestão do feed, mantendo o mesmo contrato interno.

---

## Anexo — Evidências coletadas no scan

- `robots.txt` com `Disallow: /busca`, `/busca/`, `/busca/*?*`, `/autocomplete`, `/api/`, `/skuCarousel`, `/orcamento-express`, `/vendedores`.
- `generator = CWS Platform`.
- `/searchProductsSuggestions?q=filtro de oleo` → `total: 3404`, 6 SKUs, campos `assemblerCodes`/`applications` preenchidos.
- `/busca/addMore?...&page=142` → 20 SKUs; `page=143` → 0 (fim da paginação).
- `/sku/price?skusIdList=` com 48 IDs → 48 ofertas em uma resposta.
- `/veiculos/carro` → 102 marcas; `/veiculos/carro/volkswagen` → 59 modelos; `/veiculos/carro/volkswagen/gol` → 46 anos.
- Sitemaps: 32.955 URLs de produto no total.
- 15 requisições rápidas → 15 × `200`, sem captcha/WAF.
- Termos de Uso: reprodução proibida sem autorização escrita; uso de software para interferir proibido; bloqueio de acesso previsto.
