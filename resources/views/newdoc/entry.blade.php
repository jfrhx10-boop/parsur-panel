@extends('layout')
@section('title', 'Nueva entrada')
@section('content')
<div class="head"><div><h1>Nueva entrada · {{ $s->displayName() }}</h1><div class="muted small">Cód. proveedor {{ $s->codpro }} · <a href="/nueva-entrada">cambiar proveedor</a></div></div></div>
@if($errors->any())<div class="card" style="border-color:var(--bad);margin-bottom:12px">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="post" action="/nueva-entrada/{{ $s->id }}" class="card" id="ef">
  @csrf
  <div class="row">
    <div style="flex:1 1 200px"><label>Nº de albarán del proveedor</label><input type="text" name="their_no" value="{{ old('their_no') }}" maxlength="20" placeholder="Su albarán / factura"></div>
    <div style="flex:0 1 180px"><label>Fecha</label><input type="date" name="date" value="{{ old('date', now('Europe/Madrid')->toDateString()) }}" required></div>
  </div>
  <div class="scroll" style="margin-top:12px"><table class="tbl" id="et">
    <thead><tr><th style="width:22%">Referencia</th><th>Descripción</th><th class="num" style="width:90px">Cantidad</th><th class="num" style="width:110px">Coste (€)</th><th class="num" style="width:100px">Importe</th><th style="width:30px"></th></tr></thead>
    <tbody></tbody>
    <tfoot>
      <tr><td colspan="4" class="num">Base imponible</td><td class="num" id="tb">0,00 €</td><td></td></tr>
      <tr><td colspan="4" class="num">IVA 21 %</td><td class="num" id="ti">0,00 €</td><td></td></tr>
      <tr><td colspan="4" class="num"><strong>Total</strong></td><td class="num"><strong id="tt">0,00 €</strong></td><td></td></tr>
    </tfoot>
  </table></div>
  <div class="row" style="margin-top:10px;gap:8px"><button class="btn" type="button" id="addrow">＋ Añadir línea</button><button class="btn y" type="submit">Crear entrada en FactuSOL</button></div>
  <div class="small muted" style="margin-top:8px">Escribe la referencia y pulsa Tab: se rellenan descripción y coste del artículo. Nota: FactuSOL no mueve el stock al crear la entrada por aquí.</div>
</form>
@if($mine->isNotEmpty())
<div class="card" style="margin-top:12px"><div class="small muted" style="margin-bottom:6px">Entradas creadas desde el panel (por si hay que deshacer alguna):</div>
  @foreach($mine as $pe)
  <form method="post" action="/entradas-panel/{{ $pe->id }}/quitar" style="display:inline-block;margin:2px 8px 2px 0" onsubmit="return confirm('Se borra la entrada {{ $pe->entry_code }} de FactuSOL. ¿Seguir?')">@csrf Entrada <b>{{ $pe->entry_code }}</b> · {{ eur($pe->total) }} <button class="btn ghost sm" type="submit">Quitar</button></form>
  @endforeach
</div>
@endif
<script>
(function(){
  var tb=document.querySelector('#et tbody'),n=0,old=@json(old('lines', []));
  function eur(x){return x.toFixed(2).replace('.',',')+' €';}
  function calc(){var b=0;tb.querySelectorAll('tr').forEach(function(r){var q=parseFloat(r.querySelector('.q').value)||0,c=parseFloat(r.querySelector('.c').value)||0,t=Math.round(q*c*100)/100;r.querySelector('.t').textContent=eur(t);b+=t;});
    document.getElementById('tb').textContent=eur(b);var i=Math.round(b*21)/100;document.getElementById('ti').textContent=eur(i);document.getElementById('tt').textContent=eur(b+i);}
  function row(v){v=v||{};var i=n++,tr=document.createElement('tr');
    tr.innerHTML='<td><input type="text" class="a" name="lines['+i+'][art]" placeholder="Referencia…" autocomplete="off"></td><td><input type="text" class="d" name="lines['+i+'][desc]"></td><td><input type="number" class="num q" name="lines['+i+'][qty]" value="1" step="any" min="0" inputmode="decimal"></td><td><input type="number" class="num c" name="lines['+i+'][cost]" value="0" step="any" min="0" inputmode="decimal"></td><td class="num t">0,00 €</td><td class="num"><button type="button" class="linkbtn x" style="color:var(--bad);font-size:18px">×</button></td>';
    tb.appendChild(tr);
    var a=tr.querySelector('.a');
    if(v.art!==undefined){a.value=v.art||'';tr.querySelector('.d').value=v.desc||'';tr.querySelector('.q').value=v.qty||1;tr.querySelector('.c').value=v.cost||0;}
    a.addEventListener('change',function(){var r=a.value.trim();if(!r)return;fetch('/nueva-entrada/articulo?ref='+encodeURIComponent(r),{headers:{Accept:'application/json'}}).then(function(x){return x.json()}).then(function(j){if(j){a.value=j.art;tr.querySelector('.d').value=j.desc;tr.querySelector('.c').value=j.cost;calc();tr.querySelector('.q').focus();}}).catch(function(){});});
    tr.addEventListener('input',calc);tr.querySelector('.x').addEventListener('click',function(){tr.remove();calc();});
    return tr;}
  (Array.isArray(old)?old:Object.values(old)).forEach(function(v){row(v)});
  if(!tb.children.length)row();
  document.getElementById('addrow').addEventListener('click',function(){row().querySelector('.a').focus();});
  calc();
})();
</script>
@endsection
