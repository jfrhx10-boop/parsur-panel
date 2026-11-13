@extends('layout')
@section('title', 'Artículos por máquina')
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); @endphp
<div class="head">
  <div><h1>Artículos pedidos por máquina</h1>
    <div class="muted small">Cada máquina de la flota con todo lo que su cliente ha pedido para ella desde {{ $from }}: albaranes (servido) y pedidos abiertos, de cualquier artículo. Se cruza el modelo con la referencia del documento. {{ count($rows) }} máquinas con compras. {{ $unmatched }} documentos con referencia no casan con ninguna máquina de la flota (<a href="/flota/cruce">ver máquinas que faltan</a>).</div></div>
  <div class="row" style="gap:6px"><a class="btn ghost sm" href="/flota/articulos?csv=1&desde={{ $from }}">Descargar Excel (CSV)</a><a class="btn ghost sm" href="/flota/cruce">Máquinas que faltan</a></div>
</div>
<form class="card row" method="get" action="/flota/articulos" style="margin-bottom:12px">
  <div style="flex:0 1 160px"><label for="desde">Documentos desde</label><select id="desde" name="desde" onchange="this.form.submit()">@for($y = (int) date('Y'); $y >= 2023; $y--)<option value="{{ $y }}" @selected($y === $from)>{{ $y }}</option>@endfor</select></div>
  <div style="flex:1 1 240px"><label for="fq">Filtrar por cliente, máquina o artículo</label><input id="fq" type="search" placeholder="Escribe para filtrar…" autocomplete="off"></div>
</form>
@forelse($rows as $r)
  @php $c = $r['customer']; $m = $r['machine']; @endphp
  <details class="card fa" style="margin-bottom:8px" data-t="{{ mb_strtolower($c->displayName().' '.$m->brand.' '.$m->model.' '.collect($r['arts'])->map(fn ($a) => $a['art'].' '.$a['description'])->implode(' ')) }}">
    <summary style="cursor:pointer;display:flex;flex-wrap:wrap;gap:4px 12px;align-items:baseline"><a href="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}" onclick="event.stopPropagation()"><b>{{ trim($m->brand.' '.$m->model) }}</b></a>@if($m->serial) <span class="small muted">serie {{ $m->serial }}</span>@endif <a href="/clientes/{{ $c->id }}" class="small" onclick="event.stopPropagation()">{{ $c->displayName() }}</a> <span class="small muted">· {{ $r['docs'] }} {{ $r['docs'] === 1 ? 'documento' : 'documentos' }} · {{ count($r['arts']) }} {{ count($r['arts']) === 1 ? 'artículo' : 'artículos' }}</span></summary>
    <div style="overflow-x:auto;margin-top:8px"><table class="tbl"><thead><tr><th>Artículo</th><th>Descripción</th><th class="num">Servido</th><th class="num">Pedido abierto</th><th class="num">Veces</th><th>Último</th></tr></thead><tbody>
      @foreach($r['arts'] as $a)<tr><td><a href="/articulos/{{ rawurlencode($a['art']) }}"><code class="art">{{ $a['art'] }}</code></a></td><td class="small">{{ $a['description'] }}</td><td class="num">{{ $a['served'] ? $fmt($a['served']) : '' }}</td><td class="num">{{ $a['open'] ? $fmt($a['open']) : '' }}</td><td class="num">{{ $a['n'] }}</td><td class="small">{{ $a['last']?->format('d/m/Y') }}</td></tr>@endforeach
    </tbody></table></div>
  </details>
@empty
  <div class="card"><p class="muted" style="margin:0">Ninguna máquina de la flota casa todavía con la referencia de un documento. Si faltan máquinas en las flotas, empieza por <a href="/flota/cruce">añadirlas</a>.</p></div>
@endforelse
<script>
document.getElementById('fq').addEventListener('input',function(){var q=this.value.toLowerCase().trim();document.querySelectorAll('.fa').forEach(function(d){d.style.display=(!q||d.dataset.t.indexOf(q)>-1)?'':'none';});});
</script>
@endsection
