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
```

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
  components/
    diagram-nodes.tsx         ator, caso de uso, classe, entidade, componente, estado, lifeline
    MessageEdge.tsx           seta horizontal das sequências
    DiagramCanvas.tsx         React Flow + controles + minimapa + legenda
    DiagramMiniature.tsx      miniatura SVG usada no documento e na galeria
    MarkdownDocument.tsx      Markdown -> páginas, tabelas e figuras
  pages/                      Home, Documento, Diagramas, Engenharia
```

## Tema e interação

- **Tema claro sépia** e **tema escuro** com toggle no cabeçalho (persistido em
  `localStorage`; respeita `prefers-color-scheme` na primeira visita).
- No documento, cada figura é um **preview real em React Flow** (sem interação). O clique
  abre um **modal** com o diagrama interativo, zoom, minimapa e legenda.
- O modal e a página do diagrama têm **tela cheia** (Fullscreen API) e link para a página
  completa.
- Os previews só montam o React Flow quando entram na viewport; até lá usam a miniatura SVG.

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

O build é estático (`dist/`) e usa `HashRouter`, então funciona em qualquer hospedagem,
inclusive GitHub Pages, sem regra de rewrite. O workflow
[`.github/workflows/docs.yml`](../../.github/workflows/docs.yml) publica em Pages a cada push
na branch principal.

Para habilitar: **Settings → Pages → Build and deployment → Source: GitHub Actions**.

> Os arquivos `docs/saida/PI-ES2-2026.pdf` e `docs/saida/PI-ES2-2026.docx` precisam estar
> versionados para os botões de download funcionarem no Pages. Se não estiverem, o site é
> publicado normalmente, mas os links de download ficam vazios.

## Observações

- As cores seguem a paleta do sistema: vinho `#B8270F`, areia `#FBF7F1`, teal `#1F6F6B`,
  âmbar `#B7791F`, verde `#2F6F4E`.
- Os diagramas são responsivos; em telas pequenas use o zoom do React Flow.
- O documento oficial em DOCX/PDF continua em `docs/saida/` e é baixado pelo cabeçalho do site.
