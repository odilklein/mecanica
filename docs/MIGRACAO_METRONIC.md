# Migração Vuexy → Metronic (Tailwind v9.5.0) — Análise e Plano

> Status: proposta (aguardando decisões). Data: 05/11/2026.

## 1. Situação atual (Vuexy)

| Item | Valor |
| --- | --- |
| Stack | PHP 8.2 procedural + PDO, MySQL 8.4, Apache (Docker) |
| Tema | Vuexy HTML (Bootstrap 5, layout vertical menu, webpack bundle) |
| CSS do tema | `assets/vendor/css/core.css` (bundle webpack com Bootstrap) + `assets/css/demo.css` |
| JS do tema | `assets/vendor/js/helpers.js`, `menu.js`, `bootstrap.js` |
| Vendors | Tabler Icons (iconify), bs-stepper, SweetAlert2, Flatpickr, Chart.js, DataTables-BS5 (só CSS) |
| Camada própria | `css/mecanica.css` (1.5k linhas, tokens `--bs-*` + componentes), `js/` (4.1k linhas), `includes/` (shell + builders de CRUD) |
| Páginas | 20 páginas PHP + 9 endpoints JSON de API (3.3k linhas de view) |
| Tema escuro | `data-bs-theme` no `<html>` + localStorage `mecanica-theme` (light/dark/system) |
| Ícones | 196 referências `tabler-*` em páginas/includes |
| jQuery | Não utilizado pelo app (o tema Metronic Tailwind usa jQuery internamente) |

### Inventário de componentes em uso (frequência em páginas/includes/api)

modais (143) · badges (38) · dropdowns (25) · avatares (22) · tabelas/table-responsive (9+) ·
toasts Bootstrap (11) · stepper/wizard (11) · abas nav-tabs (1) · collapse (3) · tooltips ·
pagination · alerts · empty-states (9) · quick-actions (5) · KPIs (stat-card/crud-kpi) ·
timeline · chips/sugestões · input-groups (16) · form-select (25) · form-check (14) ·
progress (4) · Chart.js (dashboard/relatórios) · SweetAlert2 (vendored, pouco usado)

### Arquitetura da UI

- `includes/header.php` — `<html>` + head + navbar (tema, notificações, usuário) + abre o conteúdo.
- `includes/sidebar.php` — menu vertical gerado por array `$navegacao` (com papéis).
- `includes/footer.php` — rodapé + scripts do tema + `main.js`, `toast.js`, `shell.js`, `crud.js`.
- `includes/crud.php` — builders PHP de markup (KPI, toolbar, bulk bar, tabela, paginação, empty state).
- `js/crud.js` — motor CRUD genérico AJAX (`window.CrudConfig` + `<template>` de linha + modal `data-crud`).
- `js/shell.js` — tema, menu vertical, tooltips, notificações (polling 30s).
- `js/toast.js` — `mostrarToast()` (Bootstrap Toast).
- `js/main.js` — utilidades de domínio (máscaras CPF/telefone/CEP, listas dinâmicas, ordenação, select-cards, filtros).
- `js/os-wizard.js` / `os-stepper.js` — wizard de nova OS (bs-stepper).
- `js/veiculo-fipe.js`, `viacep.js`, `relatorios.js` (Chart.js) — integrações.

## 2. O Metronic Tailwind v9.5.0 (packages em ~/fatec/)

| Package | Peso | Conteúdo |
| --- | --- | --- |
| `metronic-tailwind-html-demos` | 291 MB | `dist/` (css/js/media/vendors + html dos demos 1–10), `src/` (core TS, css, vendors), webpack, docs |
| `metronic-tailwind-html-starter-kit` | 63 MB | Scaffold mínimo para projeto próprio (`src/app`, `src/core` enxuto, webpack + vendors) |

### Fatos levantados

