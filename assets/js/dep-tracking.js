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
})(jQuery);
