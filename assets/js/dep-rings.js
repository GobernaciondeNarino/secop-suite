(function ($) {
  'use strict';

  // ── v5.4.1: Red ego de contratación — d3plus.Rings ─────────────────────────
  // Grafo concéntrico centrado en UNA dependencia: el nodo central es la
  // dependencia elegida (o la de mayor valor si no se elige ninguna) y sus
  // conexiones se disponen en anillos automáticamente. Reutiliza network_data a
  // través del endpoint AJAX secop_dep_network. d3plus.Rings renderiza HTML en
  // los tooltips → todas las cadenas de BD se escapan con escapeHtml().

  var COLORS = {
    dependencia: '#0080c3',
    contratista: '#844e80',
    tipo:        '#3eba6a',
    modalidad:   '#ff7300'
  };

  function colorByType(type) {
    return COLORS[type] || '#999999';
  }

  function money(v) {
    return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO');
  }

  // Escapa cadenas para inserción segura en el HTML del tooltip de d3plus.
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // d3plus envuelve cada dato en un objeto { __d3plus__: true, data: <nodo> } al
  // pasarlo a los accesores. unwrap() devuelve el nodo original en ambos casos.
  function unwrap(d) {
    return (d && d.__d3plus__ && d.data) ? d.data : d;
  }

  // Filas del tooltip (array de [encabezado, valor]) según el tipo de nodo.
  function tooltipRows(d) {
    if (d.type === 'contratista') {
      return [
        ['Contratos', escapeHtml(d.count || 0)],
        ['Valor', escapeHtml(money(d.value))],
        ['Dependencia', escapeHtml(d.dependencia || '—')]
      ];
    }
    if (d.type === 'dependencia') {
      return [
        ['Contratos', escapeHtml(d.count || 0)],
        ['Valor', escapeHtml(money(d.value))]
      ];
    }
    if (d.type === 'tipo') {
      return [['Tipo de contrato', escapeHtml(d.label)]];
    }
    if (d.type === 'modalidad') {
      return [['Modalidad', escapeHtml(d.label)]];
    }
    return [['', escapeHtml(d.label)]];
  }

  // Elige el id del nodo central: la dependencia seleccionada (si existe en los
  // datos) o, en su defecto, la dependencia con mayor valor.
  function pickCenter(nodes, dependencia) {
    var byId = {};
    var best = null;
    nodes.forEach(function (n) {
      byId[n.id] = n;
      if (n.type === 'dependencia' && (!best || (+n.value || 0) > (+best.value || 0))) {
        best = n;
      }
    });
    if (dependencia) {
      var wanted = 'dep::' + dependencia;
      if (byId[wanted]) return wanted;
    }
    return best ? best.id : null;
  }

  function renderRings(wrapper) {
    var $wrap = $(wrapper);
    var $chart = $wrap.find('.ss-rings-chart');

    if (!window.d3plus || typeof window.d3plus.Rings !== 'function') {
      $chart.empty().text('No se pudo cargar la librería de visualización (d3plus.Rings).');
      return;
    }

    var dependencia = $wrap.attr('data-dependencia') || '';

    $chart.empty().append(
      $('<div>', { 'class': 'ss-rings-loading', text: 'Cargando red…' })
    );

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_network',
      nonce: secopDep.nonce,
      dependencia: dependencia,
      limit: 0
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $chart.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
        return;
      }
      draw($wrap, $chart, res.data, dependencia);
    }).fail(function () {
      $chart.empty().text((secopDep.strings && secopDep.strings.error) || 'Error al cargar los datos.');
    });
  }

  function draw($wrap, $chart, data, dependencia) {
    var nodes = (data.nodes || []).map(function (n) { return Object.assign({}, n); });
    var links = (data.links || []).map(function (l) { return { source: l.source, target: l.target }; });

    if (!nodes.length) {
      $chart.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
      return;
    }

    var centerId = pickCenter(nodes, dependencia);
    if (!centerId) {
      $chart.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
      return;
    }

    $chart.empty();

    var height = Math.max(360, parseInt($wrap.css('min-height'), 10) || 560);

    // Reutiliza una sola instancia de Rings por contenedor (evita duplicados).
    var target = $chart.get(0);
    new window.d3plus.Rings()
      .select(target)
      .height(height)
      .nodes(nodes)
      .links(links)
      .center(centerId)
      .label(function (d) { d = unwrap(d); return d.label; })
      .size(function (d) { d = unwrap(d); return Number(d.value) || 1; })
      .shapeConfig({
        fill: function (d) { d = unwrap(d); return d.color || colorByType(d.type); }
      })
      .tooltipConfig({
        title: function (d) { d = unwrap(d); return escapeHtml(d.label); },
        tbody: function (d) { d = unwrap(d); return tooltipRows(d); }
      })
      .render();
  }

  $(function () {
    $('.ss-rings-wrapper').each(function () { renderRings(this); });

    // Selector de dependencia central: recentra/recarga.
    $(document).on('change', '.ss-rings-selector', function () {
      var dep = $(this).val();
      var $wrap = $(this).closest('.ss-rings-module').find('.ss-rings-wrapper');
      $wrap.attr('data-dependencia', dep);
      renderRings($wrap.get(0));
    });
  });
})(jQuery);