- **Zero Bootstrap.** É Tailwind utility + componentes próprios (`kt-*`). Nenhuma classe do Vuexy é reaproveitada — a migração é **reescrita de markup**, não troca de nomes de classes.
- **Layout**: `<html data-kt-theme="true" data-kt-theme-mode="light">`, `<body class="... kt-sidebar-fixed kt-header-fixed">`, `kt-sidebar` / `kt-header` / `kt-footer`, sidebar vira `kt-drawer` no mobile.
- **Tema escuro**: atributo `data-kt-theme-mode` + localStorage `kt-theme` (light|dark|system) — a lógica do `shell.js` será substituída pela do core do Metronic.
- **Componentes data-driven** (`core.bundle.js`): `data-kt-menu`, `data-kt-drawer`, `data-kt-toggle`, `data-kt-scrollable`, `data-kt-date-picker`, `data-kt-color-picker`, `data-kt-dropzone`, `data-kt-sortable`, etc. Inicializados via `KTComponents.init()`.
- **Vendors inclusos**: ApexCharts, DataTables (datatables-net), FormValidation, Dropzone, FullCalendar, TinyMCE, Clipboard, Leaflet, PrismJS, canvas-confetti, jQuery (dependência do core), **KeenIcons** (ícones `ki-filled`/`ki-outline`/`ki-duotone`), KTUI (KTDom, KTMenu, KTDropdown, KTSortable, KTData, KTDatePicker...).
- **Build**: requer Node.js. Webpack + PostCSS/Tailwind compilam `styles.css` a partir do `src/` (o `core.bundle.css` pré-compilado cobre os demos; o CSS do nosso projeto precisa ser compilado com o **content** apontando para os `.php` do sistema). **Não há Node/npm instalado na máquina.**
- **Ícones**: KeenIcons substitui Tabler. Precisa tabela de mapeamento `tabler-* → ki-*` (196 usos + dinâmicos no JS/sidebar).
- **Fontes**: Inter (hoje Public Sans).
- **Licença**: comercial (Keenthemes) — exigência de licença ativa; não versionar o pacote bruto no GitHub público sem atenção à licença (ver `LICENSE-NOTICE.txt`).

### Gaps mapeados (componentes atuais → destino Metronic)

| Atual (Vuexy/Bootstrap) | Destino (Metronic Tailwind) | Esforço |
| --- | --- | --- |
| `core.css` / Bootstrap grid | Tailwind utilities + `styles.css` compilado | Build novo |
| `helpers.js`/`menu.js`/`bootstrap.js` | `core.bundle.js` + `layouts/demo1.js` | Baixo (troca de includes) |
| `shell.js` (tema/menu) | `KTTheme` + data attributes | Baixo |
| `toast.js` (Bootstrap toast) | `kt-toast`/KTUI toast — manter wrapper `mostrarToast()` | Baixo |
| Ícones Tabler | KeenIcons `ki-*` | Médio (mapa + sed + revisão) |
| Modais Bootstrap (`modal fade`) | `kt-modal` + `data-kt-modal` | Médio (muitos modais) |
| Dropdowns Bootstrap | `data-kt-menu` (menu como dropdown) | Médio |
| Badges `badge bg-label-*` | `kt-badge kt-badge-outline-*`/sólidos | Médio |
| Avatares `avatar avatar-initial` | `kt-avatar kt-avatar-initial` | Baixo |
| Tabelas `table` | `kt-table`/DataTables (decisão abaixo) | Alto (todas as listagens) |
| bs-stepper (wizard OS) | Stepper Metronic (verificar no demo de plugins; caso não exista componente pronto, montar com `kt-*` + estado) | Alto |
| Chart.js (dashboard/relatórios) | ApexCharts | Médio |
| Alertas `alert alert-*` | `kt-alert`/pattern do Metronic | Baixo |
| Paginação própria | Pattern Metronic ou DataTables | Médio |
| `page-header`/`content-wrapper`/`card` | `kt-container`/`kt-card`/toolbars | Alto (todas as páginas) |
| Timeline | `kt-timeline` | Baixo |
| Login (`page-auth.css`) | Página de auth do Metronic | Baixo |

## 3. Estratégia de migração (por fases, cada uma entregável)

> Cada fase termina com o sistema funcionando (nunca quebrar o fluxo inteiro).

