/**
 * Josra Shopping Feed — back-office: buscador de categorías de Google, copiar URLs,
 * campos de envío según el modo y aviso mientras se genera el feed. Sin dependencias.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
  }

  /* ---------- buscador de categorías de Google (lista propia: la del navegador filtra sin acentos) ---------- */
  function esc(t) {
    var d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
  }

  function taxonomy() {
    var form = document.getElementById('jss-cats-form');
    if (!form) { return; }
    var url = form.getAttribute('data-jss-ajax');
    var taxFile = form.getAttribute('data-jss-tax-file');
    var box = document.createElement('div');
    box.className = 'list-group jss-suggest';
    box.style.cssText = 'position:absolute;left:8px;right:8px;top:100%;z-index:1050;max-height:320px;overflow:auto;margin:0;box-shadow:0 4px 12px rgba(0,0,0,.18);display:none;background:#fff';
    var current = null;
    var active = -1;
    var timer = null;
    var cache = {};
    var lastQ = '';

    function hide() {
      box.style.display = 'none';
      active = -1;
    }

    function choose(value) {
      if (current) {
        current.value = value;
        current.dispatchEvent(new Event('change', { bubbles: true }));
      }
      hide();
    }

    function render(rows, q) {
      if (!current) { return; }
      current.parentNode.appendChild(box);
      var html = '';
      rows.forEach(function (r) {
        html += '<a href="#" class="list-group-item" data-value="' + esc(r.label) + '">' + esc(r.label) + '</a>';
      });
      if (!rows.length) {
        html = '<div class="list-group-item">No hay resultados para «' + esc(q) + '». Prueba con otra palabra o búscala en la ' +
          '<a href="' + esc(taxFile) + '" target="_blank" rel="noopener">lista completa de Google</a> y escribe aquí su número.</div>';
      }
      box.innerHTML = html;
      box.style.display = '';
      active = -1;
    }

    function search(q) {
      lastQ = q;
      if (cache[q]) { render(cache[q], q); return; }
      fetch(url + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (rows) { cache[q] = rows || []; if (q === lastQ) { render(cache[q], q); } })
        .catch(function () { /* sin sugerencias */ });
    }

    form.addEventListener('input', function (e) {
      var el = e.target;
      if (!el.classList || !el.classList.contains('jss-gcat')) { return; }
      current = el;
      var q = String(el.value || '').trim();
      clearTimeout(timer);
      if (q.length < 2 || /^\d+\s-\s/.test(q)) { hide(); return; }
      timer = setTimeout(function () { search(q); }, 250);
    });

    form.addEventListener('keydown', function (e) {
      if (!e.target.classList || !e.target.classList.contains('jss-gcat') || box.style.display === 'none') { return; }
      var items = box.querySelectorAll('a.list-group-item');
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!items.length) { return; }
        active = e.key === 'ArrowDown' ? Math.min(items.length - 1, active + 1) : Math.max(0, active - 1);
        Array.prototype.forEach.call(items, function (it, i) { it.classList.toggle('active', i === active); });
        items[active].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (active >= 0 && items[active]) { choose(items[active].getAttribute('data-value')); }
      } else if (e.key === 'Escape') {
        hide();
      }
    });

    // mousedown: en PS 9 con click la lista se cierra antes de elegir
    box.addEventListener('mousedown', function (e) {
      var a = e.target.closest('a.list-group-item');
      if (!a) { return; }
      e.preventDefault();
      choose(a.getAttribute('data-value'));
    });

    form.addEventListener('focusout', function (e) {
      if (e.target.classList && e.target.classList.contains('jss-gcat')) {
        setTimeout(hide, 150);
      }
    });
  }

  /* ---------- árbol de categorías: desplegar y plegar ---------- */
  function tree() {
    var table = document.getElementById('jss-cats');
    if (!table) { return; }
    var rows = Array.prototype.slice.call(table.querySelectorAll('tr[data-jss-id]'));

    function children(id) {
      return rows.filter(function (r) { return r.getAttribute('data-jss-parent') === String(id); });
    }
    function setIcon(id, open) {
      var t = table.querySelector('[data-jss-toggle="' + id + '"] i');
      if (t) { t.className = open ? 'icon-caret-down' : 'icon-caret-right'; }
    }
    function close(id) {
      children(id).forEach(function (r) {
        r.style.display = 'none';
        close(r.getAttribute('data-jss-id'));
      });
      setIcon(id, false);
    }
    function open(id) {
      children(id).forEach(function (r) { r.style.display = ''; });
      setIcon(id, true);
    }

    table.addEventListener('click', function (e) {
      var t = e.target.closest('[data-jss-toggle]');
      if (!t) { return; }
      e.preventDefault();
      var id = t.getAttribute('data-jss-toggle');
      var isOpen = t.querySelector('i').className === 'icon-caret-down';
      if (isOpen) { close(id); } else { open(id); }
    });

    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-jss-tree]');
      if (!b) { return; }
      e.preventDefault();
      var all = b.getAttribute('data-jss-tree') === 'open';
      rows.forEach(function (r) {
        if (r.getAttribute('data-jss-depth') !== '1') { r.style.display = all ? '' : 'none'; }
        setIcon(r.getAttribute('data-jss-id'), all);
      });
    });

    // Se despliegan solas las ramas que tienen algo asignado por debajo
    rows.forEach(function (r) {
      var input = r.querySelector('.jss-gcat');
      var sel = r.querySelector('select[name^="jss_export"]');
      var own = (input && input.value) || (sel && sel.value !== '');
      if (!own) { return; }
      var parent = r.getAttribute('data-jss-parent');
      var chain = [];
      while (parent) {
        var pr = table.querySelector('tr[data-jss-id="' + parent + '"]');
        if (!pr) { break; }
        chain.unshift(parent);
        parent = pr.getAttribute('data-jss-parent');
      }
      chain.forEach(open);
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
    tree();
    copy();
    shipping();
    busy();
  });
})();
