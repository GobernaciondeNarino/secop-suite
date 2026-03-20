/**
 * SECOP Data Visualizer - Frontend JavaScript
 * 
 * @package SecopDataVisualizer
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Formateador de números estilo colombiano
     */
    const NumberFormatter = {
        colombiano: function(value) {
            if (value >= 1e12) {
                return (value / 1e12).toFixed(1).replace('.', ',') + ' Billones';
            } else if (value >= 1e9) {
                return (value / 1e9).toFixed(1).replace('.', ',') + ' MMll';
            } else if (value >= 1e6) {
                return (value / 1e6).toFixed(1).replace('.', ',') + ' Millones';
            } else if (value >= 1e3) {
                return (value / 1e3).toFixed(1).replace('.', ',') + ' Mil';
            }
            return value.toLocaleString('es-CO');
        },

        millones: function(value) {
            if (value >= 1e12) {
                return (value / 1e12).toFixed(2) + 'B';
            } else if (value >= 1e9) {
                return (value / 1e9).toFixed(2) + 'MMll';
            } else if (value >= 1e6) {
                return (value / 1e6).toFixed(2) + 'M';
            } else if (value >= 1e3) {
                return (value / 1e3).toFixed(2) + 'K';
            }
            return value.toString();
        },

        internacional: function(value) {
            return new Intl.NumberFormat('en-US').format(value);
        },

        sin_formato: function(value) {
            return value.toString();
        },

        format: function(value, format) {
            const formatter = this[format] || this.colombiano;
            return formatter(value);
        },

        fullFormat: function(value) {
            return new Intl.NumberFormat('es-CO', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(value);
        }
    };

    /**
     * Chart Manager - Maneja una instancia de gráfica
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
                try {
                    return JSON.parse(configEl.textContent);
                } catch (e) {
                    console.error('Error parsing chart config:', e);
                }
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
                    case 'detail':
                        self.showDetailModal();
                        break;
                    case 'share':
                        self.showShareModal();
                        break;
                    case 'data':
                        self.showDataModal();
                        break;
                    case 'image':
                        self.downloadImage();
                        break;
                    case 'download':
                        self.downloadCSV();
                        break;
                }
            });
        }

        bindModalEvents() {
            const self = this;
            const modals = [
                '#' + this.uniqueId + '-data-modal',
                '#' + this.uniqueId + '-share-modal',
                '#' + this.uniqueId + '-detail-modal'
            ];

            modals.forEach(function(modalId) {
                const $modal = $(modalId);
                
                // Close button
                $modal.find('.ss-modal-close, .ss-modal-close-btn').on('click', function() {
                    $modal.fadeOut(200);
                });

                // Overlay click
                $modal.find('.ss-modal-overlay').on('click', function() {
                    $modal.fadeOut(200);
                });

                // ESC key
                $(document).on('keyup', function(e) {
                    if (e.key === 'Escape') {
                        $modal.fadeOut(200);
                    }
                });
            });

            // Share buttons
            const $shareModal = $('#' + this.uniqueId + '-share-modal');
            $shareModal.find('.ss-share-btn[data-network]').on('click', function(e) {
                e.preventDefault();
                const network = $(this).data('network');
                self.shareToNetwork(network);
            });

            // Copy link
            $shareModal.find('[data-action="copy-link"]').on('click', function() {
                self.copyLink();
            });

            // Download from modal
            const $dataModal = $('#' + this.uniqueId + '-data-modal');
            $dataModal.find('[data-action="download-from-modal"]').on('click', function() {
                self.downloadCSV();
            });
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
                    if (response.success) {
                        self.data = response.data.data;
                        self.renderChart();
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(secopSuiteChart.strings.error);
                }
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

            // Preparar datos
            const chartData = this.data.map(function(d) {
                return {
                    x: d.x_value,
                    y: parseFloat(d.y_value) || 0,
                    group: d.group_value || d.x_value
                };
            });

            // Crear escala de colores
            const groups = [...new Set(chartData.map(d => d.group))];
            const colorScale = d3.scaleOrdinal().domain(groups).range(colors);

            // Configuración base
            const baseConfig = {
                data: chartData,
                select: renderTarget,
                color: function(d) { return colorScale(d.group); },
                tooltipConfig: {
                    tbody: [
                        [config.xAxisTitle || 'Categoría', function(d) { return d.x; }],
                        [config.yAxisTitle || 'Valor', function(d) { 
                            return NumberFormatter.fullFormat(d.y); 
                        }]
                    ]
                },
                yConfig: {
                    title: config.yAxisTitle || 'Valor',
                    tickFormat: function(d) { 
                        return NumberFormatter.format(d, numberFormat); 
                    }
                },
                xConfig: {
                    title: config.xAxisTitle || ''
                },
                legend: config.showLegend,
                legendPosition: config.showLegend ? 'bottom' : false,
                locale: 'es_ES'
            };

            // Timeline
            if (config.showTimeline) {
                baseConfig.time = 'x';
                baseConfig.timeline = true;
            }

            // Renderizar según tipo
            switch (this.chartType) {
                case 'bar':
                    this.chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.group || d.x); })
                        .tooltipConfig(baseConfig.tooltipConfig)
                        .yConfig(baseConfig.yConfig)
                        .legend(config.showLegend || false)
                        .legendPosition(config.showLegend ? 'bottom' : false)
                        .locale('es_ES');
                    
                    if (config.showTimeline) {
                        this.chart.time('x').timeline(true);
                    }
                    break;

                case 'line':
                    this.chart = new d3plus.LinePlot()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.group); })
                        .tooltipConfig(baseConfig.tooltipConfig)
                        .yConfig(baseConfig.yConfig)
                        .legend(config.showLegend || false)
                        .legendPosition(config.showLegend ? 'bottom' : false)
                        .locale('es_ES');
                    
                    if (config.showTimeline) {
                        this.chart.time('x').timeline(true);
                    }
                    break;

                case 'area':
                    this.chart = new d3plus.AreaPlot()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.group); })
                        .tooltipConfig(baseConfig.tooltipConfig)
                        .yConfig(baseConfig.yConfig)
                        .legend(config.showLegend || false)
                        .locale('es_ES');
                    break;

                case 'pie':
                    this.chart = new d3plus.Pie()
                        .data(chartData)
                        .groupBy('x')
                        .value('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig({
                            tbody: [
                                ['Categoría', function(d) { return d.x; }],
                                ['Valor', function(d) { return NumberFormatter.fullFormat(d.y); }]
                            ]
                        })
                        .legend(config.showLegend !== false)
                        .locale('es_ES');
                    break;

                case 'donut':
                    this.chart = new d3plus.Donut()
                        .data(chartData)
                        .groupBy('x')
                        .value('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig({
                            tbody: [
                                ['Categoría', function(d) { return d.x; }],
                                ['Valor', function(d) { return NumberFormatter.fullFormat(d.y); }]
                            ]
                        })
                        .legend(config.showLegend !== false)
                        .locale('es_ES');
                    break;

                case 'treemap':
                    this.chart = new d3plus.Treemap()
                        .data(chartData)
                        .groupBy('x')
                        .sum('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig({
                            tbody: [
                                ['Categoría', function(d) { return d.x; }],
                                ['Valor', function(d) { return NumberFormatter.fullFormat(d.y); }]
                            ]
                        })
                        .locale('es_ES');
                    break;

                case 'stacked_bar':
                    this.chart = new d3plus.StackedArea()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.group); })
                        .tooltipConfig(baseConfig.tooltipConfig)
                        .yConfig(baseConfig.yConfig)
                        .legend(config.showLegend || false)
                        .locale('es_ES');
                    break;

                case 'grouped_bar':
                    this.chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy(['x', 'group'])
                        .x('x')
                        .y('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.group); })
                        .tooltipConfig(baseConfig.tooltipConfig)
                        .yConfig(baseConfig.yConfig)
                        .legend(config.showLegend || false)
                        .barPadding(2)
                        .groupPadding(10)
                        .locale('es_ES');
                    break;

                default:
                    this.chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('x')
                        .x('x')
                        .y('y')
                        .select(renderTarget)
                        .color(function(d) { return colorScale(d.x); })
                        .tooltipConfig(baseConfig.tooltipConfig)
                        .yConfig(baseConfig.yConfig)
                        .locale('es_ES');
            }

            // Renderizar
            this.chart.render();

            // Marcar como cargado (con timeout máximo de 30s)
            const self = this;
            let attempts = 0;
            const maxAttempts = 300; // 300 * 100ms = 30s
            const checkRendered = setInterval(function() {
                attempts++;
                const svgElement = document.querySelector(renderTarget + ' svg');
                if (svgElement) {
                    self.$container.addClass('ss-loaded');
                    clearInterval(checkRendered);
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkRendered);
                    self.showError(secopSuiteChart.strings.error || 'Error al renderizar la gráfica');
                }
            }, 100);
        }

        showError(message) {
            this.$container.find('.ss-loading').hide();
            this.$container.find('.ss-error-message').show().find('p').text(message);
        }

        showDetailModal() {
            $('#' + this.uniqueId + '-detail-modal').fadeIn(200);
        }

        showShareModal() {
            $('#' + this.uniqueId + '-share-modal').fadeIn(200);
        }

        showDataModal() {
            const $modal = $('#' + this.uniqueId + '-data-modal');
            const $thead = $modal.find('thead');
            const $tbody = $modal.find('tbody');

            $thead.empty();
            $tbody.empty();

            if (this.data && this.data.length > 0) {
                // Headers
                const headers = Object.keys(this.data[0]);
                const $headerRow = $('<tr>');
                headers.forEach(function(header) {
                    $headerRow.append($('<th>').text(header.replace(/_/g, ' ')));
                });
                $thead.append($headerRow);

                // Data rows
                this.data.forEach(function(row) {
                    const $dataRow = $('<tr>');
                    headers.forEach(function(header) {
                        let value = row[header];
                        // Format numbers
                        if (typeof value === 'number' || (!isNaN(parseFloat(value)) && header.includes('value'))) {
                            value = NumberFormatter.fullFormat(parseFloat(value));
                        }
                        $dataRow.append($('<td>').text(value));
                    });
                    $tbody.append($dataRow);
                });
            }

            $modal.fadeIn(200);
        }

        shareToNetwork(network) {
            const url = encodeURIComponent(window.location.href + '#' + this.uniqueId);
            const title = encodeURIComponent(this.config.title || 'Gráfica SECOP');
            let shareUrl = '';

            switch (network) {
                case 'facebook':
                    shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
                    break;
                case 'twitter':
                    shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title;
                    break;
                case 'linkedin':
                    shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url;
                    break;
                case 'whatsapp':
                    shareUrl = 'https://api.whatsapp.com/send?text=' + title + '%20' + url;
                    break;
            }

            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        copyLink() {
            const link = window.location.href + '#' + this.uniqueId;
            const $input = $('#' + this.uniqueId + '-share-modal').find('.ss-share-link input');
            
            $input.val(link).select();
            
            try {
                document.execCommand('copy');
                this.showToast(secopSuiteChart.strings.copied);
            } catch (err) {
                // Fallback for modern browsers
                navigator.clipboard.writeText(link).then(() => {
                    this.showToast(secopSuiteChart.strings.copied);
                });
            }
        }

        downloadImage() {
            const self = this;
            const chartElement = document.querySelector('#' + this.uniqueId + '-render');
            
            if (!chartElement) return;

            // Show loading
            this.showToast('Generando imagen...');

            html2canvas(chartElement, {
                backgroundColor: '#ffffff',
                scale: 2,
                logging: false
            }).then(function(canvas) {
                const link = document.createElement('a');
                link.download = 'grafica-' + self.chartId + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            }).catch(function(error) {
                console.error('Error generating image:', error);
                self.showToast('Error al generar imagen');
            });
        }

        downloadCSV() {
            const link = document.createElement('a');
            link.href = secopSuiteChart.restUrl + 'chart/' + this.chartId + '/csv';
            link.download = 'datos-' + this.chartId + '.csv';
            link.click();
        }

        showToast(message) {
            // Remove existing toast
            $('.ss-toast').remove();

            const $toast = $('<div class="ss-toast">').text(message);
            $('body').append($toast);

            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    }

    /**
     * Initialize all charts on page load with lazy loading (Intersection Observer)
     */
    $(document).ready(function() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        new ChartManager(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '200px' });

            $('.ss-chart-container').each(function() {
                observer.observe(this);
            });
        } else {
            // Fallback para navegadores sin IntersectionObserver
            $('.ss-chart-container').each(function() {
                new ChartManager(this);
            });
        }
    });

    // Expose for external use
    window.SSChartManager = ChartManager;
    window.SSNumberFormatter = NumberFormatter;

})(jQuery);
