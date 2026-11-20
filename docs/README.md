# Documentação do Projeto Integrador — Torque

Sistema de gestão para oficinas mecânicas desenvolvido como Projeto Integrador do
curso de Desenvolvimento de Software Multiplataforma (DSM) da FATEC Araras.

| Campo | Valor |
|---|---|
| Empresa | Team Zenitec |
| Cliente | Auto Mecânica Leme |
| Sistema | Torque — Sistema de Gestão |
| Repositório | https://github.com/odilklein/mecanica |
| Apresentação | 24/11/2026 |

## Como esta pasta está organizada

A documentação tem **duas camadas**, com papéis diferentes:

```
docs/
  _template/     arquivo oficial do professor (não editar)
  engenharia/    referência técnica, extraída do código e do banco
  diagramas/     fontes dos diagramas + imagens renderizadas
  imagens/       capturas de tela e logotipos
  entrega/       texto acadêmico, uma seção por arquivo do template
  site/          documentação online em React + Tailwind + React Flow
  build/         scripts que montam o DOCX, o PDF e o site
  saida/         documentos finais (gerados)
```

### 1. `engenharia/` — referência técnica

Não é a entrega. É o material de consulta usado para escrever o documento
oficial. Tudo aqui foi levantado do código em execução e do banco `mecanica`
(MySQL 8.4), não de suposições.

| Arquivo | Conteúdo |
|---|---|
| [`engenharia/01-stack.md`](engenharia/01-stack.md) | Tecnologias e versões reais |
| [`engenharia/02-requisitos.md`](engenharia/02-requisitos.md) | RF, RNF e rastreabilidade com o código |
| [`engenharia/03-regras-negocio.md`](engenharia/03-regras-negocio.md) | Regras de negócio e fluxos de status |
| [`engenharia/04-modelo-dados.md`](engenharia/04-modelo-dados.md) | Dicionário de dados (gerado do schema) |
| [`engenharia/05-arquitetura.md`](engenharia/05-arquitetura.md) | Arquitetura em camadas e ciclo da requisição |
| [`engenharia/06-integracoes.md`](engenharia/06-integracoes.md) | ViaCEP, FIPE, Canal da Peça, Groq e Turnstile |
| [`engenharia/adr/`](engenharia/adr/) | Registros de decisão de arquitetura (ADRs) |

### 2. `entrega/` — documento oficial

Cada arquivo corresponde a uma seção do template do professor, na mesma ordem:

| Seção do template | Arquivo |
|---|---|
| Capa e ficha de controle | [`entrega/00-identificacao.md`](entrega/00-identificacao.md) |
| 1. Apresentação da Empresa | [`entrega/01-empresa.md`](entrega/01-empresa.md) |
| 2. Escopo do Sistema | [`entrega/02-escopo.md`](entrega/02-escopo.md) |
| 3. Documentação do Sistema | [`entrega/03-sistema.md`](entrega/03-sistema.md) |
| 4. Testes e Qualidade | [`entrega/04-testes.md`](entrega/04-testes.md) |
| 5. Considerações Finais | [`entrega/05-consideracoes.md`](entrega/05-consideracoes.md) |
| Referências | [`entrega/referencias.md`](entrega/referencias.md) |
| Anexos I a III | [`entrega/anexos/`](entrega/anexos/) |

## Site da documentação

O site lê os mesmos Markdown de `entrega/` e `engenharia/` e recria os 14 diagramas em
React Flow. As figuras do documento abrem o diagrama interativo correspondente.

```bash
bash docs/build/site.sh install   # uma vez
bash docs/build/site.sh dev       # http://localhost:5173
bash docs/build/site.sh build     # gera docs/site/dist
```

O deploy em GitHub Pages está em `.github/workflows/docs.yml` (Pages → Source:
GitHub Actions). Detalhes em [`site/README.md`](site/README.md).

## Como gerar o documento

```bash
# 1. diagramas (PlantUML e D2 via Docker)
docs/build/render.sh diagramas

# 2. DOCX + PDF
docs/build/render.sh documento
```

O resultado fica em `docs/saida/`:

- `PI-ES2-2026.docx` — documento para entrega
- `PI-ES2-2026.pdf` — versão para conferência

### Regras do build

1. O DOCX é montado **dentro** de `_template/template-fatec.docx`, preservando
   capa, ficha de controle, cabeçalho, logotipo da FATEC e estilos.
2. O logotipo do cabeçalho é o da FATEC e **não** é substituído.
3. Nenhum texto do documento é escrito no script: todo o conteúdo vem de
   `entrega/*.md`.
4. Os diagramas são gerados a partir das fontes em `diagramas/fontes/`.
5. O sumário é recalculado em duas passagens (gera → mede as páginas → preenche).

## Dados de demonstração

A base usada nas capturas de tela é fictícia. Nomes, CPF, placas e valores não
correspondem a pessoas reais.
