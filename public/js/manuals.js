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
    // Visor: pinta una página en el lienzo, a lo ancho del contenedor
    render: async function (id, n, canvas, box) {
      var L = await lib();
      if (!window._psPdf) window._psPdf = L.getDocument({ url: '/manuales/' + id + '/archivo', isEvalSupported: false }).promise;
      var pdf = await window._psPdf, page = await pdf.getPage(n);
      var w = Math.max(320, box.clientWidth - 2), base = page.getViewport({ scale: 1 }), scale = w / base.width, dpr = window.devicePixelRatio || 1;
      var vp = page.getViewport({ scale: scale * dpr });
      canvas.width = vp.width; canvas.height = vp.height; canvas.style.width = (vp.width / dpr) + 'px'; canvas.style.height = (vp.height / dpr) + 'px';
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      return pdf.numPages;
    }
  };
})();
