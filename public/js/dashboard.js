/**
 * Painel principal — gráficos (ApexCharts) no padrão do dashboard CRM do sistema.
 * Os dados chegam do servidor em window.dashboardData.
 */
'use strict';
(function () {
    const data = window.dashboardData;
    if (!data || typeof ApexCharts === 'undefined') {
        return;
    }

    const FONT_FAMILY = "'Public Sans', sans-serif";
    const intFmt = new Intl.NumberFormat('pt-BR');
    const moneyFmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    const mainCharts = [];
    const tabCharts = {};

    /** Lê uma variável CSS do tema atual como cor concreta para o ApexCharts. */
    function cssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue('--bs-' + name).trim();
        return value || fallback;
    }

    function palette() {
        return {
            primary: cssVar('primary', '#b8270f'),
            primarySubtle: cssVar('primary-bg-subtle', '#f8e3df'),
            success: cssVar('success', '#28c76f'),
            warning: cssVar('warning', '#ff9f43'),
            info: cssVar('info', '#00cfe8'),
            danger: cssVar('danger', '#ea5455'),
            secondary: cssVar('secondary', '#a8aaae'),
            card: cssVar('paper-bg', '#ffffff'),
            heading: cssVar('heading-color', '#444050'),
            muted: cssVar('secondary-color', '#acaab1'),
            border: cssVar('border-color', '#e6e6e8'),
            track: cssVar('secondary-bg', '#e6e6e8')
        };
    }

    function moneyShort(value) {
        const abs = Math.abs(value);
        if (abs >= 1000000) {
            return 'R$ ' + (value / 1000000).toFixed(1).replace('.', ',') + ' mi';
        }
        if (abs >= 1000) {
            return 'R$ ' + (value / 1000).toFixed(1).replace('.', ',') + ' mil';
        }
        return 'R$ ' + intFmt.format(Math.round(value));
    }

    function mount(element, options) {
        const chart = new ApexCharts(element, options);
        chart.render();
        return chart;
    }

    // ------------------------------------------------------------------
    // Cartões do topo
    // ------------------------------------------------------------------
    function renderOrdersSparkline(c) {
        const element = document.querySelector('#ordersLastWeek');
        if (!element) {
            return null;
        }

        const series = data.openOrdersByDay || data.ordersByDay;
        const values = series.values;
        return mount(element, {
            chart: {
                height: 75,
                type: 'bar',
                parentHeightOffset: 0,
                toolbar: { show: false }
            },
            tooltip: { enabled: false },
            plotOptions: {
                bar: {
                    barHeight: '100%',
                    columnWidth: '7px',
                    startingShape: 'rounded',
                    endingShape: 'rounded',
                    borderRadius: 4,
                    colors: {
                        backgroundBarColors: new Array(values.length).fill(c.track),
                        backgroundBarRadius: 4
                    }
                }
            },
            colors: [c.primary],
            grid: {
                show: false,
                padding: { top: -30, left: -16, bottom: 0, right: -6 }
            },
            dataLabels: { enabled: false },
            series: [{ name: 'OS', data: values }],
            legend: { show: false },
            xaxis: {
                categories: series.labels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { show: false }
            },
            yaxis: { labels: { show: false } },
            states: {
                hover: { filter: { type: 'none' } },
                active: { filter: { type: 'none' } }
            }
        });
    }

    function renderRevenueSparkline(c) {
        const element = document.querySelector('#revenueLastMonths');
        if (!element || !data.revenueByMonth) {
            return null;
        }

        return mount(element, {
            chart: {
                height: 75,
                type: 'area',
                parentHeightOffset: 0,
                toolbar: { show: false },
                sparkline: { enabled: true }
            },
            markers: { colors: 'transparent', strokeColors: 'transparent' },
            grid: { show: false },
            colors: [c.success],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    gradientToColors: [c.card],
                    opacityTo: 0.1,
                    stops: [0, 100]
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 2, curve: 'smooth' },
            series: [{ name: 'Receita', data: data.revenueByMonth.values }],
            xaxis: {
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false },
                lines: { show: false }
            },
            yaxis: { show: false },
            tooltip: { enabled: false }
        });
    }

    function renderWeekOrders(c) {
        const element = document.querySelector('#weekOrders');
        if (!element) {
            return null;
        }

        const values = data.ordersByDay.values;
        const lastIndex = values.length - 1;

        return mount(element, {
            chart: {
                height: 162,
                type: 'bar',
                parentHeightOffset: 0,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    barHeight: '80%',
                    columnWidth: '38%',
                    startingShape: 'rounded',
                    endingShape: 'rounded',
                    borderRadius: 5,
                    distributed: true
                }
            },
            tooltip: { enabled: false },
            grid: {
                show: false,
                padding: { top: -20, bottom: -12, left: -13, right: -3 }
            },
            colors: values.map(function (_, index) {
                return index === lastIndex ? c.primary : c.primarySubtle;
            }),
            dataLabels: { enabled: false },
            series: [{ name: 'OS', data: values }],
            legend: { show: false },
            xaxis: {
                categories: data.ordersByDay.labels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    rotate: 0,
                    rotateAlways: false,
                    hideOverlappingLabels: true,
                    style: { colors: c.muted, fontSize: '11px', fontFamily: FONT_FAMILY }
                }
            },
            yaxis: { labels: { show: false } },
            states: {
                hover: { filter: { type: 'none' } },
                active: { filter: { type: 'none' } }
            },
            responsive: [
                {
                    breakpoint: 1200,
                    options: { plotOptions: { bar: { columnWidth: '28%', borderRadius: 6 } } }
                },
                {
                    breakpoint: 576,
                    options: { plotOptions: { bar: { borderRadius: 9 } } }
                }
            ]
        });
    }

    // ------------------------------------------------------------------
    // Indicadores (abas)
    // ------------------------------------------------------------------
    /** Arredonda o topo do eixo para um valor "redondo" com folga para o rótulo. */
    function axisMax(value) {
        if (!value || value <= 0) {
            return 5;
        }
        const target = value * 1.1;
        const exponent = Math.pow(10, Math.floor(Math.log10(target)));
        const fraction = target / exponent;
        const nice = [1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find(function (step) {
            return fraction <= step;
        }) || 10;
        return nice * exponent;
    }

    function indicatorOptions(tab, c) {
        const highlight = typeof tab.highlight === 'number' ? tab.highlight : tab.values.length - 1;
        const isMoney = tab.type === 'money';
        const dataMax = tab.values.reduce(function (max, value) {
            return Math.max(max, Number(value) || 0);
        }, 0);

        return {
            chart: {
                height: 320,
                type: 'bar',
                parentHeightOffset: 0,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    columnWidth: '38%',
                    startingShape: 'rounded',
                    borderRadius: 6,
                    distributed: true,
                    dataLabels: { position: 'top' }
                }
            },
            grid: {
                show: false,
                padding: { top: 24, bottom: 0, left: -10, right: -10 }
            },
            colors: tab.values.map(function (_, index) {
                return index === highlight ? c.primary : c.primarySubtle;
            }),
            dataLabels: {
                enabled: true,
                formatter: function (value) {
                    if (!value) {
                        return '';
                    }
                    return isMoney ? moneyShort(Number(value)) : intFmt.format(Number(value));
                },
                offsetY: -14,
                style: {
                    fontSize: '12px',
                    colors: [c.heading],
                    fontWeight: '600',
                    fontFamily: FONT_FAMILY
                }
            },
            series: [{ name: tab.label, data: tab.values }],
            legend: { show: false },
            tooltip: {
                enabled: true,
                y: {
                    formatter: function (value) {
                        return isMoney ? moneyFmt.format(Number(value)) : intFmt.format(Number(value)) + ' registro(s)';
                    }
                }
            },
            xaxis: {
                categories: tab.labels,
                axisBorder: { show: true, color: c.border },
                axisTicks: { show: false },
                labels: {
                    style: { colors: c.muted, fontSize: '12px', fontFamily: FONT_FAMILY }
                }
            },
            yaxis: {
                labels: { show: false },
                min: 0,
                max: axisMax(dataMax),
                tickAmount: 4
            },
            states: {
                hover: { filter: { type: 'none' } },
                active: { filter: { type: 'none' } }
            },
            responsive: [
                {
                    breakpoint: 590,
                    options: {
                        chart: { height: 260 },
                        plotOptions: { bar: { columnWidth: '58%', borderRadius: 5 } },
                        dataLabels: { style: { fontSize: '10px', fontWeight: '500' } }
                    }
                }
            ]
        };
    }

    function renderIndicator(key) {
        if (tabCharts[key]) {
            return;
        }

        const tab = (data.indicatorTabs || []).find(function (item) {
            return item.key === key;
        });
        const element = document.querySelector('.indicator-chart[data-indicator="' + key + '"]');
        if (!tab || !element) {
            return;
        }

        tabCharts[key] = mount(element, indicatorOptions(tab, palette()));
    }

    // ------------------------------------------------------------------
    // Pedidos de peças (rosca)
    // ------------------------------------------------------------------
    function renderPartsChart(c) {
        const element = document.querySelector('#partsChart');
        if (!element) {
            return null;
        }

        const parts = data.partsByStatus;
        const colorByKey = {
            requested: c.secondary,
            validated: c.info,
            ordered: c.primary,
            on_the_way: c.warning,
            received: c.success,
            cancelled: c.heading
        };
        const colors = parts.keys.map(function (key) {
            return colorByKey[key] || c.secondary;
        });
        const total = parts.values.reduce(function (sum, value) {
            return sum + Number(value);
        }, 0);

        return mount(element, {
            chart: {
                height: 300,
                type: 'donut',
                parentHeightOffset: 0
            },
            labels: parts.labels,
            series: parts.values,
            colors: colors,
            stroke: { width: 2, colors: [c.card] },
            legend: {
                show: true,
                position: 'bottom',
                fontSize: '13px',
                fontFamily: FONT_FAMILY,
                labels: { colors: c.muted, useSeriesColors: false },
                markers: { size: 6, offsetX: -3, offsetY: 0 },
                itemMargin: { horizontal: 8, vertical: 4 }
            },
            dataLabels: {
                enabled: total > 0,
                formatter: function (value, opts) {
                    const seriesValue = opts.w.globals.series[opts.seriesIndex];
                    return seriesValue > 0 ? Math.round(Number(value)) + '%' : '';
                },
                style: {
                    fontSize: '12px',
                    fontFamily: FONT_FAMILY,
                    fontWeight: '600'
                },
                dropShadow: { enabled: false }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '13px',
                                fontFamily: FONT_FAMILY,
                                color: c.muted,
                                offsetY: -6
                            },
                            value: {
                                show: true,
                                fontSize: '20px',
                                fontWeight: '600',
                                fontFamily: FONT_FAMILY,
                                color: c.heading,
                                offsetY: 4,
                                formatter: function (value) {
                                    return intFmt.format(Number(value));
                                }
                            },
                            total: {
                                show: true,
                                label: 'Pedidos',
                                fontSize: '13px',
                                fontFamily: FONT_FAMILY,
                                color: c.muted,
                                formatter: function () {
                                    return intFmt.format(total);
                                }
                            }
                        }
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return intFmt.format(Number(value)) + ' pedido(s)';
                    }
                }
            },
            states: {
                hover: { filter: { type: 'none' } },
                active: { filter: { type: 'none' } }
            }
        });
    }

    // ------------------------------------------------------------------
    // Status da oficina (área semanal)
    // ------------------------------------------------------------------
    function renderWorkshopArea(c) {
        const element = document.querySelector('#workshopArea');
        if (!element) {
            return null;
        }

        return mount(element, {
            chart: {
                height: 200,
                type: 'area',
                toolbar: { show: false }
            },
            markers: { strokeColor: 'transparent' },
            series: [{ name: 'Novas OS', data: data.ordersByWeek.values }],
            dataLabels: { enabled: false },
            grid: {
                show: false,
                padding: { left: -10, right: -5 }
            },
            stroke: { width: 3, curve: 'straight' },
            colors: [c.warning],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    gradientToColors: [c.card],
                    opacityTo: 0.1,
                    stops: [0, 100]
                }
            },
            xaxis: {
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false },
                lines: { show: false }
            },
            yaxis: {
                labels: { show: false },
                min: 0,
                tickAmount: 4,
                forceNiceScale: true
            },
            tooltip: {
                enabled: true,
                x: { show: false },
                y: {
                    formatter: function (value) {
                        return intFmt.format(Number(value)) + ' nova(s) OS';
                    }
                }
            }
        });
    }

    function renderMainCharts() {
        const c = palette();
        [
            renderOrdersSparkline(c),
            renderRevenueSparkline(c),
            renderWeekOrders(c),
            renderPartsChart(c),
            renderWorkshopArea(c)
        ].forEach(function (chart) {
            if (chart) {
                mainCharts.push(chart);
            }
        });
    }

    function renderActiveIndicator() {
        const tabs = data.indicatorTabs || [];
        if (!tabs.length) {
            return;
        }
        const activeButton = document.querySelector('[data-bs-target^="#indicator-"].active');
        const key = activeButton
            ? activeButton.getAttribute('data-bs-target').replace('#indicator-', '')
            : tabs[0].key;
        renderIndicator(key);
    }

    // ------------------------------------------------------------------
    // Abas: monta o gráfico sob demanda (pane oculto não tem largura).
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-bs-target^="#indicator-"]').forEach(function (button) {
        button.addEventListener('shown.bs.tab', function () {
            renderIndicator(this.getAttribute('data-bs-target').replace('#indicator-', ''));
        });
    });

    // ------------------------------------------------------------------
    // Tema claro/escuro: recria os gráficos com as cores do tema novo.
    // ------------------------------------------------------------------
    let themeTimeout = null;
    const themeObserver = new MutationObserver(function () {
        window.clearTimeout(themeTimeout);
        themeTimeout = window.setTimeout(function () {
            mainCharts.splice(0).forEach(function (chart) {
                chart.destroy();
            });
            Object.keys(tabCharts).forEach(function (key) {
                tabCharts[key].destroy();
                delete tabCharts[key];
            });
            renderMainCharts();
            renderActiveIndicator();
        }, 80);
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });

    // Espera o layout/fontes terminarem para o Apex medir a largura correta.
    function start() {
        renderMainCharts();
        renderActiveIndicator();
    }

    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
})();
