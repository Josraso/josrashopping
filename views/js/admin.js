/**
 * Josra Shopping Feed — back-office: buscador de categorías de Google, copiar URLs,
 * campos de envío según el modo y aviso mientras se genera el feed. Sin dependencias.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
  }

  /* ---------- buscador de categorías de Google ---------- */
  function taxonomy() {
    var form = document.getElementById('jss-cats-form');
    var list = document.getElementById('jss-tax-list');
    if (!form || !list) { return; }
    var url = form.getAttribute('data-jss-ajax');
    var timer = null;
    var last = '';
    var cache = {};

    function fill(rows) {
      list.innerHTML = '';
      rows.forEach(function (r) {
        var o = document.createElement('option');
        o.value = r.label;
        list.appendChild(o);
      });
    }

    function search(q) {
      if (cache[q]) { fill(cache[q]); return; }
      fetch(url + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (rows) { cache[q] = rows || []; if (q === last) { fill(cache[q]); } })
        .catch(function () { /* sin sugerencias */ });
    }

    form.addEventListener('input', function (e) {
      var el = e.target;
      if (!el.classList || !el.classList.contains('jss-gcat')) { return; }
      var q = String(el.value || '').trim();
      // Ya elegida de la lista («5181 - …»): no hace falta buscar
      if (/^\d+\s-\s/.test(q) || q.length < 2) { return; }
      last = q;
      clearTimeout(timer);
      timer = setTimeout(function () { search(q); }, 250);
    });
  }

  /* ---------- copiar URLs ---------- */
  function copy() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-jss-copy]') : null;
      if (!btn) { return; }
      e.preventDefault();
      var input = document.getElementById(btn.getAttribute('data-jss-copy'));
      if (!input) { return; }
      input.select();
      var done = function () {
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="icon-check"></i> Copiada';
        setTimeout(function () { btn.innerHTML = old; }, 1500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
      } else {
        document.execCommand('copy');
        done();
      }
    });
  }

  /* ---------- envío: enseñar solo los campos del modo elegido ---------- */
  function shipping() {
    var radios = document.querySelectorAll('input[name="jss_shipping"]');
    if (!radios.length) { return; }
    var price = document.querySelector('[name="jss_ship_price"]');
    var countries = document.querySelector('[name="jss_ship_countries[]"]');
    function group(el) { return el ? el.closest('.form-group') : null; }
    function update() {
      var mode = (document.querySelector('input[name="jss_shipping"]:checked') || {}).value || 'account';
      var gp = group(price);
      var gc = group(countries);
      if (gp) { gp.style.display = mode === 'fixed' ? '' : 'none'; }
      if (gc) { gc.style.display = mode === 'carriers' ? '' : 'none'; }
    }
    Array.prototype.forEach.call(radios, function (r) { r.addEventListener('change', update); });
    update();
  }

  /* ---------- aviso mientras se genera ---------- */
  function busy() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('[data-jss-busy]') : null;
      if (!el) { return; }
      var text = el.getAttribute('data-jss-busy');
      setTimeout(function () {
        el.classList.add('disabled');
        el.innerHTML = '<i class="icon-refresh icon-spin"></i> ' + text;
      }, 0);
    });
  }

  ready(function () {
    taxonomy();
    copy();
    shipping();
    busy();
  });
})();
