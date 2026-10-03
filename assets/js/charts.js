/* CampusCoin — Chart.js helpers (colours, defaults, builders, PNG export). */
(function () {
  'use strict';
  var CC = (window.CC = window.CC || {});
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var COLORS = { emerald: '#10B981', coral: '#EF4444', navy: '#0B1B3A', blue: '#3B82F6', amber: '#F59E0B', slate: '#94A3B8', purple: '#8B5CF6' };
  var PALETTE = ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6', '#EF4444', '#14B8A6', '#EC4899', '#6366F1', '#F97316', '#0EA5E9', '#84CC16', '#64748B'];
  var instances = {};

  function money(v) { return 'Rs. ' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 }); }
  function compact(v) { return Math.abs(v) >= 1000 ? (v / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 }) + 'k' : String(v); }
  function rgba(hex, a) { var n = parseInt(hex.slice(1), 16); return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')'; }

  function setup() {
    if (typeof Chart === 'undefined') return false;
    Chart.defaults.font.family = '"Plus Jakarta Sans", system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#64748B';
    Chart.defaults.animation = reduceMotion ? false : { duration: 900, easing: 'easeOutQuart' };
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0B1B3A';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };
    return true;
  }

  CC.charts = {
    money: money,
    fail: function (canvas) {
      var box = canvas.parentNode;
      box.innerHTML = '<div class="chart-fallback">Charts could not load. Check your internet connection (Chart.js is loaded from a CDN) or see the README for offline setup.</div>';
    },
    build: function (spec) {
      var canvas = document.getElementById(spec.id);
      if (!canvas) return null;
      if (!setup()) { CC.charts.fail(canvas); return null; }
      var type = spec.type, isDonut = type === 'doughnut', isArea = type === 'area', isH = type === 'hbar';
      var hasData = spec.datasets.some(function (d) { return d.data.some(function (v) { return Number(v) !== 0; }); });
      if (!hasData) {
        canvas.parentNode.innerHTML = '<div class="chart-fallback">No data for this period yet.</div>';
        return null;
      }
      var datasets = spec.datasets.map(function (d, i) {
        var col = COLORS[d.color] || d.color || PALETTE[i % PALETTE.length];
        if (isDonut) return { data: d.data, backgroundColor: spec.colors || PALETTE, borderWidth: 3, borderColor: '#fff', hoverOffset: 8 };
        var base = { label: d.label, data: d.data, borderColor: col, backgroundColor: col };
        if (type === 'bar' || isH) { base.borderRadius = 8; base.borderSkipped = false; base.maxBarThickness = 38; base.backgroundColor = col; }
        if (type === 'line' || isArea) {
          base.tension = 0.38; base.borderWidth = 2.5; base.pointRadius = 3; base.pointHoverRadius = 6; base.pointBackgroundColor = '#fff'; base.pointBorderWidth = 2;
          base.fill = isArea || d.fill; base.backgroundColor = rgba(col, isArea ? 0.16 : 0.1);
        }
        if (d.colors) base.backgroundColor = d.colors;
        return base;
      });
      var cfg = {
        type: isArea ? 'line' : (isH ? 'bar' : type),
        data: { labels: spec.labels, datasets: datasets },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: isDonut || datasets.length > 1, position: isDonut ? 'bottom' : 'top', align: isDonut ? 'center' : 'end' },
            tooltip: { callbacks: { label: function (c) { var v = isDonut ? c.parsed : (isH ? c.parsed.x : c.parsed.y); return ' ' + (c.dataset.label ? c.dataset.label + ': ' : c.label + ': ') + money(v); } } }
          }
        }
      };
      if (isDonut) {
        cfg.options.cutout = '68%'; cfg.options.interaction = { mode: 'nearest', intersect: true };
      } else {
        var valAxis = { beginAtZero: true, grid: { color: '#EEF2F7' }, border: { display: false }, ticks: { callback: function (v) { return compact(v); } } };
        var catAxis = { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: spec.maxTicks || 12 } };
        cfg.options.scales = isH ? { x: valAxis, y: catAxis } : { x: catAxis, y: valAxis };
        if (isH) cfg.options.indexAxis = 'y';
      }
      if (instances[spec.id]) instances[spec.id].destroy();
      return (instances[spec.id] = new Chart(canvas, cfg));
    },
    /* Download a chart as a PNG (white background). */
    download: function (id, name) {
      var canvas = document.getElementById(id); if (!canvas) return;
      var tmp = document.createElement('canvas'); tmp.width = canvas.width; tmp.height = canvas.height;
      var ctx = tmp.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, tmp.width, tmp.height); ctx.drawImage(canvas, 0, 0);
      var a = document.createElement('a'); a.href = tmp.toDataURL('image/png'); a.download = (name || id) + '.png'; a.click();
    }
  };
})();
