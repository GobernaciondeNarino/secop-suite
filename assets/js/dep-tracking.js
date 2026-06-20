(function ($) {
  'use strict';
  function fmtMoney(v) {
    return '$' + Math.round(v).toLocaleString('es-CO');
  }
  function render(el) {
    var $el = $(el), target = $el.find('.ss-dep-chart-target')[0];
    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_chart_data',
      nonce: secopDep.nonce,
      dimension: $el.data('dimension'),
      dependencia: $el.data('dependencia') || ''
    }).done(function (res) {
      if (!res.success || !res.data.length) { $(target).html('<p>No hay datos.</p>'); return; }
      var type = $el.data('type');
      var data = res.data.map(function (d) {
        return { id: d.label, valor: +d.valor, conteo: +d.conteo };
      });
      var viz = (type === 'treemap') ? new d3plus.Treemap()
              : (type === 'donut' || type === 'pie') ? new d3plus.Donut()
              : (type === 'line' || type === 'area') ? new d3plus.LinePlot()
              : new d3plus.BarChart();
      viz.select(target).data(data).groupBy('id')
        .tooltipConfig({
          title: function (d) { return d.id; },
          tbody: function (d) {
            return [['Valor ejecutado', fmtMoney(d.valor)], ['Contratos', d.conteo]];
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
      $root.find('.ss-dep-chart-wrapper').attr('data-dependencia', dep).each(function () {
        window.secopDepRender(this);
      });
      $.post(secopDep.ajaxUrl, { action: 'secop_dep_contratos', nonce: secopDep.nonce, dependencia: dep })
        .done(function (res) {
          if (!res.success) return;
          var html = res.data.rows.map(function (r) {
            var num = r.url_contrato
              ? '<a href="' + r.url_contrato + '" target="_blank" rel="noopener">' + r.numero_del_contrato + '</a>'
              : r.numero_del_contrato;
            return '<tr><td>' + num + '</td><td>' + (r.nom_raz_social_contratista || '') + '</td><td>' +
              String(r.fecha_inicio_ejecucion || '').slice(0, 10) + '</td><td>' +
              String(r.fecha_fin_ejecucion || '').slice(0, 10) + '</td><td>$' +
              Math.round(r.valor_contrato || 0).toLocaleString('es-CO') + '</td><td>' +
              String(r.objeto_del_proceso || '').slice(0, 120) + '</td></tr>';
          }).join('');
          $root.find('.ss-seguimiento-contratos table tbody').html(html || '<tr><td colspan="6">Sin contratos.</td></tr>');
        });
    });
  });
})(jQuery);
