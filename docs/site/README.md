# Site da documentação — Torque

Documentação online do Projeto Integrador, construída com **React + Tailwind CSS + React Flow**.
O documento, os requisitos e o dicionário vêm dos Markdown do repositório; os diagramas são
recriados como grafos interativos.

## Rodar

O projeto não exige Node instalado: o script usa Docker (`node:22-alpine`).

```bash
# na raiz do repositório
bash docs/build/site.sh install   # instala as dependências (uma vez)
bash docs/build/site.sh dev       # http://localhost:5173
bash docs/build/site.sh build     # gera docs/site/dist
bash docs/build/site.sh preview   # serve o build em http://localhost:4173
bash docs/build/site.sh deploy    # publica em https://torque-zenitec.pages.dev
```

O `deploy` usa o wrangler com o login salvo em `~/.wrangler` (rode
`npx wrangler login` uma vez). O projeto `torque-zenitec` **não** está ligado ao
GitHub: um `git push` não publica o site, é preciso rodar o comando de deploy.

Se preferir Node local (22+):

```bash
cd docs/site
npm install
npm run dev
npm run build
```

## Como o conteúdo é montado

`scripts/prepare-content.mjs` roda antes do dev/build e:

1. lê `docs/entrega/*.md` e `docs/engenharia/*.md`;
2. extrai os blocos RF/RNF para `requirements` e troca os blocos por marcadores;
3. gera o sumário com os mesmos slugs do `rehype-slug`;
4. copia prints, logotipos e os downloads (PDF/DOCX) para `public/`;
5. escreve `src/content/generated.ts`.

O arquivo `src/content/generated.ts` é **gerado** e está no `.gitignore`. Para atualizar o
conteúdo, edite os Markdown e rode o build de novo.

## Estrutura

```
src/
  content/generated.ts        conteúdo gerado (não versionado)
  diagrams/                   definições dos 14 grafos (nós e arestas)
  lib/site.ts                 endereço público (Cloudflare Pages)
  components/
    diagram-nodes.tsx         ator, caso de uso, classe, entidade, componente, estado, lifeline
    MessageEdge.tsx           seta horizontal das sequências
    DiagramCanvas.tsx         React Flow + controles + minimapa + legenda
    DiagramMiniature.tsx      miniatura SVG usada no documento e na galeria
    MarkdownDocument.tsx      Markdown -> páginas, tabelas e figuras
  pages/                      Home, Documento, Diagramas, Engenharia
wrangler.toml                 configuração do Cloudflare Pages
public/_headers               cabeçalhos HTTP do Cloudflare Pages
```

## Tema e interação

- **Temas claro e escuro** alinhados à paleta do sistema (areia `#f6f0e7` no
  claro, preto `#09090b` no escuro, primária `#b8270f`) com toggle no cabeçalho (persistido em
  `localStorage`; respeita `prefers-color-scheme` na primeira visita).
- No documento, cada figura é um **preview real em React Flow** (sem interação). O clique
  abre um **modal** com o diagrama interativo, zoom, minimapa e legenda.
- O modal e a página do diagrama têm **tela cheia** (Fullscreen API) e link para a página
  completa.
- Os previews só montam o React Flow quando entram na viewport; até lá usam a miniatura SVG.

### Interações do diagrama

- **Arrastar** reposiciona os nós; o zoom e o minimapa continuam funcionando.
- **Passar o mouse** acende o nó e as ligações vizinhas e esmaece o resto. O cartão no
  topo mostra a descrição do componente.
- **Clicar** fixa os detalhes do nó; clicar no fundo limpa a seleção.
- **Animar** liga o movimento das ligações. Diagramas de arquitetura e de fluxo já
  começam animados; sequências ficam estáticas para não alterar o significado do tracejado.
- Cada nó aceita `description` em `src/diagrams/types.ts`. O texto aparece no cartão de
  detalhes e não muda o desenho do diagrama.

## Equipe

A seção “Quem fez acontecer” lê `src/data/members.ts`. Cada integrante tem `photo`,
`github`, `role`, `focus` e `contribution`. Se `photo` não for informado, o card mostra as
iniciais; se `github` não for informado, o link aponta para o repositório do projeto.

## Diagramas

Cada diagrama é um `DiagramDefinition` em `src/diagrams/` com `nodes`, `edges`, `figure`,
`category` e `legend`. Os nós usam os tipos registrados em `diagram-nodes.tsx`; as arestas
normais usam o tipo `default` e as mensagens de sequência usam `message`.

Para adicionar um diagrama:

1. crie a definição em `src/diagrams/` e exporte no `index.ts`;
2. use o mesmo nome do arquivo de imagem referenciado no Markdown
   (`diagramas/png/meu_diagrama.png` → id `meu_diagrama`);
3. a figura no documento passa a abrir `/diagramas/meu_diagrama` automaticamente.

## Deploy

O build é estático (`dist/`) e usa `HashRouter`, então funciona em qualquer hospedagem
sem regra de rewrite.

**Endereço público:** https://torque-zenitec.pages.dev

**Cloudflare Pages**

O projeto se chama `torque-zenitec`; o subdomínio `.pages.dev` é derivado desse nome. A
configuração está em [`wrangler.toml`](wrangler.toml):

| Campo | Valor |
|---|---|
| Projeto | `torque-zenitec` |
| Branch de produção | `main` |
| Diretório raiz | `docs/site` |
| Comando de build | `npm run build` |
| Diretório de saída | `dist` |
| Variável | `NODE_VERSION=22` (ou `.node-version` com `22`) |

No painel do Cloudflare: **Workers & Pages → Create → Pages → Connect to Git**, escolha o
repositório, informe o diretório raiz e os comandos acima. O `wrangler.toml` já declara o
nome e a pasta de saída. Se o nome `torque-zenitec` estiver em uso, troque-o em:

1. `wrangler.toml` (`name`);
2. `src/lib/site.ts` (`SITE_URL` e `SITE_HOST`);
3. `docs/build/build_docx.py` (`SITE_URL`);
4. `docs/README.md` e neste arquivo.

O workflow antigo de GitHub Pages continua em `.github/workflows/docs.yml` como
alternativa. Para desativá-lo, remova o arquivo ou a permissão de Pages do repositório.

> Os arquivos `docs/saida/PI-ES2-2026.pdf` e `docs/saida/PI-ES2-2026.docx` precisam estar
> versionados para os botões de download funcionarem. O `prepare-content` copia os dois
> para `public/downloads/`.

## Observações

- As cores seguem a paleta do sistema: vinho `#B8270F`, areia `#FBF7F1`, teal `#1F6F6B`,
  âmbar `#B7791F`, verde `#2F6F4E`.
- Os diagramas são responsivos; em telas pequenas use o zoom do React Flow.
- O documento oficial em DOCX/PDF continua em `docs/saida/` e é baixado pelo cabeçalho do site.
