/* Manuales: lectura del texto de cada página en el navegador (pdf.js) y envío al panel por bloques.
   También pinta una página del PDF en el visor. */
(function () {
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function loadScript(src) {
    return new Promise(function (ok, ko) {
      if (window.pdfjsLib) return ok();
      var s = document.createElement('script'); s.src = src; s.onload = function () { ok(); }; s.onerror = function () { ko(new Error('No se pudo cargar el lector de PDF')); };
      document.head.appendChild(s);
    });
  }
  async function lib() {
    await loadScript('/lib/pdfjs/pdf.min.js');
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = '/lib/pdfjs/pdf.worker.min.js';
    return window.pdfjsLib;
  }

  // Reconstruye los renglones de la página a partir de la posición de cada trozo de texto
  function pageLines(items) {
    var rows = [];
    items.forEach(function (it) {
      if (!it.str || !it.str.trim()) return;
      var x = it.transform[4], y = it.transform[5], h = Math.abs(it.transform[3]) || it.height || 10;
      var row = null;
      for (var i = 0; i < rows.length; i++) { if (Math.abs(rows[i].y - y) <= Math.max(2.5, h * 0.35)) { row = rows[i]; break; } }
      if (!row) { row = { y: y, parts: [] }; rows.push(row); }
      row.parts.push({ x: x, w: it.width || 0, s: it.str });
    });
    rows.sort(function (a, b) { return b.y - a.y; });
    return rows.map(function (r) {
      r.parts.sort(function (a, b) { return a.x - b.x; });
      var out = '', end = null;
      r.parts.forEach(function (p) {
        if (end !== null) { var gap = p.x - end; out += gap > 1.2 ? (gap > 14 ? '   ' : ' ') : ''; }
        out += p.s; end = p.x + p.w;
      });
      return out.replace(/\s+$/, '');
    }).join('\n');
  }

  var docs = {};
  // Un mismo PDF solo se descarga una vez por página del panel
  async function doc(id) {
    var L = await lib();
    if (!docs[id]) docs[id] = L.getDocument({ url: '/manuales/' + id + '/archivo', isEvalSupported: false }).promise;
    return docs[id];
  }
  function norm(s) { return String(s).toUpperCase().replace(/[^0-9A-Z]/g, ''); }

  async function post(url, body, json) {
    var r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: Object.assign({ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, json ? { 'Content-Type': 'application/json' } : {}), body: json ? JSON.stringify(body) : body });
    if (!r.ok) {
      var t = ''; try { var j = await r.json(); t = j.message || ''; if (j.errors) { t = Object.values(j.errors)[0][0]; } } catch (e) {}
      throw new Error(t || ('Error ' + r.status));
    }
    return r.json();
  }

  // Lee las páginas desde «from» y las manda en bloques de 40
  async function indexPdf(pdf, id, from, onProgress) {
    var total = pdf.numPages, batch = [];
    for (var n = from; n <= total; n++) {
      var page = await pdf.getPage(n), tc = await page.getTextContent();
      batch.push({ page: n, text: pageLines(tc.items) });
      page.cleanup();
      if (batch.length >= 40 || n === total) {
        var res = await post('/manuales/' + id + '/paginas', { pages: batch, total: total }, true);
        batch = [];
        onProgress(n, total, res);
      }
    }
  }

  window.PsManuals = {
    // Formulario de subida: primero el PDF y sus datos, después el texto página a página
    upload: async function (form, msg, bar) {
      var file = form.querySelector('input[type=file]').files[0];
      if (!file) throw new Error('Elige el PDF del manual.');
      var L = await lib();
      msg('Abriendo el PDF…');
      var pdf = await L.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false }).promise;
      var fd = new FormData(form); fd.set('pages', pdf.numPages);
      msg('Subiendo el PDF (' + (file.size / 1048576).toFixed(1) + ' MB)…');
      var res = await post('/manuales', fd, false);
      await indexPdf(pdf, res.id, 1, function (n, total) { bar(n / total); msg('Leyendo páginas: ' + n + ' de ' + total + '. No cierres esta pestaña.'); });
      return res.id;
    },
    // Continuar una lectura que se quedó a medias, desde el PDF ya subido
    resume: async function (id, from, msg, bar) {
      var L = await lib();
      msg('Abriendo el PDF…');
      var pdf = await L.getDocument({ url: '/manuales/' + id + '/archivo', isEvalSupported: false }).promise;
      await indexPdf(pdf, id, from, function (n, total) { bar(n / total); msg('Leyendo páginas: ' + n + ' de ' + total + '. No cierres esta pestaña.'); });
    },
    // Visor: pinta una página a lo ancho del contenedor (por el zoom) y guarda dónde está cada texto para poder resaltarlo
    render: async function (id, n, canvas, box, zoom) {
      var pdf = await doc(id), page = await pdf.getPage(n);
      var w = Math.max(320, box.clientWidth - 2) * (zoom || 1), base = page.getViewport({ scale: 1 }), scale = w / base.width, dpr = Math.min(window.devicePixelRatio || 1, 2);
      var vp = page.getViewport({ scale: scale * dpr });
      canvas.width = vp.width; canvas.height = vp.height; canvas.style.width = (vp.width / dpr) + 'px'; canvas.style.height = (vp.height / dpr) + 'px';
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      var css = page.getViewport({ scale: scale }), tc = await page.getTextContent();
      box._items = tc.items.filter(function (it) { return it.str && it.str.trim(); }).map(function (it) {
        var t = window.pdfjsLib.Util.transform(css.transform, it.transform), h = Math.hypot(t[2], t[3]);
        return { n: norm(it.str), x: t[4], y: t[5] - h, w: it.width * scale, h: h * 1.15 };
      });
      return pdf.numPages;
    },
    // Resalta en la página los textos que contienen alguna de las referencias o palabras dadas (ya normalizadas)
    highlight: function (box, layer, needles, scroll) {
      layer.innerHTML = '';
      var first = null;
      (box._items || []).forEach(function (it) {
        if (!needles.some(function (x) { return x && it.n.indexOf(x) !== -1; })) return;
        var d = document.createElement('div'); d.className = 'mv-hl';
        d.style.cssText = 'left:' + (it.x - 3) + 'px;top:' + (it.y - 2) + 'px;width:' + (it.w + 6) + 'px;height:' + (it.h + 4) + 'px';
        layer.appendChild(d); if (!first) first = d;
      });
      if (first && scroll) { box.scrollTop = Math.max(0, first.offsetTop - box.clientHeight / 2); box.scrollLeft = Math.max(0, first.offsetLeft - box.clientWidth / 2); }
      return layer.children.length;
    },
    // Miniatura de una página para los resultados de la búsqueda
    thumb: async function (id, n, canvas, width) {
      var pdf = await doc(id), page = await pdf.getPage(n), base = page.getViewport({ scale: 1 }), dpr = Math.min(window.devicePixelRatio || 1, 2);
      var vp = page.getViewport({ scale: width / base.width * dpr });
      canvas.width = vp.width; canvas.height = vp.height; canvas.style.width = width + 'px'; canvas.style.height = (vp.height / dpr) + 'px';
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      page.cleanup();
    },
    // Miniaturas perezosas: cada lienzo con data-m y data-p se pinta cuando entra en pantalla, de una en una
    lazyThumbs: function (width) {
      var queue = Promise.resolve();
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) {
          if (!e.isIntersecting) return;
          io.unobserve(e.target);
          var c = e.target;
          queue = queue.then(function () { return PsManuals.thumb(+c.dataset.m, +c.dataset.p, c, width).then(function () { c.classList.add('ok'); }); }).catch(function () {});
        });
      }, { rootMargin: '300px' });
      document.querySelectorAll('canvas.mn-th').forEach(function (c) { io.observe(c); });
    }
  };
})();
