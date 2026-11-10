/* Sugerencias de artículos al teclear una referencia o parte de la descripción.
   Uso: artSuggest(input, {tariff: 1, onPick: function(item){...}})  — item = {art, desc, stock, price, cost} */
(function () {
  function fmt(n) { return Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  window.artSuggest = function (input, opt) {
    opt = opt || {};
    var box = document.createElement('div'); box.className = 'asug'; box.hidden = true; document.body.appendChild(box);
    var items = [], sel = -1, timer = null, seq = 0, last = '';
    function place() { var r = input.getBoundingClientRect(); box.style.left = (r.left + window.scrollX) + 'px'; box.style.top = (r.bottom + window.scrollY + 2) + 'px'; box.style.minWidth = Math.max(r.width, 360) + 'px'; }
    function close() { box.hidden = true; sel = -1; input.dataset.sugOpen = ''; }
    function render() {
      if (!items.length) { close(); return; }
      box.innerHTML = items.map(function (it, i) {
        return '<div class="asug-i' + (i === sel ? ' on' : '') + '" data-i="' + i + '"><b>' + esc(it.art) + '</b><span>' + esc(it.desc) + '</span><em class="' + (it.stock > 0 ? 'ok' : 'no') + '">' + (it.stock > 0 ? 'stock ' + Number(it.stock).toLocaleString('es-ES') : 'sin stock') + '</em>' + (opt.showCost && it.cost !== null ? '<i>' + fmt(it.cost) + '</i>' : '<i>' + fmt(it.price) + '</i>') + '</div>';
      }).join('');
      place(); box.hidden = false; input.dataset.sugOpen = '1';
    }
    function pick(i) { var it = items[i]; if (!it) return; input.value = it.art; close(); if (opt.onPick) opt.onPick(it); }
    input.addEventListener('input', function () {
      var q = input.value.trim(); clearTimeout(timer);
      if (q.length < 2) { items = []; close(); return; }
      timer = setTimeout(function () {
        var my = ++seq; last = q;
        fetch('/articulos-sugerir?q=' + encodeURIComponent(q) + '&tarifa=' + (opt.tariff || 1), { headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); }).then(function (j) { if (my !== seq || document.activeElement !== input) return; items = j || []; sel = -1; render(); }).catch(function () {});
      }, 160);
    });
    // keydown en captura: va antes que otros manejadores del mismo campo (p. ej. «Intro añade la línea»)
    input.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(items.length - 1, sel + 1); render(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(-1, sel - 1); render(); }
      else if (e.key === 'Escape') { close(); }
      else if (e.key === 'Enter' && sel >= 0) { e.preventDefault(); e.stopImmediatePropagation(); pick(sel); }
    }, true);
    // mousedown (no click) y sin perder el foco: así el campo no dispara «change» con lo tecleado a medias
    box.addEventListener('mousedown', function (e) { var el = e.target.closest('.asug-i'); if (!el) return; e.preventDefault(); pick(+el.dataset.i); });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    window.addEventListener('resize', function () { if (!box.hidden) place(); });
  };
})();
