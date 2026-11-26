@extends('layout')
@section('title', 'Albaranes y facturas de proveedor')
@section('content')
<div class="head"><div><h1>Albaranes y facturas de proveedor</h1><div class="muted small">Sube los PDF o fotos de albaranes y facturas de proveedor (puedes elegir varios a la vez). El panel lee las líneas, te dice qué pedidos lo esperan y crea en FactuSOL la entrada (albarán) o la factura recibida (factura).</div></div></div>
<div class="card">
  <form method="post" action="/albaranes-proveedor" enctype="multipart/form-data" id="upf">
    @csrf
    <input type="hidden" name="text" id="uptext"><input type="hidden" name="how" id="uphow" value="nada">
    <div class="row" style="align-items:center;gap:10px">
      <select name="kind" id="upkind" style="flex:0 0 auto;width:auto"><option value="auto">Detectar si es albarán o factura</option><option value="albaran">Es un albarán → Entrada</option><option value="factura">Es una factura → Factura recibida</option></select>
      <input type="file" name="file" id="upfile" accept="application/pdf,image/*" multiple required style="flex:1;min-width:220px">
      <button class="btn y" type="submit" id="upbtn">Subir y leer</button>
    </div>
    <div class="small muted" id="upmsg" style="margin-top:8px">PDF (lo mejor: se lee exacto) o foto nítida y recta (JPG/PNG). Máximo 15 MB. La lectura se hace en tu equipo y tarda unos segundos.</div>
  </form>
</div>
<div class="card mt"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Subido</th><th>Proveedor</th><th>Tipo</th><th>Su nº</th><th>Fecha</th><th class="num">Líneas</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    @forelse($notes as $n)
      <tr @if($n->pendingRead()) data-pending="{{ $n->id }}" data-name="{{ $n->file_name }}"@endif>
        <td class="small">{{ $n->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}<div class="muted">{{ $n->user?->name }}</div></td>
        <td><a href="/albaranes-proveedor/{{ $n->id }}"><strong>{{ $n->supplier_name ?: 'Proveedor sin identificar' }}</strong></a></td>
        <td class="small">{{ $n->isInvoice() ? 'Factura' : 'Albarán' }}</td><td>{{ $n->their_no ?: '—' }}</td>
        <td class="small">{{ $n->date?->format('d/m/Y') }}</td>
        <td class="num">{{ count($n->linesArray()) }}</td>
        <td><span class="badge {{ $n->entry_code || $n->received_at ? 'ok' : 'warn' }}">{{ $n->stateLabel() }}</span>@if($n->pendingRead())<div class="small muted">📧 {{ \Illuminate\Support\Str::limit($n->file_name, 40) }}</div>@endif</td>
        <td class="num" style="white-space:nowrap"><a class="btn ghost sm" href="/albaranes-proveedor/{{ $n->id }}">Abrir</a>
          @if(auth()->user()->canSeeSales() || $n->user_id === auth()->id())<form method="post" action="/albaranes-proveedor/{{ $n->id }}/borrar" style="display:inline" onsubmit="return confirm('¿Borrar este documento subido?{{ $n->entry_code ? ' Lo creado en FactuSOL ('.$n->docLabel().' '.$n->entry_code.') NO se borra: para quitarlo, ábrelo y pulsa Deshacer.' : '' }}')">@csrf<button class="btn ghost sm" type="submit" style="color:var(--bad)">🗑 Borrar</button></form>@endif</td>
      </tr>
    @empty<tr><td colspan="8" class="muted">Todavía no se ha subido ningún documento.</td></tr>@endforelse
    </tbody>
  </table>
</div></div>
@if($notes->hasPages())
  <div class="pager">
    @if(!$notes->onFirstPage())<a class="btn ghost sm" href="{{ $notes->previousPageUrl() }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $notes->currentPage() }} de {{ $notes->lastPage() }}</span>
    @if($notes->hasMorePages())<a class="btn ghost sm" href="{{ $notes->nextPageUrl() }}">Siguientes →</a>@endif
  </div>
@endif
<script src="/js/supplier-note.js?v=2"></script>
<script>
(function(){
  var f=document.getElementById('upf'),file=document.getElementById('upfile'),msg=document.getElementById('upmsg'),btn=document.getElementById('upbtn'),done=false;
  var tok=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
  f.addEventListener('submit',function(e){
    if(done||!file.files.length)return;
    e.preventDefault();btn.disabled=true;btn.textContent='Leyendo…';
    var files=Array.prototype.slice.call(file.files);
    if(files.length===1){
      window.readSupplierDoc(files[0],function(t){msg.textContent=t;}).then(function(r){
        document.getElementById('uptext').value=(r.text||'').slice(0,390000);document.getElementById('uphow').value=r.how;
        msg.textContent=r.how==='nada'?'No he podido leerlo aquí; lo subo igualmente y metes las líneas a mano.':'Leído. Subiendo…';
        done=true;delete f.dataset.busy;f.submit();
      });
      return;
    }
    // Varios documentos: se leen y suben de uno en uno y al final se vuelve a la lista
    var ok=[],bad=[];
    files.reduce(function(p,fl,i){return p.then(function(){
      msg.textContent='Documento '+(i+1)+' de '+files.length+': '+fl.name+'…';
      return window.readSupplierDoc(fl,function(){}).then(function(r){
        var fd=new FormData();fd.append('_token',tok);fd.append('file',fl);fd.append('text',(r.text||'').slice(0,390000));fd.append('how',r.how);fd.append('kind',document.getElementById('upkind').value);
        return fetch('/albaranes-proveedor',{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(res){return res.ok?res.json():Promise.reject(res.status);})
          .then(function(j){ok.push((j.supplier||'Proveedor')+' '+(j.their_no||'')+' ('+j.lines+' líneas)');},function(){bad.push(fl.name);});
      });
    });},Promise.resolve()).then(function(){
      msg.textContent='Subidos '+ok.length+' de '+files.length+'.'+(bad.length?' No se han podido subir: '+bad.join(', ')+'.':'')+' Ábrelos en la lista para revisarlos y validarlos.';
      setTimeout(function(){location.reload();},bad.length?4000:1200);
    });
  });
})();
</script>
<script>
// Documentos que han llegado por correo (Zapier): se leen aquí, en el navegador, igual que los subidos a mano.
document.addEventListener('DOMContentLoaded',function(){
  var rows=[].slice.call(document.querySelectorAll('tr[data-pending]'));if(!rows.length)return;
  var tok=document.querySelector('meta[name=csrf-token]').content,msg=document.getElementById('upmsg');
  msg.textContent='Leyendo '+rows.length+(rows.length===1?' documento llegado':' documentos llegados')+' por correo…';
  rows.reduce(function(p,tr){return p.then(function(){
    var id=tr.getAttribute('data-pending');
    return fetch('/albaranes-proveedor/'+id+'/archivo',{credentials:'same-origin'}).then(function(r){return r.blob();}).then(function(b){
      var fl=new File([b],tr.getAttribute('data-name')||'documento.pdf',{type:'application/pdf'});
      return window.readSupplierDoc(fl,function(){});
    }).then(function(r){
      var fd=new FormData();fd.append('_token',tok);fd.append('text',(r.text||'').slice(0,390000));fd.append('how',r.how);
      return fetch('/albaranes-proveedor/'+id+'/leer',{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json'}});
    }).catch(function(){});
  });},Promise.resolve()).then(function(){location.reload();});
})();
</script>
@endsection
