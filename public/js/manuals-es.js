/* Manuales: lectura en español del gremio. Traduce los términos de los catálogos (inglés) con un glosario
   y recoloca los grupos de palabras al orden español: «Rear Wear Plates» → «Placas de desgaste traseras».
   No es un traductor de frases: lo que no está en el glosario se deja como viene. */
window.PsEs = (function () {
  var G = null, maxLen = 1, loading = null;

  function load() {
    if (!loading) loading = fetch('/manuales-glosario', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (rows) {
      G = {};
      rows.forEach(function (r) { G[r[0]] = { en: r[0], es: r[1], t: r[2], id: r[3] }; maxLen = Math.max(maxLen, r[0].split(/[ \/]/).length); });
    });
    return loading;
  }

  function isNoun(g) { return g.t.charAt(0) === 'n'; }
  // Concordancia del adjetivo (dado en masculino singular) con el nombre al que acompaña
  function agree(adj, head) {
    var fem = head.t.charAt(1) === 'f', pl = head.t.length === 3, parts = adj.split(' '), w = parts[0];
    if (fem) { if (/o$/.test(w)) w = w.slice(0, -1) + 'a'; else if (/[dt]or$/.test(w)) w += 'a'; }
    if (pl) w += /[aeiouáéíóú]$/.test(w) ? 's' : 'es';
    parts[0] = w;
    return parts.join(' ');
  }
  function recase(out, src) {
    var letters = src.replace(/[^A-Za-z]/g, '');
    if (letters.length > 1 && letters === letters.toUpperCase()) return out.toUpperCase();
    if (/^[^A-Za-z]*[A-Z]/.test(src)) return out.charAt(0).toUpperCase() + out.slice(1);
    return out;
  }

  // Trocea el texto en unidades: términos del glosario (lo más largos posible) y lo demás tal cual
  function units(str) {
    var toks = str.match(/[A-Za-z][A-Za-z'’]*(?:-[A-Za-z]+)*\.?|#|[^A-Za-z#]+/g) || [], out = [], i = 0, unknown = false;
    function isWord(t) { return /^[A-Za-z#]/.test(t); }
    while (i < toks.length) {
      var t = toks[i];
      if (!isWord(t)) { out.push({ o: t }); i++; continue; }
      var hit = null, end = i;
      // de la combinación más larga a la más corta: palabra (sep palabra)*
      for (var n = maxLen; n >= 1 && !hit; n--) {
        var j = i, key = toks[i], ok = true;
        for (var k = 1; k < n; k++) {
          var sep = toks[j + 1], nx = toks[j + 2];
          if (sep === undefined || nx === undefined || !isWord(nx) || !/^( |\/| ?- ?)$/.test(sep) && !(sep === ' ' && nx === '#')) { ok = false; break; }
          key += (sep === '/' ? '/' : ' ') + nx; j += 2;
        }
        if (!ok) continue;
        var lk = key.toLowerCase().replace(/’/g, "'"), g = G[lk] || G[lk.replace(/\.$/, '')];
        if (g) { hit = g; end = j; }
      }
      if (hit) {
        var src = toks.slice(i, end + 1).join('');
        // el punto final de una frase no es parte del término
        var dot = /\.$/.test(src) && !/\.$/.test(hit.en) ? '.' : '';
        out.push({ g: hit, src: src });
        if (dot) out.push({ o: dot });
        i = end + 1;
      } else {
        // siglas, códigos y letras sueltas no cuentan como inglés sin traducir
        if (!(/^[A-Z]{1,5}\.?$/.test(t) || t.length < 2 || t === '#')) unknown = true;
        out.push({ o: t }); i++;
      }
    }
    return { list: out, unknown: unknown };
  }

  function text(str) {
    if (!G || !str || !/[A-Za-z]{2}/.test(str)) return str;
    var u = units(str), list = u.list;
    if (!list.some(function (x) { return x.g; })) return str;
    var res = '';
    if (u.unknown) {
      // Frase con palabras fuera del glosario: solo se cambian los términos, sin recolocar
      list.forEach(function (x) { res += x.g ? recase(x.g.es, x.src) : x.o; });
      return res;
    }
    var i = 0;
    while (i < list.length) {
      var x = list[i];
      if (!x.g || x.g.t === 'x') { res += x.g ? recase(x.g.es, x.src) : x.o; i++; continue; }
      // grupo: términos seguidos separados solo por un espacio
      var grp = [x], j = i;
      while (list[j + 1] && list[j + 1].o === ' ' && list[j + 2] && list[j + 2].g && list[j + 2].g.t !== 'x') { grp.push(list[j + 2]); j += 2; }
      var src = grp.map(function (q) { return q.src; }).join(' '), head = -1, out;
      for (var k = grp.length - 1; k >= 0; k--) { if (isNoun(grp[k].g)) { head = k; break; } }
      if (head < 0) {
        out = grp.map(function (q) { return q.g.es; }).join(' ');
      } else {
        var h = grp[head].g, nouns = [], post = [], adjs = [];
        for (k = grp.length - 1; k >= 0; k--) {
          if (k === head) continue;
          var g = grp[k].g;
          if (isNoun(g)) nouns.push('de ' + g.es); else if (g.t === 'p') post.push(g.es); else adjs.push(agree(g.es, h));
        }
        out = [h.es].concat(nouns, post, adjs).join(' ');
      }
      res += recase(out, src);
      i = j + 1;
    }
    // «950 Loaders» → «Palas cargadoras 950»
    var m = res.match(/^([0-9][0-9A-Z.\-]{2,}|[A-Z]{1,2}[0-9]{1,3}[A-Z]?)\s+([A-ZÁÉÍÓÚ][a-záéíóúñ]+s(?: [a-záéíóúñ]+)*)$/);
    if (m) res = m[2] + ' ' + m[1];
    return res;
  }

  // Términos del glosario que aparecen en una lista de textos (para el glosario de la página)
  function found(strs) {
    var seen = {}, out = [];
    strs.forEach(function (s) {
      if (!/[A-Za-z]{2}/.test(s)) return;
      units(s).list.forEach(function (x) { if (x.g && x.g.t !== 'x' && !seen[x.g.en]) { seen[x.g.en] = 1; out.push(x.g); } });
    });
    return out.sort(function (a, b) { return a.en.localeCompare(b.en); });
  }

  // Capa sobre la página: tapa cada texto inglés con su versión en español, en el mismo sitio
  function overlay(box, layer, on) {
    layer.innerHTML = '';
    if (!on) return 0;
    var n = 0;
    (box._items || []).forEach(function (it) {
      var es = text(it.s);
      if (es === it.s) return;
      var d = document.createElement('div'); d.className = 'mv-es'; d.textContent = es; d.title = it.s;
      d.style.cssText = 'left:' + (it.x - 1) + 'px;top:' + (it.y - 1) + 'px;height:' + (it.h + 2) + 'px;line-height:' + (it.h + 2) + 'px;font-size:' + (it.h * 0.78) + 'px;min-width:' + (it.w + 2) + 'px';
      layer.appendChild(d);
      var need = d.scrollWidth, have = it.w + 2;
      if (need > have + 1) { var f = Math.max(0.7, have / need); d.style.transform = 'scaleX(' + f + ')'; d.style.width = need + 'px'; d.style.minWidth = '0'; }
      n++;
    });
    return n;
  }

  // Textos sueltos del panel (títulos de sección, resultados): data-tr guarda el original
  function dom(on) {
    document.querySelectorAll('[data-tr]').forEach(function (el) {
      if (!el.dataset.en) el.dataset.en = el.textContent;
      el.textContent = on ? el.dataset.en.split(' · ').map(text).join(' · ') : el.dataset.en;
    });
  }

  function isOn() { try { return localStorage.getItem('ps-manual-es') === '1'; } catch (e) { return false; } }
  function set(on) { try { localStorage.setItem('ps-manual-es', on ? '1' : '0'); } catch (e) {} }

  return { load: load, text: text, found: found, overlay: overlay, dom: dom, isOn: isOn, set: set };
})();
