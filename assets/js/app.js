/* CampusCoin — global behaviour: toasts, modals, dropdowns, sidebar, counters, suggestions. */
(function () {
  'use strict';
  var CC = (window.CC = window.CC || {});
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /* ---------- Toasts ---------- */
  var ICONS = {
    success: '<svg class="t-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    error: '<svg class="t-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    warning: '<svg class="t-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info: '<svg class="t-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
  };
  CC.toast = function (type, message, timeout) {
    var root = $('#toastRoot');
    if (!root) return;
    type = ICONS[type] ? type : 'info';
    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = ICONS[type] + '<div class="t-msg"></div><button type="button" class="t-close" aria-label="Dismiss">&times;</button>';
    el.querySelector('.t-msg').textContent = message;   // textContent → no XSS
    root.appendChild(el);
    var close = function () { el.classList.add('leaving'); setTimeout(function () { el.remove(); }, 260); };
    el.querySelector('.t-close').addEventListener('click', close);
    setTimeout(close, timeout || (type === 'error' ? 7000 : 5000));
  };
  (function flashFromServer() {
    var root = $('#toastRoot');
    if (!root) return;
    try {
      JSON.parse(root.getAttribute('data-flash') || '[]').forEach(function (f, i) {
        setTimeout(function () { CC.toast(f.type, f.message); }, i * 180);
      });
    } catch (e) { /* ignore */ }
  })();

  /* ---------- Modals (native <dialog>) ---------- */
  function fillModal(modal, trigger) {
    $$('form', modal).forEach(function (f) { f.reset(); });
    Object.keys(trigger.dataset).forEach(function (k) {
      if (k.indexOf('fill') !== 0 || k.length < 5) return;
      var key = k.charAt(4).toLowerCase() + k.slice(5);
      $$('[data-bind="' + key + '"]', modal).forEach(function (el) {
        var v = trigger.dataset[k];
        if (/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) {
          if (el.type === 'checkbox') el.checked = v === '1' || v === 'true'; else el.value = v;
        } else { el.textContent = v; }
      });
    });
  }
  function openModal(id, trigger) {
    var m = document.getElementById(id);
    if (!m || typeof m.showModal !== 'function') return;
    if (trigger) fillModal(m, trigger);
    m.showModal();
    var first = m.querySelector('input:not([type=hidden]), select, textarea');
    if (first) setTimeout(function () { first.focus(); }, 60);
  }
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-modal-open]');
    if (open) { e.preventDefault(); openModal(open.getAttribute('data-modal-open'), open); return; }
    var close = e.target.closest('[data-modal-close]');
    if (close) { var d = close.closest('dialog'); if (d) d.close(); return; }
    if (e.target.tagName === 'DIALOG') {           // click on backdrop
      var r = e.target.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
    }
  });

  /* ---------- Confirmation dialog for destructive / important actions ---------- */
  (function confirmDialog() {
    var modal = $('#confirmModal'), ok = $('#confirmOk');
    if (!modal || !ok) return;
    var pendingForm = null;
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-confirm]');
      if (!btn) return;
      e.preventDefault();
      pendingForm = btn.closest('form');
      $('#confirmTitle').textContent = btn.getAttribute('data-confirm-title') || 'Are you sure?';
      $('#confirmText').textContent = btn.getAttribute('data-confirm');
      ok.textContent = btn.getAttribute('data-confirm-label') || 'Confirm';
      var primary = btn.getAttribute('data-confirm-variant') === 'primary';
      ok.className = 'btn ' + (primary ? 'btn-primary' : 'btn-danger');
      $('#confirmIcon').className = 'modal-icon' + (primary ? ' primary' : '');
      modal.showModal();
    });
    ok.addEventListener('click', function () {
      if (pendingForm) { ok.classList.add('is-loading'); pendingForm.submit(); }
    });
    modal.addEventListener('close', function () { ok.classList.remove('is-loading'); });
  })();

  /* ---------- Dropdowns ---------- */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-dropdown-toggle]');
    $$('[data-dropdown]').forEach(function (dd) {
      var menu = dd.querySelector('.dropdown-menu'), t = dd.querySelector('[data-dropdown-toggle]');
      if (toggle && dd.contains(toggle)) {
        var willOpen = menu.hidden; menu.hidden = !willOpen; t.setAttribute('aria-expanded', String(willOpen));
      } else if (!dd.contains(e.target)) { menu.hidden = true; t.setAttribute('aria-expanded', 'false'); }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      $$('[data-dropdown] .dropdown-menu').forEach(function (m) { m.hidden = true; });
      document.body.classList.remove('sidebar-open');
    }
    if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
      var s = $('.topbar-search input'); if (s) { e.preventDefault(); s.focus(); }
    }
  });

  /* ---------- Sidebar + public nav ---------- */
  $$('[data-sidebar-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
  });
  var navToggle = $('#navToggle'), pubNav = $('#publicNav');
  if (navToggle && pubNav) {
    navToggle.addEventListener('click', function () {
      var open = pubNav.classList.toggle('open'); navToggle.setAttribute('aria-expanded', String(open));
    });
    $$('a', pubNav).forEach(function (a) { a.addEventListener('click', function () { pubNav.classList.remove('open'); }); });
  }

  /* ---------- Password show/hide ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle-password]');
    if (!t) return;
    var input = document.getElementById(t.getAttribute('data-toggle-password'));
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    t.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    t.querySelector('.eye-on').hidden = show; t.querySelector('.eye-off').hidden = !show;
  });

  /* ---------- Loading state on submit (only after validation passes) ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.hasAttribute('data-loading') || e.defaultPrevented) return;
    var btn = form.querySelector('button[type="submit"]');
    if (btn) setTimeout(function () { btn.classList.add('is-loading'); }, 0);
  });
  window.addEventListener('pageshow', function () { $$('.btn.is-loading').forEach(function (b) { b.classList.remove('is-loading'); }); });

  /* ---------- Number counters ---------- */
  function fmt(n, dec) { return n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); }
  $$('[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var prefix = el.getAttribute('data-prefix') || '', dec = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var neg = target < 0, abs = Math.abs(target);
    var render = function (v) { el.textContent = (neg ? '-' : '') + prefix + fmt(v, dec); };
    if (reduceMotion || abs === 0) { render(abs); return; }
    var start = null, dur = 900;
    render(0);
    requestAnimationFrame(function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1), eased = 1 - Math.pow(1 - p, 3);
      render(abs * eased);
      if (p < 1) requestAnimationFrame(step); else render(abs);
    });
  });

  /* ---------- Progress bars animate in ---------- */
  $$('.progress-bar[data-width]').forEach(function (b) {
    var w = Math.min(100, parseFloat(b.getAttribute('data-width')) || 0) + '%';
    if (reduceMotion) { b.style.width = w; return; }
    requestAnimationFrame(function () { setTimeout(function () { b.style.width = w; }, 80); });
  });

  /* ---------- Amount inputs: allow only digits, comma, dot ---------- */
  $$('input[data-type="amount"]').forEach(function (i) {
    i.addEventListener('input', function () { i.value = i.value.replace(/[^0-9.,]/g, ''); });
  });

  /* ---------- Category suggestion on the Add Expense form ---------- */
  function postJSON(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  CC.postJSON = postJSON;
  (function suggest() {
    var input = $('[data-suggest-url]'), chip = $('#suggestChip');
    if (!input || !chip) return;
    var select = document.getElementById(input.getAttribute('data-suggest-target')), timer = null, last = '';
    var run = function () {
      var text = input.value.trim();
      if (text.length < 3 || text === last) { if (text.length < 3) chip.hidden = true; return; }
      last = text;
      postJSON(input.getAttribute('data-suggest-url'), { description: text }).then(function (res) {
        if (!res || !res.ok || !res.category) { chip.hidden = true; return; }
        if (select && String(select.value) === String(res.category.id)) { chip.hidden = true; return; }
        chip.innerHTML = '';
        var label = document.createElement('span');
        label.textContent = 'Suggested category: ';
        var strong = document.createElement('strong'); strong.textContent = res.category.name; label.appendChild(strong);
        var accept = document.createElement('button'); accept.type = 'button'; accept.textContent = 'Accept';
        var dismiss = document.createElement('button'); dismiss.type = 'button'; dismiss.className = 'plain'; dismiss.textContent = 'Change';
        accept.addEventListener('click', function () { if (select) select.value = res.category.id; chip.hidden = true; });
        dismiss.addEventListener('click', function () { chip.hidden = true; if (select) select.focus(); });
        chip.appendChild(label); chip.appendChild(accept); chip.appendChild(dismiss); chip.hidden = false;
      }).catch(function () { chip.hidden = true; });
    };
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 450); });
  })();

  /* ---------- AI Insights page: standalone category-suggestion tool ---------- */
  (function aiTool() {
    var form = $('#suggestForm'); if (!form) return;
    var out = $('#suggestResult'), select = $('#suggestChange');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = $('#suggestText').value.trim(); if (!text) return;
      var btn = form.querySelector('button[type="submit"]'); btn.classList.add('is-loading');
      postJSON(form.getAttribute('data-url'), { description: text }).then(function (res) {
        btn.classList.remove('is-loading');
        if (!res.ok || !res.category) { CC.toast('warning', res.error || 'No suggestion available.'); return; }
        out.hidden = false;
        $('#suggestName').textContent = res.category.name;
        $('#suggestSource').textContent = { ai: 'AI', history: 'your history', rules: 'smart rules', fallback: 'default' }[res.source] || res.source;
        var base = form.getAttribute('data-add-url') + '?description=' + encodeURIComponent(text) + '&category_id=';
        $('#suggestAccept').href = base + res.category.id;
        select.value = res.category.id; select.setAttribute('data-base', base);
        $('#suggestChangeGo').href = base + res.category.id;
      }).catch(function () { btn.classList.remove('is-loading'); CC.toast('error', 'Could not fetch a suggestion.'); });
    });
    select.addEventListener('change', function () { $('#suggestChangeGo').href = select.getAttribute('data-base') + select.value; });
    $('#suggestChangeToggle').addEventListener('click', function () { $('#suggestChangeRow').hidden = false; });
  })();

  /* ---------- Print / PDF buttons ---------- */
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  /* ---------- Copy to clipboard ---------- */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.getAttribute('data-copy');
      (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { CC.toast('success', 'Copied to clipboard.', 2500); }, function () { CC.toast('warning', 'Copy failed — select and copy manually.'); });
    });
  });
})();
