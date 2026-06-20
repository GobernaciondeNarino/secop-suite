(function ($) {
  'use strict';
  // FIX 3: helper para escapar HTML en títulos de tooltip (d3plus los renderiza como HTML)
  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function fmtMoney(v) {
    return '$' + Math.round(v).toLocaleString('es-CO');
  }
  function render(el) {
    var $el = $(el), target = $el.find('.ss-dep-chart-target')[0];
    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_chart_data',
      nonce: secopDep.nonce,
      dimension: $el.data('dimension'),
      dependencia: $el.data('dependencia') || ''   // FIX 2: lee del jQuery cache
    }).done(function (res) {
      var rows = res.success && res.data ? res.data.data : null;
      if (!rows || !rows.length) {
        // FIX 6: usa cadena i18n con fallback; construcción DOM (no concatenación)
        $(target).empty().append(
          $('<p>').text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.')
        );
        return;
      }
      var type = $el.data('type');
      var data = rows.map(function (d) {
        return { id: d.label, valor: +d.valor, conteo: +d.conteo };
      });
      var viz = (type === 'treemap') ? new d3plus.Treemap()
              : (type === 'donut' || type === 'pie') ? new d3plus.Donut()
              : (type === 'line' || type === 'area') ? new d3plus.LinePlot()
              : new d3plus.BarChart();
      viz.select(target).data(data).groupBy('id')
        .tooltipConfig({
          // FIX 3: escapa el nombre de dependencia (dato de BD renderizado como HTML por d3plus)
          title: function (d) { return escapeHtml(d.id); },
          // FIX 6: usa cadenas i18n con fallback
          tbody: function (d) {
            return [
              [(secopDep.strings && secopDep.strings.valueLabel) || 'Valor ejecutado', fmtMoney(d.valor)],
              [(secopDep.strings && secopDep.strings.countLabel) || 'Contratos', d.conteo]
            ];
          }
        });
      if (viz.x) { viz.x('id'); }
      if (viz.y) { viz.y('valor'); }
      viz.render();
    });
  }
  $(function () { $('.ss-dep-chart-wrapper').each(function () { render(this); }); });
  window.secopDepRender = render;

  // ── Task 12: interactividad del selector de dependencia ─────────
  $(function () {
    $('.ss-seguimiento').on('change', '.ss-dep-selector', function () {
      var dep = $(this).val(), $root = $(this).closest('.ss-seguimiento');
      // FIX 2: actualiza TANTO el atributo DOM (inspectabilidad) COMO el cache jQuery
      // que render() lee con .data('dependencia')
      $root.find('.ss-dep-chart-wrapper')
        .attr('data-dependencia', dep)
        .data('dependencia', dep)
        .each(function () { window.secopDepRender(this); });

      $.post(secopDep.ajaxUrl, { action: 'secop_dep_contratos', nonce: secopDep.nonce, dependencia: dep })
        .done(function (res) {
          if (!res.success) return;
          var $tbody = $root.find('.ss-seguimiento-contratos table tbody');
          var rows = res.data.rows;

          // FIX 1: estado vacío con construcción DOM segura
          if (!rows || !rows.length) {
            $tbody.empty().append(
              $('<tr>').append(
                $('<td>', { colspan: 6 }).text(
                  (secopDep.strings && secopDep.strings.noContracts) || 'Sin contratos.'
                )
              )
            );
            return;
          }

          // FIX 1: construcción DOM segura — ningún valor de BD se concatena como HTML
          var $rows = rows.map(function (r) {
            var $tr = $('<tr>');

            // Columna número: enlace sólo si la URL tiene esquema http(s) válido
            var $tdNum = $('<td>');
            if (typeof r.url_contrato === 'string' && /^https?:\/\//i.test(r.url_contrato)) {
              $tdNum.append(
                $('<a>', { href: r.url_contrato, target: '_blank', rel: 'noopener' })
                  .text(r.numero_del_contrato)
              );
            } else {
              $tdNum.text(r.numero_del_contrato);
            }
            $tr.append($tdNum);

            $tr.append($('<td>').text(r.nom_raz_social_contratista || ''));
            $tr.append($('<td>').text(String(r.fecha_inicio_ejecucion || '').slice(0, 10)));
            $tr.append($('<td>').text(String(r.fecha_fin_ejecucion || '').slice(0, 10)));
            $tr.append($('<td>').text('$' + Math.round(r.valor_contrato || 0).toLocaleString('es-CO')));
            $tr.append($('<td>').text(String(r.objeto_del_proceso || '').slice(0, 120)));
            return $tr;
          });
          $tbody.empty().append($rows);
        });
    });
  });
})(jQuery);
