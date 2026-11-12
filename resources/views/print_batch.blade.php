<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Imprimir varios documentos · Parsur</title>
<style>
  body{margin:0;background:#e9e9e6;font-family:Arial,Helvetica,sans-serif}
  .bbar{position:sticky;top:0;z-index:10;background:#1F1F1F;color:#fff;padding:10px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:13px}
  .bbar button,.bbar a{background:#FAB001;color:#1F1F1F;border:0;border-radius:8px;padding:8px 14px;font-weight:800;cursor:pointer;text-decoration:none;font-size:13px}
  .bbar .g{background:#444;color:#fff}
  .bbar label{display:inline-flex;align-items:center;gap:6px;font-weight:700;cursor:pointer}
  .bbar .st{opacity:.8;flex:1 1 200px}
  .busy{opacity:.5;pointer-events:none}
  #docs .sheet{page-break-after:always;break-after:page}
  #docs .sheet:last-child{page-break-after:auto;break-after:auto}
  @media print{.bbar{display:none}body{background:#fff}}
</style>
<div id="styles"></div>
</head><body>
<div class="bbar" id="bbar">
  <button type="button" onclick="window.print()">🖨 Imprimir todo</button>
  <button type="button" onclick="guardar()">⬇ Guardar en un PDF</button>
  <label title="Presupuestos y pedidos salen sin referencias salvo que lo marques"><input type="checkbox" id="refs" onchange="refs(this.checked)"> Incluir referencias</label>
  <a class="g" href="javascript:window.close()">Cerrar</a>
  <span class="st" id="st">Cargando documentos…</span>
</div>
<div id="docs"></div>
<script src="/js/qrcode.js?v=1"></script>
<script src="/js/html2pdf.bundle.min.js?v=1"></script>
<script>
var OK=/^\/(presupuestos\/\d+|documentos\/[a-z]+\/\d+\/\d+)\/pdf$/;
var lista=(new URLSearchParams(location.search).get('docs')||'').split(',').filter(function(u){return OK.test(u);}).slice(0,60);
var docs=document.getElementById('docs'),st=document.getElementById('st'),vistos={},fallos=0;
function qr(img,url){try{var q=qrcode(0,'M');q.addData(url);q.make();img.src=q.createDataURL(6,2);}catch(e){img.style.display='none';}}
function refs(on){[].forEach.call(docs.querySelectorAll('.sheet[data-noref]'),function(s){s.classList.toggle('noref',!on);});}
async function cargar(){
  if(!lista.length){st.textContent='No hay documentos seleccionados.';return;}
  for(var i=0;i<lista.length;i++){
    st.textContent='Cargando '+(i+1)+' de '+lista.length+'…';
    try{
      var h=await fetch(lista[i],{credentials:'same-origin'}).then(function(r){if(!r.ok)throw 0;return r.text();});
      var d=new DOMParser().parseFromString(h,'text/html'),sheet=d.querySelector('.sheet');
      if(!sheet)throw 0;
      [].forEach.call(d.querySelectorAll('head style'),function(s){var k=s.textContent.length+':'+s.textContent.slice(0,80);if(!vistos[k]){vistos[k]=1;var n=document.createElement('style');n.textContent=s.textContent.replace(/\.bar\b[^{}]*\{[^}]*\}/g,'');document.getElementById('styles').appendChild(n);}});
      [].forEach.call(sheet.querySelectorAll('script'),function(s){s.remove();});
      var n=document.importNode(sheet,true);
      if(n.classList.contains('noref'))n.setAttribute('data-noref','1');
      docs.appendChild(n);
      var p=n.querySelector('.pay'),pi=p&&p.querySelector('img'),pa=p&&p.querySelector('a[href]');if(pi&&pa){pi.removeAttribute('id');qr(pi,pa.href);}
      var r=n.querySelector('.rev'),ri=r&&r.querySelector('img'),ra=r&&r.querySelector('a[href]');if(ri&&ra){ri.removeAttribute('id');qr(ri,ra.href);}
    }catch(e){fallos++;}
  }
  var n=docs.querySelectorAll('.sheet').length;
  st.textContent=n+' documento'+(n===1?'':'s')+' listo'+(n===1?'':'s')+(fallos?' · '+fallos+' no se han podido cargar':'')+'. Cada uno sale en su hoja.';
}
async function guardar(){
  var b=document.getElementById('bbar');b.classList.add('busy');
  try{await html2pdf().set({margin:[10,10,12,10],filename:'Documentos_Parsur_'+new Date().toISOString().slice(0,10)+'.pdf',image:{type:'jpeg',quality:.95},html2canvas:{scale:2,useCORS:true,backgroundColor:'#fff'},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},pagebreak:{mode:['css','legacy']}}).from(docs).save();}
  finally{b.classList.remove('busy');}
}
cargar();
</script>
</body></html>
