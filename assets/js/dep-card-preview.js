/**
 * SECOP Suite — Vista previa en vivo del editor de cards de Contratación.
 *
 * Recolecta los valores actuales del formulario de configuración, solicita por
 * AJAX (secop_dep_preview) los datos + el SQL generado, y renderiza la gráfica
 * reutilizando el motor compartido window.SSChartRender (frontend.js).
 *
 * @package SecopSuite
 */
(function($) {
    'use strict';

    if (typeof secopDepPreview === 'undefined') {
        return;
    }

    var SEL = {
        dimension:   '#dep_dimension',
        chartType:   '[name="dep_chart_type"]',
        metric:      '[name="dep_metric"]',
        dependencia: '[name="dep_dependencia"]',
        order:       '[name="dep_order"]',
        orderDir:    '[name="dep_order_dir"]',
        colors:      '[name="dep_colors"]',
        limit:       '[name="dep_limit"]'
    };

    /**
     * Traduce la config (snake_case) que devuelve card_to_chart_config a las
     * claves camelCase que espera SSChartRender (igual que chart.php).
     */
    function toRenderConfig(cfg) {
        cfg = cfg || {};
        return {
            colors:         cfg.colors || undefined,
            showLegend:     !!cfg.show_legend,
            legendMode:     cfg.legend_mode || 'text',
            legendPosition: cfg.legend_position || 'bottom',
            showTimeline:   !!cfg.show_timeline,
            yAxisTitle:     cfg.y_axis_title || '',
            xAxisTitle:     cfg.x_axis_title || '',
            numberFormat:   cfg.number_format || 'colombiano',
            multiY:         !!(cfg.y_fields && cfg.y_fields.length)
        };
    }

    function collect() {
        return {
            dimension:   $(SEL.dimension).val()   || 'dependencia',
            chart_type:  $(SEL.chartType).val()    || '',
            metric:      $(SEL.metric).val()       || 'valordebito',
            dependencia: $(SEL.dependencia).val()  || '',
            order:       $(SEL.order).val()        || 'valor',
            order_dir:   $(SEL.orderDir).val()     || 'DESC',
            limit:       parseInt($(SEL.limit).val(), 10) || 0,
            colors:      $(SEL.colors).val()       || ''
        };
    }

    function renderDataTable(rows) {
        var $wrap = $('#ss-dep-preview-data');
        $wrap.empty();
        if (!rows || rows.length === 0) {
            $wrap.append($('<p>').text(secopDepPreview.strings.noData));
            return;
        }
        var headers = Object.keys(rows[0]);
        var $table = $('<table class="widefat striped">');
        var $thead = $('<thead>');
        var $hr = $('<tr>');
        headers.forEach(function(h) { $hr.append($('<th>').text(h.replace(/_/g, ' '))); });
        $thead.append($hr);
        $table.append($thead);
        var $tbody = $('<tbody>');
        rows.forEach(function(row) {
            var $tr = $('<tr>');
            headers.forEach(function(h) {
                // Siempre .text() — nunca innerHTML de valores de BD.
                $tr.append($('<td>').text(row[h] === null || row[h] === undefined ? '' : row[h]));
            });
            $tbody.append($tr);
        });
        $table.append($tbody);
        $wrap.append($table);
    }

    function refresh() {
        var payload = collect();
        payload.action = 'secop_dep_preview';
        payload.nonce = secopDepPreview.nonce;

        var $sql = $('#ss-dep-preview-sql');
        $sql.text(secopDepPreview.strings.loading);

        $.ajax({
            url: secopDepPreview.ajaxUrl,
            type: 'POST',
            data: payload,
            success: function(response) {
                if (!response || !response.success) {
                    $sql.text((response && response.data && response.data.message) || secopDepPreview.strings.error);
                    return;
                }
                var res = response.data;
                var chartType = payload.chart_type || (res.config && res.config.chart_type) || 'bar';

                // Gráfica
                var $render = $('#ss-dep-preview-render');
                $render.empty();
                if (typeof window.SSChartRender !== 'function' || typeof window.d3plus === 'undefined') {
                    $render.append($('<p>').text(
                        'No se cargó el motor de gráficas (d3plus). Revise que el plugin esté actualizado y recargue con Ctrl+F5. Los datos y la consulta SQL se muestran abajo.'
                    ));
                } else {
                    try {
                        window.SSChartRender('#ss-dep-preview-render', chartType, res.data, toRenderConfig(res.config));
                    } catch (e) {
                        $render.append($('<p>').text('Error al renderizar la gráfica: ' + (e && e.message ? e.message : e)));
                        /* eslint-disable-next-line no-console */
                        console.error('SECOP dep preview render error:', e);
                    }
                }

                // Datos
                renderDataTable(res.data);

                // SQL (escapado vía .text())
                $sql.text(res.sql || '');
            },
            error: function() {
                $sql.text(secopDepPreview.strings.error);
            }
        });
    }

    var debounceTimer = null;
    function debouncedRefresh() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, 400);
    }

    $(function() {
        $('#ss-dep-refresh-preview').on('click', refresh);

        var watch = [
            SEL.dimension, SEL.chartType, SEL.metric, SEL.dependencia,
            SEL.order, SEL.orderDir, SEL.colors, SEL.limit
        ].join(', ');
        $(document).on('change', watch, debouncedRefresh);
        // Refresco inicial.
        refresh();
    });

})(jQuery);
