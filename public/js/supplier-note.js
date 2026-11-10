/* Lectura de albaranes de proveedor en el navegador: texto del PDF (pdf.js) o reconocimiento de una foto (tesseract.js).
   Todo se hace en este equipo; al servidor se manda el archivo y el texto leído. */
(function () {
  function loadScript(src) {
    return new Promise(function (ok, ko) {
      if (document.querySelector('script[data-src="' + src + '"]')) return ok();
      var s = document.createElement('script'); s.src = src; s.dataset.src = src; s.onload = function () { ok(); }; s.onerror = function () { ko(new Error('No se pudo cargar ' + src)); };
      document.head.appendChild(s);
    });
  }

  // Reconstruye los renglones de una página de PDF a partir de la posición de cada trozo de texto
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
    });
  }

  async function pdfDoc(file) {
    await loadScript('/vendor/pdfjs/pdf.min.js');
    var lib = window.pdfjsLib; lib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.js';
    return lib.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false }).promise;
  }

  async function pdfText(pdf) {
    var lines = [];
    for (var n = 1; n <= Math.min(pdf.numPages, 15); n++) {
      var page = await pdf.getPage(n), tc = await page.getTextContent();
      lines = lines.concat(pageLines(tc.items));
    }
    return lines.join('\n');
  }

  async function pdfCanvases(pdf) {
    var out = [];
    for (var n = 1; n <= Math.min(pdf.numPages, 6); n++) {
      var page = await pdf.getPage(n), vp = page.getViewport({ scale: 2.2 });
      var c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
      await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
      out.push(c);
    }
    return out;
  }

  function imageCanvas(file) {
    return new Promise(function (ok, ko) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        var max = 2400, k = Math.min(1, max / Math.max(img.width, img.height));
        var c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); ok(c);
      };
      img.onerror = function () { URL.revokeObjectURL(url); ko(new Error('Este formato de imagen no se puede leer aquí.')); };
      img.src = url;
    });
  }

  async function ocr(canvases, progress) {
    await loadScript('/vendor/ocr/tesseract.min.js');
    var page = 0;
    var worker = await window.Tesseract.createWorker('spa', 1, {
      workerPath: '/vendor/ocr/worker.min.js', corePath: '/vendor/ocr', langPath: '/vendor/ocr', workerBlobURL: false, gzip: true,
      logger: function (m) { if (progress && m.status === 'recognizing text') progress('Leyendo la imagen… ' + Math.round(((page + m.progress) / canvases.length) * 100) + ' %'); }
    });
    try {
      await worker.setParameters({ tessedit_pageseg_mode: '6', preserve_interword_spaces: '1' });
      var text = '';
      for (page = 0; page < canvases.length; page++) {
        var r = await worker.recognize(canvases[page]);
        text += r.data.text + '\n';
      }
      return text;
    } finally { await worker.terminate(); }
  }

  /** Devuelve {text, how}: how = 'pdf' (texto del PDF), 'ocr' (leído de imagen) o 'nada'. */
  window.readSupplierDoc = async function (file, progress) {
    var isPdf = /pdf$/i.test(file.type) || /\.pdf$/i.test(file.name);
    try {
      if (isPdf) {
        if (progress) progress('Leyendo el PDF…');
        var pdf = await pdfDoc(file), text = await pdfText(pdf);
        if (text.replace(/\s/g, '').length >= 60) return { text: text, how: 'pdf' };
        if (progress) progress('El PDF es una imagen escaneada: leyéndola…');
        return { text: await ocr(await pdfCanvases(pdf), progress), how: 'ocr' };
      }
      if (progress) progress('Leyendo la imagen…');
      return { text: await ocr([await imageCanvas(file)], progress), how: 'ocr' };
    } catch (e) {
      return { text: '', how: 'nada', error: String(e && e.message || e) };
    }
  };
})();
