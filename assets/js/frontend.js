/**
 * SECOP Suite - Frontend JavaScript
 *
 * @package SecopSuite
 * @version 4.1.2
 */

(function($) {
    'use strict';

    /**
     * Formateador de números estilo colombiano
     */
    const NumberFormatter = {
        colombiano: function(value) {
            if (value >= 1e12) return (value / 1e12).toFixed(1).replace('.', ',') + ' Billones';
            if (value >= 1e9) return (value / 1e9).toFixed(1).replace('.', ',') + ' MMll';
            if (value >= 1e6) return (value / 1e6).toFixed(1).replace('.', ',') + ' Millones';
            if (value >= 1e3) return (value / 1e3).toFixed(1).replace('.', ',') + ' Mil';
            return value.toLocaleString('es-CO');
        },
        millones: function(value) {
            if (value >= 1e12) return (value / 1e12).toFixed(2) + 'B';
            if (value >= 1e9) return (value / 1e9).toFixed(2) + 'MMll';
            if (value >= 1e6) return (value / 1e6).toFixed(2) + 'M';
            if (value >= 1e3) return (value / 1e3).toFixed(2) + 'K';
            return value.toString();
        },
        internacional: function(value) {
            return new Intl.NumberFormat('en-US').format(value);
        },
        sin_formato: function(value) {
            return value.toString();
        },
        format: function(value, format) {
            return (this[format] || this.colombiano)(value);
        },
        fullFormat: function(value) {
            return new Intl.NumberFormat('es-CO', { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
        }
    };

    /**
     * Safely resolve a d3plus constructor by name.
     * Tries d3plus.Name first, then d3plus_hierarchy.Name, etc.
     */
    function getD3PlusClass(name) {
        if (typeof d3plus !== 'undefined' && d3plus[name]) return d3plus[name];
        // Fallback to global window in case of separate package loads
        if (typeof window['d3plus_hierarchy'] !== 'undefined' && window['d3plus_hierarchy'][name]) return window['d3plus_hierarchy'][name];
        if (typeof window['d3plus_plot'] !== 'undefined' && window['d3plus_plot'][name]) return window['d3plus_plot'][name];
        if (typeof window['d3plus_network'] !== 'undefined' && window['d3plus_network'][name]) return window['d3plus_network'][name];
        return null;
    }

    /**
     * Chart Manager
     */
    class ChartManager {
        constructor(container) {
            this.$container = $(container);
            this.uniqueId = this.$container.attr('id');
            this.chartId = this.$container.data('chart-id');
            this.chartType = this.$container.data('chart-type');
            this.config = this.loadConfig();
            this.data = [];
            this.chart = null;
            this.init();
        }

        loadConfig() {
            const configEl = document.getElementById(this.uniqueId + '-config');
            if (configEl) {
                try { return JSON.parse(configEl.textContent); } catch (e) { console.error('Config parse error:', e); }
            }
            return {};
        }

        init() {
            this.bindToolbarEvents();
            this.bindModalEvents();
            this.loadData();
        }

        bindToolbarEvents() {
            const self = this;
            this.$container.find('.ss-toolbar-btn').on('click', function() {
                const action = $(this).data('action');
                switch (action) {
                    case 'detail': self.showDetailModal(); break;
                    case 'share': self.showShareModal(); break;
                    case 'data': self.showDataModal(); break;
                    case 'image': self.downloadImage(); break;
                    case 'download': self.downloadCSV(); break;
                }
            });
        }

        bindModalEvents() {
            const self = this;
            ['data-modal', 'share-modal', 'detail-modal'].forEach(function(suffix) {
                const $modal = $('#' + self.uniqueId + '-' + suffix);
                $modal.find('.ss-modal-close, .ss-modal-close-btn').on('click', function() { $modal.fadeOut(200); });
                $modal.find('.ss-modal-overlay').on('click', function() { $modal.fadeOut(200); });
            });
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') { $('.ss-modal:visible').fadeOut(200); }
            });
            var $shareModal = $('#' + this.uniqueId + '-share-modal');
            $shareModal.find('.ss-share-btn[data-network]').on('click', function(e) { e.preventDefault(); self.shareToNetwork($(this).data('network')); });
            $shareModal.find('[data-action="copy-link"]').on('click', function() { self.copyLink(); });
            $('#' + this.uniqueId + '-data-modal').find('[data-action="download-from-modal"]').on('click', function() { self.downloadCSV(); });
        }

        loadData() {
            const self = this;
            $.ajax({
                url: secopSuiteChart.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_get_chart_data',
                    nonce: this.config.chartNonce || secopSuiteChart.nonce,
                    chart_id: this.chartId
                },
                success: function(response) {
                    if (response.success) { self.data = response.data.data; self.renderChart(); }
                    else { self.showError(response.data.message); }
                },
                error: function() { self.showError(secopSuiteChart.strings.error); }
            });
        }

        renderChart() {
            if (!this.data || this.data.length === 0) {
                this.showError(secopSuiteChart.strings.noData);
                return;
            }

            const renderTarget = '#' + this.uniqueId + '-render';
            const config = this.config;
            const colors = config.colors || ['#844e80', '#ff7300', '#ffc53b', '#3eba6a', '#0080c3'];
            const numberFormat = config.numberFormat || 'colombiano';
            const isMultiY = config.multiY || false;

            // Prepare data
            const chartData = this.data.map(function(d) {
                return { x: d.x_value, y: parseFloat(d.y_value) || 0, group: d.group_value || d.x_value };
            });

            if (isMultiY) config.showLegend = true;

            const groups = [...new Set(chartData.map(function(d) { return d.group; }))];
            const colorScale = d3.scaleOrdinal().domain(groups).range(colors);

            const tooltipConfig = {
                tbody: [
                    [config.xAxisTitle || 'Categoría', function(d) { return d.x; }],
                    [config.yAxisTitle || 'Valor', function(d) { return NumberFormatter.fullFormat(d.y); }]
                ]
            };
            const yConfig = {
                title: config.yAxisTitle || 'Valor',
                tickFormat: function(d) { return NumberFormatter.format(d, numberFormat); }
            };
            const showLegend = config.showLegend || false;

            try {
                this.chart = this._createChart(this.chartType, chartData, renderTarget, colorScale, tooltipConfig, yConfig, showLegend, config);
            } catch (e) {
                console.error('SECOP Chart render error:', e);
                this.showError('Error: ' + e.message);
                return;
            }

            if (this.chart) {
                this.chart.render();
                this._waitForRender(renderTarget);
            }
        }

        _createChart(type, data, target, colorScale, tooltipConfig, yConfig, legend, config) {
            var Cls;

            switch (type) {
                case 'bar':
                case 'stacked_bar':
                case 'grouped_bar':
                    Cls = getD3PlusClass('BarChart');
                    if (!Cls) throw new Error('d3plus.BarChart not available');
                    var chart = new Cls()
                        .data(data).groupBy('group').x('x').y('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.group || d.x); })
                        .tooltipConfig(tooltipConfig).yConfig(yConfig)
                        .legend(legend).locale('es_ES');
                    if (type === 'stacked_bar') chart.stacked(true);
                    if (type === 'grouped_bar') chart.stacked(false).barPadding(2).groupPadding(10);
                    if (config.showTimeline) chart.time('x').timeline(true);
                    return chart;

                case 'line':
                    Cls = getD3PlusClass('LinePlot');
                    if (!Cls) throw new Error('d3plus.LinePlot not available');
                    var lc = new Cls()
                        .data(data).groupBy('group').x('x').y('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.group); })
                        .tooltipConfig(tooltipConfig).yConfig(yConfig)
                        .legend(legend).locale('es_ES');
                    if (config.showTimeline) lc.time('x').timeline(true);
                    return lc;

                case 'area':
                    Cls = getD3PlusClass('AreaPlot') || getD3PlusClass('StackedArea');
                    if (!Cls) throw new Error('d3plus.AreaPlot not available');
                    return new Cls()
                        .data(data).groupBy('group').x('x').y('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.group); })
                        .tooltipConfig(tooltipConfig).yConfig(yConfig)
                        .legend(legend).locale('es_ES');

                case 'pie':
                    Cls = getD3PlusClass('Pie');
                    if (!Cls) throw new Error('d3plus.Pie not available');
                    return new Cls()
                        .data(data).groupBy('x').value('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig(tooltipConfig)
                        .legend(true).locale('es_ES');

                case 'donut':
                    Cls = getD3PlusClass('Donut');
                    if (!Cls) throw new Error('d3plus.Donut not available');
                    return new Cls()
                        .data(data).groupBy('x').value('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig(tooltipConfig)
                        .legend(true).locale('es_ES');

                case 'treemap':
                    Cls = getD3PlusClass('Treemap');
                    if (!Cls) throw new Error('d3plus.Treemap not available');
                    return new Cls()
                        .data(data).groupBy('x').sum('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig(tooltipConfig).locale('es_ES');

                case 'tree':
                    Cls = getD3PlusClass('Tree');
                    if (!Cls) throw new Error('d3plus.Tree not available');
                    var treeData = data.map(function(d) { return { id: d.x, value: d.y, parent: d.group !== d.x ? d.group : null }; });
                    var ids = treeData.map(function(d) { return d.id; });
                    var parents = [...new Set(treeData.filter(function(d) { return d.parent; }).map(function(d) { return d.parent; }))];
                    parents.forEach(function(p) { if (ids.indexOf(p) === -1) treeData.push({ id: p, value: 0, parent: null }); });
                    return new Cls().data(treeData).groupBy('id').select(target)
                        .color(function(d) { return colorScale(d.id); })
                        .legend(legend).locale('es_ES');

                case 'pack':
                    Cls = getD3PlusClass('Pack');
                    if (!Cls) throw new Error('d3plus.Pack not available');
                    var packData = data.map(function(d) { return { id: d.x, value: d.y, group: d.group }; });
                    return new Cls().data(packData).groupBy('id').sum('value')
                        .select(target)
                        .color(function(d) { return colorScale(d.group || d.id); })
                        .legend(legend).locale('es_ES');

                case 'network':
                    Cls = getD3PlusClass('Network');
                    if (!Cls) throw new Error('d3plus.Network not available');
                    var nodes = [], links = [], nodeIds = {};
                    data.forEach(function(d) {
                        if (!nodeIds[d.x]) { nodeIds[d.x] = true; nodes.push({ id: d.x, value: d.y }); }
                        if (d.group && d.group !== d.x) {
                            if (!nodeIds[d.group]) { nodeIds[d.group] = true; nodes.push({ id: d.group, value: 0 }); }
                            links.push({ source: d.group, target: d.x });
                        }
                    });
                    return new Cls().data(nodes).links(links).groupBy('id')
                        .select(target)
                        .color(function(d) { return colorScale(d.id); })
                        .locale('es_ES');

                default:
                    Cls = getD3PlusClass('BarChart');
                    if (!Cls) throw new Error('d3plus library not loaded');
                    return new Cls().data(data).groupBy('x').x('x').y('y')
                        .select(target)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig(tooltipConfig).yConfig(yConfig).locale('es_ES');
            }
        }

        _waitForRender(renderTarget) {
            const self = this;
            let attempts = 0;
            const check = setInterval(function() {
                attempts++;
                if (document.querySelector(renderTarget + ' svg')) {
                    self.$container.addClass('ss-loaded');
                    clearInterval(check);
                } else if (attempts >= 300) {
                    clearInterval(check);
                    self.showError(secopSuiteChart.strings.error || 'Error al renderizar');
                }
            }, 100);
        }

        showError(message) {
            this.$container.find('.ss-loading').hide();
            this.$container.find('.ss-error-message').show().find('p').text(message);
        }

        showDetailModal() { $('#' + this.uniqueId + '-detail-modal').fadeIn(200); }
        showShareModal() { $('#' + this.uniqueId + '-share-modal').fadeIn(200); }

        showDataModal() {
            const $modal = $('#' + this.uniqueId + '-data-modal');
            const $thead = $modal.find('thead');
            const $tbody = $modal.find('tbody');
            $thead.empty(); $tbody.empty();
            if (this.data && this.data.length > 0) {
                const headers = Object.keys(this.data[0]);
                const $hr = $('<tr>');
                headers.forEach(function(h) { $hr.append($('<th>').text(h.replace(/_/g, ' '))); });
                $thead.append($hr);
                this.data.forEach(function(row) {
                    const $dr = $('<tr>');
                    headers.forEach(function(h) {
                        var v = row[h];
                        if (!isNaN(parseFloat(v)) && h.includes('value')) v = NumberFormatter.fullFormat(parseFloat(v));
                        $dr.append($('<td>').text(v));
                    });
                    $tbody.append($dr);
                });
            }
            $modal.fadeIn(200);
        }

        shareToNetwork(network) {
            var url = encodeURIComponent(window.location.href + '#' + this.uniqueId);
            var title = encodeURIComponent(this.config.title || 'Gráfica SECOP');
            var shareUrl = '';
            switch (network) {
                case 'facebook': shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url; break;
                case 'twitter': shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title; break;
                case 'linkedin': shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url; break;
                case 'whatsapp': shareUrl = 'https://api.whatsapp.com/send?text=' + title + '%20' + url; break;
            }
            if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=400');
        }

        copyLink() {
            var link = window.location.href + '#' + this.uniqueId;
            navigator.clipboard.writeText(link).then(() => { this.showToast(secopSuiteChart.strings.copied); });
        }

        downloadImage() {
            var self = this;
            var el = document.querySelector('#' + this.uniqueId + '-render');
            if (!el) return;
            this.showToast('Generando imagen...');
            html2canvas(el, { backgroundColor: '#ffffff', scale: 2, logging: false }).then(function(canvas) {
                var a = document.createElement('a'); a.download = 'grafica-' + self.chartId + '.png'; a.href = canvas.toDataURL('image/png'); a.click();
            }).catch(function() { self.showToast('Error al generar imagen'); });
        }

        downloadCSV() {
            var a = document.createElement('a'); a.href = secopSuiteChart.restUrl + 'chart/' + this.chartId + '/csv'; a.download = 'datos-' + this.chartId + '.csv'; a.click();
        }

        showToast(message) {
            $('.ss-toast').remove();
            var $t = $('<div class="ss-toast">').text(message);
            $('body').append($t);
            setTimeout(function() { $t.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        }
    }

    // Initialize with lazy loading
    $(document).ready(function() {
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) { new ChartManager(entry.target); observer.unobserve(entry.target); }
                });
            }, { rootMargin: '200px' });
            $('.ss-chart-container').each(function() { observer.observe(this); });
        } else {
            $('.ss-chart-container').each(function() { new ChartManager(this); });
        }
    });

    window.SSChartManager = ChartManager;
    window.SSNumberFormatter = NumberFormatter;

})(jQuery);
