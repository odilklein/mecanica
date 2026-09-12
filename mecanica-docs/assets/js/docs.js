// ============================================================
// AUTO MECÂNICA LEME — Documentação do sistema (site separado)
// Renderiza topbar, sidebar, paginação e TOC; cuida do tema,
// busca, botões de copiar, highlight.js e diagramas Mermaid.
// ============================================================
(() => {
    'use strict';

    const DOC = {
        brand: 'Auto Mecânica Leme',
        sub: 'Documentação do sistema',
        note: 'Projeto Integrador · 2026',
        groups: [
            {
                label: 'Apresentação',
                items: [
                    { id: 'index', file: 'index.html', title: 'Visão geral', desc: 'O sistema em uma página', icon: 'layout-grid' },
                ],
            },
            {
                label: 'Produto',
                items: [
                    { id: 'regras-negocio', file: 'regras-negocio.html', title: 'Regras de negócio', desc: 'Como a oficina opera', icon: 'scale' },
                    { id: 'perfis', file: 'perfis.html', title: 'Perfis de acesso', desc: 'Quem faz o quê', icon: 'shield-check' },
                    { id: 'funcoes', file: 'funcoes.html', title: 'Funções do sistema', desc: 'Módulos e arquivos', icon: 'boxes' },
                ],
            },
            {
                label: 'Referência técnica',
                items: [
                    { id: 'banco-dados', file: 'banco-dados.html', title: 'Banco de dados', desc: 'Modelo e migrações', icon: 'database' },
                    { id: 'seguranca', file: 'seguranca.html', title: 'Segurança', desc: 'Proteções aplicadas', icon: 'lock' },
                    { id: 'instalacao', file: 'instalacao.html', title: 'Instalação', desc: 'Como rodar o sistema', icon: 'download' },
                    { id: 'convencoes', file: 'convencoes.html', title: 'Convenções', desc: 'Regras de evolução', icon: 'code-2' },
                ],
            },
        ],
    };

    const paginaAtual = document.body.dataset.page || 'index';
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    const icone = (nome) => `<i data-lucide="${nome}"></i>`;
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    // ---------- TOPBAR ----------
    const topbar = document.getElementById('topbar');
    topbar.innerHTML = `
        <button class="icon-btn hamburger" type="button" aria-label="Abrir menu">${icone('menu')}</button>
        <a class="brand" href="index.html" aria-label="Início da documentação">
            <span class="brand-mark">${icone('wrench')}</span>
            <span class="brand-text">
                <strong>${esc(DOC.brand)}</strong>
                <span>${esc(DOC.sub)}</span>
            </span>
        </a>
        <div class="search">
            ${icone('search')}
            <input type="search" id="docSearch" placeholder="Buscar na documentação…" aria-label="Buscar">
            <kbd>/</kbd>
        </div>
        <div class="topbar-actions">
            <button class="icon-btn" id="themeToggle" type="button" aria-label="Alternar tema">${icone('moon')}${icone('sun')}</button>
        </div>
    `;

    // Hamburger / scrim
    const scrim = document.createElement('div');
    scrim.className = 'scrim';
    document.body.appendChild(scrim);

    const hamburger = topbar.querySelector('.hamburger');
    hamburger.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    scrim.addEventListener('click', () => document.body.classList.remove('sidebar-open'));

    // ---------- SIDEBAR ----------
    const sidebar = document.getElementById('sidebar');
    let htmlGroups = '';
    DOC.groups.forEach((g, gi) => {
        const items = g.items
            .map((it) => `
                <a class="sidebar-link ${it.id === paginaAtual ? 'active' : ''}" href="${it.file}"
                   data-hit="${esc(it.title.toLowerCase())} ${esc(it.desc.toLowerCase())}"
                   title="${esc(it.title)}">
                    <span class="sl-icon">${icone(it.icon)}</span>
                    <span>
                        <span class="sl-label">${esc(it.title)}</span>
                        <span class="sl-desc">${esc(it.desc)}</span>
                    </span>
                </a>`)
            .join('');
        htmlGroups += `
            <div class="sidebar-group" data-group>
                <div class="sidebar-group-label">${esc(g.label)}</div>
                ${items}
            </div>`;
    });

    sidebar.innerHTML = `
        ${htmlGroups}
        <div class="sidebar-more">
            <a href="../mecanica/index.php" target="_blank" rel="noopener">${icone('arrow-up-right')} Ir ao sistema</a>
        </div>
        <p class="sidebar-empty">Nenhum resultado encontrado.</p>
    `;

    // ---------- BUSCA (filtra o menu) ----------
    const search = document.getElementById('docSearch');
    const linksNav = [...sidebar.querySelectorAll('.sidebar-link')];
    const groupsEl = [...sidebar.querySelectorAll('[data-group]')];

    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        document.body.classList.toggle('searching', q.length > 0);
        if (!q) return;
        linksNav.forEach((a) => {
            const hit = a.dataset.hit.includes(q);
            a.classList.toggle('hit', hit);
            a.setAttribute('aria-hidden', String(!hit));
        });
    });
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const first = sidebar.querySelector('.sidebar-link.hit');
            if (first) location.href = first.getAttribute('href');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
            e.preventDefault();
            search.focus();
        }
    });

    // ---------- Ícones Lucide ----------
    const refreshIcons = () => {
        if (window.lucide) window.lucide.createIcons();
    };

    // ---------- TEMA ----------
    const hljsLink = document.createElement('link');
    hljsLink.rel = 'stylesheet';
    hljsLink.id = 'docs-hljs-theme';
    document.head.appendChild(hljsLink);

    const syncHljsTheme = () => {
        const base = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/styles/';
        hljsLink.href = base + (isDark() ? 'github-dark.min.css' : 'github.min.css');
    };

    const applyTheme = () => {
        const saved = localStorage.getItem('docs-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = saved ? saved === 'dark' : prefersDark;
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        syncHljsTheme();
        if (window.__retina) window.__retina();
    };

    const themeBtn = document.getElementById('themeToggle');
    themeBtn.addEventListener('click', () => {
        const next = isDark() ? 'light' : 'dark';
        localStorage.setItem('docs-theme', next);
        applyTheme();
    });
    applyTheme();

    document.querySelectorAll('.icon-btn').forEach((b) => b.setAttribute('aria-label', b.id === 'themeToggle' ? 'Alternar tema' : 'Abrir menu'));

    // ---------- PAGINAÇÃO ----------
    const flat = DOC.groups.flatMap((g) => g.items);
    const idx = flat.findIndex((it) => it.id === paginaAtual);
    const pager = document.getElementById('pager');
    const linkPager = (item, dir) => `
        <a class="pager-link ${dir}" href="${item.file}">
            <small>${dir === 'next' ? 'Próxima' : 'Anterior'}</small>
            <strong>${esc(item.title)}</strong>
        </a>`;
    pager.innerHTML = `
        ${idx > 0 ? linkPager(flat[idx - 1], 'prev') : '<span class="pager-spacer"></span>'}
        ${idx < flat.length - 1 ? linkPager(flat[idx + 1], 'next') : '<span class="pager-spacer"></span>'}
    `;

    // ---------- Âncoras nos títulos ----------
    document.querySelectorAll('h2[id], h3[id]').forEach((h) => {
        const a = document.createElement('a');
        a.className = 'anchor';
        a.href = '#' + h.id;
        a.setAttribute('aria-label', h.id);
        a.innerHTML = '<i data-lucide="link"></i>';
        h.appendChild(a);
        refreshIcons();
    });

    // ---------- TOC (Nesta página) + scrollspy ----------
    const tocNav = document.getElementById('toc-nav');
    const headings = [...document.querySelectorAll('main.content h2[id], main.content h3[id]')];

    if (headings.length) {
        headings.forEach((h) => {
            const a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent.replace(/^\s*|\s*$/g, '');
            if (h.tagName === 'H3') a.classList.add('sub');
            tocNav.appendChild(a);
        });
        const links = [...tocNav.querySelectorAll('a')];
        const spy = new IntersectionObserver((entries) => {
            const visiveis = entries.filter((e) => e.isIntersecting).map((e) => e.target.id);
            if (!visiveis.length) return;
            const id = visiveis[visiveis.length - 1];
            links.forEach((l) => l.classList.toggle('active', l.getAttribute('href') === '#' + id));
        }, { rootMargin: '-72px 0px -68% 0px' });
        headings.forEach((h) => spy.observe(h));
    }

    // ---------- BOTÃO COPIAR ----------
    document.querySelectorAll('pre').forEach((bloco) => {
        if (bloco.classList.contains('mermaid')) return;
        if (bloco.querySelector('.code-copy')) return;

        const srcText = bloco.innerText.replace(/\n*$/, '');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'code-copy';
        btn.textContent = 'Copiar';
        btn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(srcText);
            } catch (e) {
                const ta = document.createElement('textarea');
                ta.value = srcText;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            btn.textContent = 'Copiado!';
            setTimeout(() => { btn.textContent = 'Copiar'; }, 1600);
        });
        bloco.appendChild(btn);
    });

    refreshIcons();

    // ---------- DIAGRAMAS (MERMAID) + HIGHLIGHT ----------
    (async () => {
        const codeBlocks = [...document.querySelectorAll('pre code[class*="language-"]')];
        if (codeBlocks.length) {
            try {
                const { default: hljs } = await import(
                    'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/es/highlight.min.js'
                );
                codeBlocks.forEach((b) => hljs.highlightElement(b));
            } catch (e) {
                console.warn('[docs] highlight.js não carregou:', e);
            }
        }

        const diagramas = [...document.querySelectorAll('pre.mermaid')];
        if (!diagramas.length) return;

        const fontes = new Map();
        diagramas.forEach((n) => fontes.set(n, n.textContent));

        let mermaid;
        let falhou = false;
        try {
            ({ default: mermaid } = await import(
                'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs'
            ));
        } catch (e) {
            console.warn('[docs] mermaid não carregou:', e);
            diagramas.forEach((n) => { n.textContent = 'Diagrama indisponível.'; });
        }

        const renderMermaid = async () => {
            if (!mermaid || falhou) return;
            mermaid.initialize({
                startOnLoad: false,
                securityLevel: 'loose',
                theme: isDark() ? 'dark' : 'default',
                fontSize: 14,
                themeVariables: { fontFamily: 'Inter, sans-serif' },
            });
            for (const n of diagramas) {
                if (falhou) return;
                n.textContent = fontes.get(n);
                try {
                    await mermaid.run({ nodes: [n] });
                } catch (e) {
                    falhou = true;
                    console.warn('[docs] falha em um diagrama:', e);
                    n.textContent = 'Não foi possível desenhar este diagrama.';
                    break;
                }
            }
        };

        window.__retina = () => {
            syncHljsTheme();
            renderMermaid();
        };

        await renderMermaid();
    })();
})();