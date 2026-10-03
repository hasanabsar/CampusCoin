/* CampusCoin — builds every chart described in #page-data (student, reports and admin pages). */
(function () {
  'use strict';
  var node = document.getElementById('page-data');
  if (!node || !window.CC || !CC.charts) return;
  var data;
  try { data = JSON.parse(node.textContent); } catch (e) { return; }
  (data.charts || []).forEach(function (spec) { CC.charts.build(spec); });

  document.querySelectorAll('[data-chart-download]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      CC.charts.download(btn.getAttribute('data-chart-download'), btn.getAttribute('data-filename'));
    });
  });
})();
