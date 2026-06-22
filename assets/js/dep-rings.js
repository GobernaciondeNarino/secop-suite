(function ($) {
  'use strict';

  // ── Red ego de contratación — layout radial con d3 (fiable) ────────────────
  // El nodo central es UNA dependencia; sus conexiones (modalidades, tipos y
  // contratistas) se disponen en anillos concéntricos alrededor. Reutiliza
  // network_data vía el endpoint AJAX secop_dep_network. Se renderiza con d3 puro
  // (window.d3) porque d3plus.Rings requiere una configuración frágil y no
  // renderizaba; el resultado visual (anillos concéntricos) es el mismo.

  var COLORS = {
    dependencia: '#0080c3',
    contratista: '#844e80',
    tipo:        '#3eba6a',
    modalidad:   '#ff7300'
  };
  function colorByType(t) { return COLORS[t] || '#999999'; }
  function money(v) { return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO'); }

  // Radio de cada anillo por tipo (modalidades dentro, contratistas fuera).
  var RING_ORDER = ['modalidad', 'tipo', 'contratista'];

  function pickCenter(nodes, dependencia) {
    var byId = {}, best = null;
    nodes.forEach(function (n) {
      byId[n.id] = n;
      if (n.type === 'dependencia' && (!best || (+n.value || 0) > (+best.value || 0))) best = n;
    });
    if (dependencia && byId['dep::' + dependencia]) return 'dep::' + dependencia;
    return best ? best.id : null;
  }

  function renderRings(wrapper) {
    var $wrap = $(wrapper);
    var $chart = $wrap.find('.ss-rings-chart');
    if (!window.d3) { $chart.empty().text('No se pudo cargar la librería de visualización (d3).'); return; }

    var dependencia = $wrap.attr('data-dependencia') || '';
    $chart.empty().append($('<div>', { 'class': 'ss-rings-loading', text: 'Cargando red…' }));

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
    var d3 = window.d3;
    var nodes = data.nodes || [];
    var links = data.links || [];
    var byId = {};
    nodes.forEach(function (n) { byId[n.id] = n; });

    var centerId = pickCenter(nodes, dependencia);
    if (!centerId || !byId[centerId]) {
      $chart.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
      return;
    }
    var center = byId[centerId];

    // Vecinos del centro: nodos enlazados a la dependencia central.
    var neighborIds = {};
    links.forEach(function (l) {
      if (l.target === centerId && byId[l.source]) neighborIds[l.source] = true;
      if (l.source === centerId && byId[l.target]) neighborIds[l.target] = true;
    });
    var neighbors = Object.keys(neighborIds).map(function (id) { return byId[id]; });
    if (!neighbors.length) {
      $chart.empty().text('La dependencia seleccionada no tiene conexiones.');
      return;
    }

    // Agrupar vecinos por tipo y asignar anillos.
    var groups = {};
    neighbors.forEach(function (n) { (groups[n.type] = groups[n.type] || []).push(n); });
    var ringsPresent = RING_ORDER.filter(function (t) { return groups[t] && groups[t].length; });

    $chart.empty();
    var width = Math.max(320, $chart.width() || $wrap.width() || 600);
    var height = Math.max(360, parseInt($wrap.css('min-height'), 10) || 560);
    var cx = width / 2, cy = height / 2;
    var maxR = Math.min(width, height) / 2 - 40;
    var ringGap = ringsPresent.length ? maxR / (ringsPresent.length + 0.5) : maxR;

    var svg = d3.select($chart.get(0)).append('svg')
      .attr('width', '100%')
      .attr('viewBox', '0 0 ' + width + ' ' + height)
      .attr('preserveAspectRatio', 'xMidYMid meet');

    // Posicionar vecinos.
    var positioned = [];
    ringsPresent.forEach(function (type, ri) {
      var r = ringGap * (ri + 1);
      var arr = groups[type];
      arr.forEach(function (n, i) {
        var ang = (i / arr.length) * 2 * Math.PI - Math.PI / 2;
        positioned.push({ node: n, x: cx + r * Math.cos(ang), y: cy + r * Math.sin(ang) });
      });
    });

    // Escala de tamaño por valor (para contratistas/dependencia).
    var maxVal = d3.max(positioned.concat([{ node: center }]).map(function (p) { return +p.node.value || 0; })) || 1;
    var rScale = d3.scaleSqrt().domain([0, maxVal]).range([4, 16]);

    // Enlaces centro → vecino.
    svg.append('g').selectAll('line').data(positioned).enter().append('line')
      .attr('x1', cx).attr('y1', cy)
      .attr('x2', function (p) { return p.x; }).attr('y2', function (p) { return p.y; })
      .attr('stroke', '#d0d0d0').attr('stroke-width', 1);

    // Tooltip.
    var tip = d3.select($chart.get(0)).append('div').attr('class', 'ss-rings-tooltip').style('display', 'none');
    function showTip(ev, n) {
      var html = '<strong>' + esc(n.label) + '</strong>';
      if (n.type === 'contratista') {
        html += '<br>Contratos: ' + esc(n.count || 0) + '<br>Valor: ' + esc(money(n.value)) + '<br>Dependencia: ' + esc(n.dependencia || '—');
      } else if (n.type === 'dependencia') {
        html += '<br>Contratos: ' + esc(n.count || 0) + '<br>Valor: ' + esc(money(n.value));
      } else if (n.type === 'tipo') { html += '<br>Tipo de contrato'; }
      else if (n.type === 'modalidad') { html += '<br>Modalidad'; }
      var off = $chart.offset();
      tip.html(html).style('display', 'block')
        .style('left', (ev.pageX - off.left + 12) + 'px')
        .style('top', (ev.pageY - off.top + 12) + 'px');
    }
    function hideTip() { tip.style('display', 'none'); }

    // Nodos vecinos.
    var g = svg.append('g');
    g.selectAll('circle').data(positioned).enter().append('circle')
      .attr('cx', function (p) { return p.x; }).attr('cy', function (p) { return p.y; })
      .attr('r', function (p) { return (p.node.type === 'contratista') ? rScale(+p.node.value || 0) : 7; })
      .attr('fill', function (p) { return p.node.color || colorByType(p.node.type); })
      .attr('stroke', '#fff').attr('stroke-width', 1)
      .style('cursor', 'pointer')
      .on('mousemove', function (p) { showTip(d3.event, p.node); })
      .on('mouseout', hideTip);

    // Nodo central (la dependencia).
    svg.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 18)
      .attr('fill', center.color || colorByType('dependencia')).attr('stroke', '#fff').attr('stroke-width', 2)
      .style('cursor', 'pointer')
      .on('mousemove', function () { showTip(d3.event, center); }).on('mouseout', hideTip);
    svg.append('text').attr('x', cx).attr('y', cy + 34).attr('text-anchor', 'middle')
      .attr('font-size', '12px').attr('font-weight', 'bold').text(center.label);

    // Leyenda + conteo.
    renderLegend($wrap, ringsPresent, neighbors.length);
  }

  function renderLegend($wrap, ringsPresent, neighborCount) {
    var $leg = $wrap.find('.ss-rings-legend');
    if (!$leg.length) { $leg = $('<div class="ss-rings-legend"></div>').appendTo($wrap); }
    $leg.empty();
    var labels = { dependencia: 'Dependencia (centro)', modalidad: 'Modalidad', tipo: 'Tipo de contrato', contratista: 'Contratista' };
    ['dependencia'].concat(ringsPresent).forEach(function (t) {
      $leg.append($('<span class="ss-rings-leg-item"></span>').append(
        $('<span class="ss-rings-leg-dot"></span>').css('background', colorByType(t)),
        document.createTextNode(' ' + (labels[t] || t))
      ));
    });
    $leg.append($('<span class="ss-rings-caption"></span>').text(neighborCount + ' conexiones'));
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  $(function () {
    $('.ss-rings-wrapper').each(function () { renderRings(this); });
    $(document).on('change', '.ss-rings-selector', function () {
      var dep = $(this).val();
      var $wrap = $(this).closest('.ss-rings-module, .ss-rings-wrapper').find('.ss-rings-wrapper');
      if (!$wrap.length) $wrap = $(this).closest('.ss-rings-wrapper');
      if (!$wrap.length) $wrap = $('.ss-rings-wrapper').first();
      $wrap.attr('data-dependencia', dep);
      renderRings($wrap.get(0));
    });
  });
})(jQuery);
