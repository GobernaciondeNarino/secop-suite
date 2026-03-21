/**
 * SECOP Data Visualizer - Admin JavaScript
 * 
 * @package SecopDataVisualizer
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Chart Config Manager
     */
    const ChartConfigManager = {
        columns: [],

        init: function() {
            this.bindEvents();
            this.initColorPreview();
            
            // Load columns if table is already selected
            const selectedTable = $('#ss_table_name').val();
            if (selectedTable) {
                this.loadColumns(selectedTable);
            }

            // Restore saved values
            this.restoreSavedConfig();
        },

        bindEvents: function() {
            const self = this;

            // Chart type selection
            $('.ss-chart-type-option').on('click', function() {
                $('.ss-chart-type-option').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input').prop('checked', true);
            });

            // Table selection
            $('#ss_table_name').on('change', function() {
                const table = $(this).val();
                if (table) {
                    self.loadColumns(table);
                    $('#ss-fields-section, #ss-filters-section').show();
                } else {
                    $('#ss-fields-section, #ss-filters-section').hide();
                }
            });

            // X Field selection - show date grouping if date field
            $('#ss_x_field').on('change', function() {
                self.checkDateGroupingVisibility();
            });

            // Color preview
            $('#ss_colors').on('input', function() {
                self.updateColorPreview($(this).val());
            });

            // Toolbar toggle
            $('#ss_show_toolbar').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.ss-toolbar-options').show();
                } else {
                    $('.ss-toolbar-options').hide();
                }
            });

            // Custom query toggle
            $('#ss_use_custom_query').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.ss-custom-query-row').show();
                } else {
                    $('.ss-custom-query-row').hide();
                }
            });

            // Advanced section toggle
            $('.ss-toggle-advanced').on('click', function() {
                const $content = $(this).closest('.ss-config-section').find('.ss-advanced-content');
                $content.slideToggle();
                $(this).text($content.is(':visible') ? secopSuiteChartAdmin.strings.collapse || 'Colapsar' : secopSuiteChartAdmin.strings.expand || 'Expandir');
            });

            // Add filter
            $('#ss-add-filter').on('click', function() {
                self.addFilterRow();
            });

            // Remove filter
            $(document).on('click', '.ss-remove-filter', function() {
                $(this).closest('.ss-filter-row').remove();
                self.reindexFilters();
            });

            // Preview button
            $('#ss-refresh-preview').on('click', function() {
                self.refreshPreview();
            });

            // Copy shortcode
            $('.ss-copy-shortcode').on('click', function() {
                const text = $('#ss-shortcode-display').text();
                navigator.clipboard.writeText(text).then(function() {
                    const $btn = $('.ss-copy-shortcode');
                    const originalText = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes"></span> Copiado');
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                });
            });
        },

        checkDateGroupingVisibility: function() {
            const $xField = $('#ss_x_field');
            const selectedOption = $xField.find('option:selected');
            const fieldType = selectedOption.data('type') || '';
            
            // Check if it's a date/datetime field
            const isDateField = fieldType.includes('date') || fieldType.includes('time');
            
            if (isDateField) {
                $('.ss-date-grouping-row').slideDown();
            } else {
                $('.ss-date-grouping-row').slideUp();
                $('#ss_x_date_grouping').val('');
            }
        },

        loadColumns: function(table) {
            const self = this;
            const $selects = $('.ss-column-select');
            
            $selects.prop('disabled', true);
            
            $.ajax({
                url: secopSuiteChartAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_get_table_columns',
                    nonce: secopSuiteChartAdmin.nonce,
                    table: table
                },
                success: function(response) {
                    if (response.success) {
                        self.columns = response.data.columns;
                        self.populateColumnSelects();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(secopSuiteChartAdmin.strings.selectTable);
                },
                complete: function() {
                    $selects.prop('disabled', false);
                }
            });
        },

        populateColumnSelects: function() {
            const self = this;
            const $selects = $('.ss-column-select');
            
            $selects.each(function() {
                const $select = $(this);
                const currentValue = $select.val() || $select.data('saved-value');
                const $firstOption = $select.find('option:first');
                
                $select.empty().append($firstOption);
                
                self.columns.forEach(function(col) {
                    const isNumeric = col.type.includes('int') || col.type.includes('decimal') || col.type.includes('float') || col.type.includes('double');
                    const isDate = col.type.includes('date') || col.type.includes('time');
                    
                    let label = col.name;
                    if (isNumeric) label += ' (numérico)';
                    if (isDate) label += ' (fecha)';
                    
                    $select.append(
                        $('<option>')
                            .val(col.name)
                            .text(label)
                            .data('type', col.type)
                    );
                });
                
                // Restore value
                if (currentValue) {
                    $select.val(currentValue);
                }
            });

            // Check date grouping visibility after populating
            this.checkDateGroupingVisibility();
        },

        addFilterRow: function() {
            const index = $('#ss-filters-container .ss-filter-row').length;
            const template = $('#ss-filter-template').html().replace(/{{index}}/g, index);
            const $row = $(template);
            
            $('#ss-filters-container').append($row);
            
            // Populate the new select
            this.populateFilterSelect($row.find('.ss-column-select'));
        },

        populateFilterSelect: function($select) {
            const self = this;
            const $firstOption = $select.find('option:first');
            
            $select.empty().append($firstOption);
            
            self.columns.forEach(function(col) {
                $select.append(
                    $('<option>')
                        .val(col.name)
                        .text(col.name)
                );
            });
        },

        reindexFilters: function() {
            $('#ss-filters-container .ss-filter-row').each(function(index) {
                $(this).find('[name]').each(function() {
                    const name = $(this).attr('name');
                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
        },

        initColorPreview: function() {
            const colors = $('#ss_colors').val();
            if (colors) {
                this.updateColorPreview(colors);
            }
        },

        updateColorPreview: function(colorsStr) {
            const $preview = $('#ss-color-preview');
            $preview.empty();
            
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => c);
            
            colors.forEach(function(color) {
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    $preview.append(
                        $('<div class="ss-color-swatch">').css('background-color', color)
                    );
                }
            });
        },

        refreshPreview: function() {
            const self = this;
            const $preview = $('#ss-chart-preview');
            const $button = $('#ss-refresh-preview');
            
            // Collect form data
            const formData = {
                action: 'secop_suite_preview_data',
                nonce: secopSuiteChartAdmin.nonce,
                chart_type: $('input[name="ss_chart_type"]:checked').val(),
                table_name: $('#ss_table_name').val(),
                x_field: $('#ss_x_field').val(),
                x_date_grouping: $('#ss_x_date_grouping').val(),
                y_field: $('#ss_y_field').val(),
                group_by: $('#ss_group_by').val(),
                aggregate: $('#ss_aggregate').val(),
                color_field: $('#ss_color_field').val(),
                date_field: $('#ss_date_field').val(),
                date_from: $('#ss_date_from').val(),
                date_to: $('#ss_date_to').val(),
                limit: $('#ss_limit').val() || 100,
                filters: []
            };
            
            // Collect filters
            $('#ss-filters-container .ss-filter-row').each(function() {
                const field = $(this).find('.ss-filter-field').val();
                if (field) {
                    formData.filters.push({
                        field: field,
                        operator: $(this).find('.ss-filter-operator').val(),
                        value: $(this).find('.ss-filter-value').val()
                    });
                }
            });
            
            if (!formData.table_name || !formData.x_field || !formData.y_field) {
                $preview.html('<p class="ss-preview-placeholder">Por favor configure la tabla, eje X y eje Y</p>');
                return;
            }
            
            $button.prop('disabled', true).addClass('updating-message');
            $preview.html('<p class="ss-preview-placeholder">Cargando vista previa...</p>');
            
            $.ajax({
                url: secopSuiteChartAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success && response.data.data.length > 0) {
                        self.renderPreview(response.data.data, formData);
                    } else {
                        $preview.html('<p class="ss-preview-placeholder">No hay datos para mostrar</p>');
                    }
                },
                error: function() {
                    $preview.html('<p class="ss-preview-placeholder">' + secopSuiteChartAdmin.strings.previewError + '</p>');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('updating-message');
                }
            });
        },

        renderPreview: function(data, config) {
            const $preview = $('#ss-chart-preview');
            $preview.empty();
            
            const chartData = data.map(function(d) {
                return {
                    x: d.x_value,
                    y: parseFloat(d.y_value) || 0,
                    group: d.group_value || d.x_value
                };
            });
            
            const colorsStr = $('#ss_colors').val() || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3';
            const colors = colorsStr.split(',').map(c => c.trim());
            const groups = [...new Set(chartData.map(d => d.group))];
            const colorScale = d3.scaleOrdinal().domain(groups).range(colors);
            
            const chartType = config.chart_type || 'bar';
            let chart;
            
            switch (chartType) {
                case 'bar':
                    chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group || d.x); })
                        .yConfig({
                            title: $('#ss_y_axis_title').val() || 'Valor',
                            tickFormat: function(d) {
                                if (d >= 1e9) return (d / 1e9).toFixed(1) + 'MMll';
                                if (d >= 1e6) return (d / 1e6).toFixed(1) + 'M';
                                if (d >= 1e3) return (d / 1e3).toFixed(1) + 'K';
                                return d;
                            }
                        })
                        .legend(false)
                        .locale('es_ES');
                    break;
                    
                case 'line':
                    chart = new d3plus.LinePlot()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group); })
                        .legend(false)
                        .locale('es_ES');
                    break;
                    
                case 'pie':
                    chart = new d3plus.Pie()
                        .data(chartData)
                        .groupBy('x')
                        .value('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .legend(true)
                        .locale('es_ES');
                    break;
                    
                case 'treemap':
                    chart = new d3plus.Treemap()
                        .data(chartData)
                        .groupBy('x')
                        .sum('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .locale('es_ES');
                    break;

                case 'tree':
                    var treeData = chartData.map(function(d) {
                        return { id: d.x, value: d.y, parent: d.group !== d.x ? d.group : null };
                    });
                    var existingIds = treeData.map(function(d) { return d.id; });
                    var parentIds = [...new Set(treeData.filter(function(d) { return d.parent; }).map(function(d) { return d.parent; }))];
                    parentIds.forEach(function(p) {
                        if (existingIds.indexOf(p) === -1) {
                            treeData.push({ id: p, value: 0, parent: null });
                        }
                    });
                    chart = new d3plus.Tree()
                        .data(treeData)
                        .groupBy('id')
                        .layoutPadding(2)
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.id); })
                        .locale('es_ES');
                    break;

                case 'pack':
                    chart = new d3plus.Pack()
                        .data(chartData.map(function(d) { return { id: d.x, value: d.y, group: d.group }; }))
                        .groupBy('id')
                        .sum('value')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group || d.id); })
                        .locale('es_ES');
                    break;

                case 'network':
                    var netNodes = [];
                    var netLinks = [];
                    var netNodeIds = {};
                    chartData.forEach(function(d) {
                        if (!netNodeIds[d.x]) {
                            netNodeIds[d.x] = true;
                            netNodes.push({ id: d.x, value: d.y });
                        }
                        if (d.group && d.group !== d.x) {
                            if (!netNodeIds[d.group]) {
                                netNodeIds[d.group] = true;
                                netNodes.push({ id: d.group, value: 0 });
                            }
                            netLinks.push({ source: d.group, target: d.x });
                        }
                    });
                    chart = new d3plus.Network()
                        .data(netNodes)
                        .links(netLinks)
                        .groupBy('id')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.id); })
                        .locale('es_ES');
                    break;

                default:
                    chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('x')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .legend(false)
                        .locale('es_ES');
            }
            
            chart.render();
        },

        restoreSavedConfig: function() {
            if (typeof ssSavedConfig !== 'undefined' && ssSavedConfig) {
                // Store saved values for later restoration
                if (ssSavedConfig.x_field) {
                    $('#ss_x_field').data('saved-value', ssSavedConfig.x_field);
                }
                if (ssSavedConfig.x_date_grouping) {
                    $('#ss_x_date_grouping').data('saved-value', ssSavedConfig.x_date_grouping);
                }
                if (ssSavedConfig.y_field) {
                    $('#ss_y_field').data('saved-value', ssSavedConfig.y_field);
                }
                if (ssSavedConfig.group_by) {
                    $('#ss_group_by').data('saved-value', ssSavedConfig.group_by);
                }
                if (ssSavedConfig.color_field) {
                    $('#ss_color_field').data('saved-value', ssSavedConfig.color_field);
                }
                if (ssSavedConfig.date_field) {
                    $('#ss_date_field').data('saved-value', ssSavedConfig.date_field);
                }
                if (ssSavedConfig.order_by) {
                    $('#ss_order_by').data('saved-value', ssSavedConfig.order_by);
                }

                // Restore filter field values
                if (ssSavedConfig.filters && ssSavedConfig.filters.length > 0) {
                    ssSavedConfig.filters.forEach(function(filter, index) {
                        const $row = $('#ss-filters-container .ss-filter-row').eq(index);
                        if ($row.length) {
                            $row.find('.ss-filter-field').data('saved-value', filter.field);
                        }
                    });
                }

                // Restore x_date_grouping value after columns load
                if (ssSavedConfig.x_date_grouping) {
                    $('#ss_x_date_grouping').val(ssSavedConfig.x_date_grouping);
                    if (ssSavedConfig.x_date_grouping !== '') {
                        $('.ss-date-grouping-row').show();
                    }
                }
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.ss-config-wrapper').length) {
            ChartConfigManager.init();
        }
    });

})(jQuery);
