/* CampusCoin — client-side form validation (server validates again — this is UX only). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function setError(field, msg) {
    var wrap = field.closest('.field'); if (!wrap) return;
    var err = wrap.querySelector('.field-error.js');
    if (msg) {
      wrap.classList.add('has-error'); field.setAttribute('aria-invalid', 'true');
      if (!err) { err = document.createElement('p'); err.className = 'field-error js'; err.setAttribute('role', 'alert'); wrap.appendChild(err); }
      err.textContent = msg;
      var server = wrap.querySelector('.field-error:not(.js)'); if (server) server.hidden = true;
    } else {
      wrap.classList.remove('has-error'); field.removeAttribute('aria-invalid');
      if (err) err.remove();
    }
  }
  function message(f) {
    var v = f.value.trim(), label = (f.closest('.field') && f.closest('.field').querySelector('label')) ? f.closest('.field').querySelector('label').firstChild.textContent.trim() : 'This field';
    if (f.required && v === '') return label + ' is required.';
    if (f.type === 'email' && v !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Enter a valid email address.';
    if (f.minLength > 0 && v !== '' && v.length < f.minLength) return label + ' must be at least ' + f.minLength + ' characters.';
    if (f.getAttribute('data-type') === 'amount' && v !== '' && !/^\d{1,9}([.,]\d{1,2})?$|^\d{1,3}(,\d{3})*(\.\d{1,2})?$/.test(v)) return 'Enter a valid amount.';
    if (f.getAttribute('data-type') === 'amount' && v !== '' && parseFloat(v.replace(/,/g, '')) <= 0) return 'Amount must be greater than zero.';
    if (f.hasAttribute('data-password') && v !== '' && (v.length < 8 || !/[A-Za-z]/.test(v) || !/\d/.test(v))) return 'Use 8+ characters with at least one letter and one number.';
    var match = f.getAttribute('data-match');
    if (match) { var other = document.getElementById(match); if (other && v !== other.value) return 'Passwords do not match.'; }
    return '';
  }

  $$('form[data-validate]').forEach(function (form) {
    var fields = $$('input:not([type=hidden]):not([type=checkbox]):not([type=file]), select, textarea', form);
    fields.forEach(function (f) {
      f.addEventListener('blur', function () { if (f.value !== '' || f.required) setError(f, message(f)); });
      f.addEventListener('input', function () { if (f.closest('.has-error')) setError(f, message(f)); });
    });
    form.addEventListener('submit', function (e) {
      var firstBad = null;
      fields.forEach(function (f) { var m = message(f); setError(f, m); if (m && !firstBad) firstBad = f; });
      if (firstBad) { e.preventDefault(); e.stopImmediatePropagation(); firstBad.focus(); }
    }, true);
  });

  /* Password strength meter */
  $$('[data-strength-for]').forEach(function (meter) {
    var input = document.getElementById(meter.getAttribute('data-strength-for'));
    var label = meter.parentNode.querySelector('.strength-label');
    if (!input) return;
    input.addEventListener('input', function () {
      var v = input.value, score = 0;
      if (v.length >= 8) score++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) score++;
      if (v === '') score = 0;
      meter.setAttribute('data-level', String(score));
      if (label) label.textContent = ['Enter a password', 'Weak', 'Fair', 'Good', 'Strong'][score];
    });
  });
})();
