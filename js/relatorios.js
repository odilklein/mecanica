// =============================================
// RELATÓRIOS - GRÁFICOS (Chart.js)
// Lê os dados de window.RelatoriosConfig e as cores das
// variáveis CSS, recriando os gráficos quando o tema muda.
// =============================================
(function () {
    'use strict';

    var cfg = window.RelatoriosConfig;
    if (!cfg || typeof Chart === 'undefined') {
        return;
    }

    var instancias = {};
    var temaEscuro = document.body.classList.contains('dark');

    function varCss(nome, padrao) {
        var valor = getComputedStyle(document.body).getPropertyValue(nome);
        valor = (valor || '').trim();
        return valor || padrao;
    }

    function cores() {
        return {
            accent: varCss('--accent', '#b8270f'),
            accentLight: varCss('--accent-light', 'rgba(184, 39, 15, 0.08)'),
            success: varCss('--success', '#15803d'),
            warning: varCss('--warning', '#b45309'),
            danger: varCss('--danger', '#c81e2e'),
            info: varCss('--info', '#0369a1'),
            violet: varCss('--c-violet', '#7c3aed'),
            orange: varCss('--c-orange', '#c2410c'),
            amber: varCss('--c-amber', '#a16207'),
            teal: varCss('--c-teal', '#0f766e'),
            blue: varCss('--c-blue', '#1d4ed8'),
            purple: varCss('--c-purple', '#7e22ce'),
            green: varCss('--c-green', '#15803d'),
            gray: varCss('--c-gray', '#4b5563'),
            texto: varCss('--text-muted', '#6f767c'),
            grade: varCss('--border-color', '#e3e6ea'),
            superficie: varCss('--card-bg', '#ffffff')
        };
    }

    function moeda(valor) {
        return 'R$ ' + (Number(valor) || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function abreviarMoeda(valor) {
        var n = Number(valor) || 0;
        var abs = Math.abs(n);
        if (abs >= 1000000) return 'R$ ' + (n / 1000000).toFixed(1).replace('.', ',') + 'M';
        if (abs >= 1000) return 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k';
        return 'R$ ' + n.toFixed(0);
    }

    function formatar(valor, formato) {
        return formato === 'moeda' ? moeda(valor) : String(valor);
    }

    function resolver(token, c) {
        return c[token] || c.gray;
    }

    function resolverLista(tokens, c) {
        return tokens.map(function (token) { return resolver(token, c); });
    }

    function existe(id) {
        return !!document.getElementById(id);
    }

    function guardar(id, grafico) {
        if (instancias[id]) {
            instancias[id].destroy();
        }
        instancias[id] = grafico;
    }

    function legenda(c) {
        return {
            position: 'bottom',
            labels: {
                color: c.texto,
                boxWidth: 12,
                boxHeight: 12,
                padding: 14,
                usePointStyle: true,
                font: { family: "'Roboto', sans-serif", size: 11 }
            }
        };
    }

    function escala(c, formato) {
        return {
            grid: { color: c.grade, drawBorder: false },
            border: { display: false },
            ticks: {
                color: c.texto,
                font: { family: "'Roboto', sans-serif", size: 11 },
                maxRotation: 0,
                callback: function (valor) {
                    return formato === 'moeda' ? abreviarMoeda(valor) : valor;
                }
            }
        };
    }

    function opcoesBarra(c, formato) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c.superficie,
                    titleColor: c.texto,
                    bodyColor: varCss('--text-primary', '#171a1c'),
                    borderColor: c.grade,
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        label: function (ctx) {
                            return formatar(ctx.raw, formato);
                        }
                    }
                }
            },
            scales: {
                x: Object.assign({}, escala(c), { grid: { display: false } }),
                y: Object.assign({}, escala(c, formato), { beginAtZero: true })
            }
        };
    }

    function opcoesBarraHorizontal(c, formato, extras) {
        var opcoes = opcoesBarra(c, formato);
        opcoes.indexAxis = 'y';
        opcoes.scales = {
            x: Object.assign({}, escala(c, formato), { beginAtZero: true }),
            y: Object.assign({}, escala(c), { grid: { display: false } })
        };
        if (extras && extras.afterLabel) {
            opcoes.plugins.tooltip.callbacks.afterLabel = extras.afterLabel;
        }
        return opcoes;
    }

    function opcoesRosca(c) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: legenda(c),
                tooltip: {
                    backgroundColor: c.superficie,
                    titleColor: c.texto,
                    bodyColor: varCss('--text-primary', '#171a1c'),
                    borderColor: c.grade,
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function (ctx) {
                            var dados = ctx.dataset.data.map(Number);
                            var total = dados.reduce(function (a, b) { return a + b; }, 0);
                            var pct = total > 0 ? Math.round((Number(ctx.parsed) / total) * 100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        };
    }

    function corBorda(c) {
        return { borderColor: c.superficie, borderWidth: 2, hoverOffset: 6 };
    }

    function construir() {
        var c = cores();

        if (existe('chartFaturamento')) {
            var d = cfg.faturamento;
            guardar('chartFaturamento', new Chart(document.getElementById('chartFaturamento'), {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Faturado',
                        data: d.valores,
                        backgroundColor: c.accent,
                        hoverBackgroundColor: varCss('--accent-hover', '#9e1f0a'),
                        borderRadius: 6,
                        maxBarThickness: 44
                    }]
                },
                options: opcoesBarra(c, 'moeda')
            }));
        }

        if (existe('chartOrcamentosStatus')) {
            var d = cfg.orcamentosStatus;
            guardar('chartOrcamentosStatus', new Chart(document.getElementById('chartOrcamentosStatus'), {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.valores,
                        backgroundColor: resolverLista(d.cores, c),
                        hoverBackgroundColor: resolverLista(d.cores, c),
                        borderWidth: 2,
                        borderColor: c.superficie
                    }]
                },
                options: opcoesRosca(c)
            }));
        }

        if (existe('chartOsMes')) {
            var d = cfg.osMes;
            guardar('chartOsMes', new Chart(document.getElementById('chartOsMes'), {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Ordens abertas',
                        data: d.valores,
                        borderColor: c.info,
                        backgroundColor: varCss('--info-bg', 'rgba(3, 105, 161, 0.1)'),
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: c.info,
                        pointBorderColor: c.superficie,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: opcoesBarra(c, 'numero')
            }));
        }

        if (existe('chartOsStatus')) {
            var d = cfg.osStatus;
            guardar('chartOsStatus', new Chart(document.getElementById('chartOsStatus'), {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.valores,
                        backgroundColor: resolverLista(d.cores, c),
                        borderWidth: 2,
                        borderColor: c.superficie
                    }]
                },
                options: opcoesRosca(c)
            }));
        }

        if (existe('chartOsPrioridade')) {
            var d = cfg.osPrioridade;
            guardar('chartOsPrioridade', new Chart(document.getElementById('chartOsPrioridade'), {
                type: 'polarArea',
                data: {
                    labels: d.labels,
                    datasets: Object.assign({
                        data: d.valores,
                        backgroundColor: d.cores
                    }, corBorda(c))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: legenda(c),
                        tooltip: {
                            backgroundColor: c.superficie,
                            titleColor: c.texto,
                            bodyColor: varCss('--text-primary', '#171a1c'),
                            borderColor: c.grade,
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                label: function (ctx) {
                                    return ' ' + ctx.label + ': ' + ctx.parsed.r;
                                }
                            }
                        }
                    },
                    scales: {
                        r: {
                            grid: { color: c.grade },
                            angleLines: { color: c.grade },
                            ticks: {
                                color: c.texto,
                                backdropColor: 'transparent',
                                font: { size: 10 }
                            }
                        }
                    }
                }
            }));
        }

        if (existe('chartServicos')) {
            var d = cfg.servicos;
            guardar('chartServicos', new Chart(document.getElementById('chartServicos'), {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Execuções',
                        data: d.valores,
                        backgroundColor: c.success,
                        borderRadius: 6,
                        maxBarThickness: 26
                    }]
                },
                options: opcoesBarraHorizontal(c, 'numero', {
                    afterLabel: function (ctx) {
                        return 'Receita: ' + moeda(d.receita[ctx.dataIndex]);
                    }
                })
            }));
        }

        if (existe('chartPecas')) {
            var d = cfg.pecas;
            guardar('chartPecas', new Chart(document.getElementById('chartPecas'), {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Vendas',
                        data: d.valores,
                        backgroundColor: c.violet,
                        borderRadius: 6,
                        maxBarThickness: 26
                    }]
                },
                options: opcoesBarraHorizontal(c, 'numero', {
                    afterLabel: function (ctx) {
                        return 'Receita: ' + moeda(d.receita[ctx.dataIndex]);
                    }
                })
            }));
        }

        if (existe('chartClientes')) {
            var d = cfg.clientes;
            guardar('chartClientes', new Chart(document.getElementById('chartClientes'), {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Total gasto',
                        data: d.valores,
                        backgroundColor: c.amber,
                        borderRadius: 6,
                        maxBarThickness: 26
                    }]
                },
                options: opcoesBarraHorizontal(c, 'moeda')
            }));
        }

        if (existe('chartReceitaComposicao')) {
            var d = cfg.receitaComposicao;
            var opcoes = opcoesRosca(c);
            opcoes.plugins.tooltip.callbacks.label = function (ctx) {
                var dados = ctx.dataset.data.map(Number);
                var total = dados.reduce(function (a, b) { return a + b; }, 0);
                var pct = total > 0 ? Math.round((Number(ctx.parsed) / total) * 100) : 0;
                return ' ' + ctx.label + ': ' + moeda(ctx.parsed) + ' (' + pct + '%)';
            };
            guardar('chartReceitaComposicao', new Chart(document.getElementById('chartReceitaComposicao'), {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.valores,
                        backgroundColor: [c.info, c.accent],
                        borderWidth: 2,
                        borderColor: c.superficie
                    }]
                },
                options: opcoes
            }));
        }

        if (existe('chartMarcas')) {
            var d = cfg.marcas;
            guardar('chartMarcas', new Chart(document.getElementById('chartMarcas'), {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Atendimentos',
                        data: d.valores,
                        backgroundColor: c.teal,
                        borderRadius: 6,
                        maxBarThickness: 26
                    }]
                },
                options: opcoesBarraHorizontal(c, 'numero')
            }));
        }

        if (existe('chartPedidosStatus')) {
            var d = cfg.pedidosStatus;
            guardar('chartPedidosStatus', new Chart(document.getElementById('chartPedidosStatus'), {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.valores,
                        backgroundColor: resolverLista(d.cores, c),
                        borderWidth: 2,
                        borderColor: c.superficie
                    }]
                },
                options: opcoesRosca(c)
            }));
        }
    }

    function aoMudarTema() {
        var escuroAgora = document.body.classList.contains('dark');
        if (escuroAgora !== temaEscuro) {
            temaEscuro = escuroAgora;
            construir();
        }
    }

    construir();

    // Expõe as instâncias para que a página possa redimensionar os
    // gráficos ao exibir uma aba (canvases ocultos Renderizam com 0px).
    window.RelatoriosGraficos = instancias;

    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(aoMudarTema).observe(document.body, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
})();