### Fase 0 — Preparação e spike
1. **Git**: conectar GitHub (seção 4), baseline. ✅ `gh` e identidade git prontos; falta só o device login.
2. **Node.js**: ✅ instalado (nvm, Node v20.20.2).
3. **Spike de build**: copiar `starter-kit` para `theme/` no projeto (ou usar o `demos` como fonte), `npm install`, apontar o content do Tailwind para `**/*.php` + `js/**`, compilar e servir. Validar 1 página crua com o shell Metronic.
4. **Referências**: extrair do `dist/html` os templates-base (shell, auth, tabela DataTables, formulário, modal, stepper) para servir de guia.
5. Definir tokens da marca (`#b8270f` etc.) no Tailwind config.

### Fase 0 — Andamento (registro técnico)

- ✅ Starter-kit copiado para `theme/` (dist 48 MB + src 15 MB; `theme/node_modules/` no `.gitignore`; artefatos `*.Zone.Identifier` excluídos e ignorados).
- ✅ GitHub conectado (repo privado `odilklein/mecanica`, branch `master`, histórico adotado localmente).
- ✅ Marca aplicada em `theme/src/css/config.ktui.css` (`--primary: #b8270f` light / `#ff5a3c` dark).
- ✅ `@source` adicionado em `theme/src/css/styles.css` apontando para o app (PHP/JS).
- ⚠️ **Fix**: o `package-lock.json` do Keenthemes referencia `@keenthemes/ktui` como link de monorepo (`../../../ktui/ktui`) — sem a pasta, o npm cria symlink quebrado. Solução: instalar do registry (`npm install @keenthemes/ktui`), que atualiza o lock.
- ✅ Build validado: `npm run build` (css via @tailwindcss/cli + js via webpack) gera `dist/assets/css/styles.css` (~450 KB) escaneando os `.php`/`.js` do app e aplicando a marca. Classes arbitrárias (`w-[300px]`, `max-w-[500px]`), fracionais (`gap-7.5`) e variantes (`hover:text-primary`, `lg:flex`) confirmadas no CSS.
- 📝 Dicas de debug (economizam tempo): (1) classes "sonda" precisam ser utilitárias válidas — `probe-xyz` é descartado pelo compilador por não casar com nenhuma utility; (2) no CSS final, seletores têm `:` e `/` escapados (`hover\:text-primary`, `bg-accent\/60`) — usar `grep -F`.
- ✅ Página spike: `theme/spike/index.php` (http://localhost:8080/theme/spike/) com shell completo (sidebar `data-kt-menu` + drawer mobile, header sticky com dropdowns, card, tabela `kt-table`, badges, modal `kt-modal`, alerta `kt-alert`, troca de tema light/dark/system via classe `.dark` no `<html>`).
- 📝 Classes corretas (diferem do Metronic clássico): dropdown item = `kt-dropdown-menu-link`; avatar com inicial = `kt-avatar-fallback`; tabelas = `kt-table` + utilitários Tailwind (nada de `fs-6`/`gy-5`/`fw-bold`).

### Fase 1 — Shell (header/sidebar/footer/tema)
- Reescrever `includes/header.php`, `sidebar.php`, `footer.php` com o esqueleto Metronic (menu `data-kt-menu`, drawer mobile, notificações, switch de tema light/dark/system).
- Trocar fontes (Public Sans → Inter); ícones Tabler mantidos (decisão 5).
- `shell.js` → inicialização Metronic (KTComponents, layout demo1); manter o polling de notificações.
- Login/perfil migrados junto (páginas "fora do shell").

### Fase 2 — Camada compartilhada
- Reescrever `includes/crud.php` (KPI → `kt-card` stats; toolbar; bulk bar; tabela; paginação; empty state) e `js/crud.js`/`toast.js` com seletores novos.
- **Camada DataTables server-side** (decisão 4): helper PHP de resposta + modo `draw` nos endpoints + inicializador DataTables.
- `css/mecanica.css` → utilitários Tailwind + componentes novos; remover dependência do Vuexy.

### Fase 3 — Páginas em lotes (por área)
1. Clientes, Veículos, Funcionários (CRUD simples).
2. Ordens de serviço (listagem, detalhes com abas/timeline, wizard de nova OS).
3. Pedidos de peças, Orçamentos (editor, detalhes, impressão, relatório com ApexCharts).
4. Notificações, Auditoria, Dashboard (ApexCharts).
5. Relatórios: Chart.js → ApexCharts.

### Fase 4 — Responsividade e polimento
- Auditoria mobile de todas as telas (tabelas, modais full-screen no mobile, wizard, KPIs).
- Acessibilidade (skip-link, foco, `aria-*`) preservada.
- Tema escuro revisado em todas as telas.

### Fase 5 — Limpeza e entrega
- Remover assets Vuexy (`assets/vendor`, `assets/css`, vendors antigos).
- Atualizar README/DOCUMENTACAO_SISTEMA.
- Screenshots novos; commit + push.

## 4. GitHub

- `gh` CLI **2.45 instalado** (apt). `git` configurado com `user.name=odilklein`, `user.email=99180711+odilklein@users.noreply.github.com` (noreply), branch padrão `main`.
- Conta pública `odilklein` (9 repositórios públicos); **não existe repositório público `mecanica`** na conta (404) — pode ser privado ou ainda não criado.
- **Decisão**: autenticar com `gh auth login` (device flow) — pendente de ação do usuário. Depois eu listo os repositórios (incluindo privados) e conecto o remote.
- Cuidado: verificar se o repo remoto já tem histórico antes de `git init` local (se tiver, clonar/adotar o clone para não perder nada).

## 5. Decisões fechadas

| # | Decisão | Impacto |
| --- | --- | --- |
| 1 | GitHub via **gh CLI + device login** | Pendente do usuário rodar `gh auth login` |
| 2 | Node.js via **nvm no usuário** | ✅ Feito: Node v20.20.2 + npm 10.8.2 |
| 3 | Assets: **starter-kit em `theme/` + dist commitado** | Apache serve direto; sem build no deploy; src (Tailwind config com nossa marca) também commitado |
| 4 | Tabelas: **DataTables server-side** (datatables-net do Metronic) | Reescrever `js/crud.js` e adaptar os 9 endpoints JSON para o protocolo DataTables (`draw`, `recordsTotal`, `recordsFiltered`, `data`); jQuery passa a ser usado nas listagens |
| 5 | Ícones: **manter Tabler** (iconify) | Sem mapeamento de 196 ícones; incluir o CSS do iconify junto dos assets Metronic |

### Consequências da decisão DataTables na estratégia

- **Fase 2** passa a incluir a camada DataTables: helper PHP genérico (`includes/datatables.php`) que monta a resposta a partir de query + filtros + paginação (reaproveita `includes/paginacao.php`).
- Os endpoints existentes (`api/clientes.php` etc.) ganham um modo DataTables (detecção por `$_GET['draw']`) em vez de endpoints novos — menor superfície.
- `crud.js` vira inicializador DataTables + handlers de linha (modais continuam com o `template` atual, reestilizado).
- Bulk actions, busca e filtros passam a ser os do DataTables (server-side), mantendo CSRF nos POSTs.

### Consequência da decisão de manter Tabler

- `assets/vendor/fonts/iconify-icons.css` (Tabler) é mantido e carregado junto do tema.
- Classes de ícone continuam `icon-base ti tabler-*` — sem sed em massa.

## 6. Riscos

- **Maior risco**: volume de reescrita de markup (20 páginas + includes) — mitigado por fases incrementais e baseline git.
- Toolchain Node (primeira vez no projeto) — spike na Fase 0. ✅ Node já instalado.
- DataTables server-side muda o contrato dos endpoints JSON — feito atrás de um modo `draw`, sem quebrar o CRUD antigo durante a transição.
- Stepper/alguns padrões custom (select-cards, status-pills) podem não ter componente 1:1 — serão recriados com utilitários Metronic.
- jQuery passa a existir como dependência do tema e das tabelas (não do restante do código da aplicação).
- Licença comercial do Metronic: não publicar o pacote completo no GitHub público.
