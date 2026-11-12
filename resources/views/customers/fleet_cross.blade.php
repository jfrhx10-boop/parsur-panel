@extends('layout')
@section('title', 'Máquinas de los documentos')
@section('content')
<div class="head">
  <div><h1>Máquinas de los documentos que faltan en la flota</h1>
    <div class="muted small">Sale de la referencia de presupuestos, pedidos y albaranes de FactuSOL desde {{ $from }}. {{ $total }} máquinas en {{ count($groups) }} clientes. El nº de serie se toma de las observaciones del documento, si lo lleva.</div></div>
  <a class="btn ghost sm" href="/clientes">← Clientes</a>
</div>
<form class="card row" method="get" action="/flota/cruce" style="margin-bottom:12px">
  <div style="flex:0 1 160px"><label for="desde">Documentos desde</label><select id="desde" name="desde" onchange="this.form.submit()">@for($y = (int) date('Y'); $y >= 2023; $y--)<option value="{{ $y }}" @selected($y === $from)>{{ $y }}</option>@endfor</select></div>
  <div class="small muted" style="flex:1 1 300px;align-self:center">Vienen marcadas las que tienen marca reconocida y salen en 2 documentos o más, o llevan nº de serie. Revisa, marca o desmarca, y pulsa <b>Añadir marcadas</b>. Lo que no sea una máquina, déjalo sin marcar o descártalo en la ficha del cliente.</div>
</form>
@if(! $total)
  <div class="card"><p class="muted" style="margin:0">No queda ninguna máquina por cruzar: todo lo que sale en los documentos ya está en la flota (o se descartó).</p></div>
@else
<form method="post" action="/flota/cruce" id="fx">@csrf
  <div class="card" style="position:sticky;top:8px;z-index:30;display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px">
    <button class="btn y" type="submit" onclick="return confirm('Se añaden a la flota de cada cliente las máquinas marcadas. ¿Seguir?')">✓ Añadir marcadas (<span id="fxn">0</span>)</button>
    <button class="btn ghost sm" type="button" onclick="fxAll(true)">Marcar todas</button>
    <button class="btn ghost sm" type="button" onclick="fxAll(false)">Desmarcar todas</button>
    <button class="btn ghost sm" type="button" onclick="fxSafe()">Solo las seguras</button>
  </div>
  @foreach($groups as $g)
    @php $c = $g['customer']; @endphp
    <div class="card" style="margin-bottom:10px">
      <div style="display:flex;flex-wrap:wrap;gap:6px 12px;align-items:baseline;margin-bottom:6px"><a href="/clientes/{{ $c->id }}"><b>{{ $c->displayName() }}</b></a> <span class="small muted">#{{ $c->codcli }} · {{ $c->machines->count() }} en flota</span></div>
      @foreach($g['items'] as $it)
        @php $safe = $it['brand'] !== '' && ($it['n'] >= 2 || $it['serial']); $val = base64_encode(json_encode(['c' => $c->id, 'key' => $it['key'], 'model' => $it['model']])); @endphp
        <label style="display:flex;gap:10px;align-items:flex-start;padding:6px 0;border-top:1px solid #eee;cursor:pointer">
          <input type="checkbox" name="items[]" value="{{ $val }}" class="fxc" data-safe="{{ $safe ? 1 : 0 }}" @checked($safe) style="margin-top:3px;width:18px;height:18px">
          <span style="flex:1 1 auto"><b>{{ trim($it['brand'].' '.$it['model']) ?: $it['ref'] }}</b>@if($it['serial']) · serie <code>{{ $it['serial'] }}</code>@endif
            @if($it['brand'] === '')<span class="badge warn">sin marca</span>@endif
            <span class="small muted">· {{ $it['n'] }} {{ $it['n'] === 1 ? 'documento' : 'documentos' }} · último: {{ $it['doc'] }}@if($it['last']) ({{ $it['last'] }})@endif · en FactuSOL pone «{{ $it['ref'] }}»</span></span>
        </label>
      @endforeach
    </div>
  @endforeach
</form>
<script>
function fxCount(){document.getElementById('fxn').textContent=document.querySelectorAll('.fxc:checked').length;}
function fxAll(v){document.querySelectorAll('.fxc').forEach(function(c){c.checked=v;});fxCount();}
function fxSafe(){document.querySelectorAll('.fxc').forEach(function(c){c.checked=c.dataset.safe==='1';});fxCount();}
document.getElementById('fx').addEventListener('change',fxCount);fxCount();
</script>
@endif
@endsection
