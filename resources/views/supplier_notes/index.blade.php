@extends('layout')
@section('title', 'Albaranes y facturas de proveedor')
@section('content')
<div class="head"><div><h1>Albaranes y facturas de proveedor</h1><div class="muted small">Sube el PDF o una foto del albarán o de la factura del proveedor. El panel lee las líneas, te dice qué pedidos lo esperan y crea en FactuSOL la entrada (albarán) o la factura recibida (factura).</div></div></div>
<div class="card">
  <form method="post" action="/albaranes-proveedor" enctype="multipart/form-data" id="upf">
    @csrf
    <input type="hidden" name="text" id="uptext"><input type="hidden" name="how" id="uphow" value="nada">
    <div class="row" style="align-items:center;gap:10px">
      <select name="kind" style="flex:0 0 auto;width:auto"><option value="albaran">Es un albarán → Entrada</option><option value="factura">Es una factura → Factura recibida</option></select>
      <input type="file" name="file" id="upfile" accept="application/pdf,image/*" required style="flex:1;min-width:220px">
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
      <tr>
        <td class="small">{{ $n->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}<div class="muted">{{ $n->user?->name }}</div></td>
        <td><a href="/albaranes-proveedor/{{ $n->id }}"><strong>{{ $n->supplier_name ?: 'Proveedor sin identificar' }}</strong></a></td>
        <td class="small">{{ $n->isInvoice() ? 'Factura' : 'Albarán' }}</td><td>{{ $n->their_no ?: '—' }}</td>
        <td class="small">{{ $n->date?->format('d/m/Y') }}</td>
        <td class="num">{{ count($n->linesArray()) }}</td>
        <td><span class="badge {{ $n->entry_code || $n->received_at ? 'ok' : 'warn' }}">{{ $n->stateLabel() }}</span></td>
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
  f.addEventListener('submit',function(e){
    if(done||!file.files.length)return;
    e.preventDefault();btn.disabled=true;btn.textContent='Leyendo…';
    window.readSupplierDoc(file.files[0],function(t){msg.textContent=t;}).then(function(r){
      document.getElementById('uptext').value=(r.text||'').slice(0,390000);document.getElementById('uphow').value=r.how;
      msg.textContent=r.how==='nada'?'No he podido leerlo aquí; lo subo igualmente y metes las líneas a mano.':'Leído. Subiendo…';
      done=true;delete f.dataset.busy;f.submit();
    });
  });
})();
</script>
@endsection
