@extends('layout')
@section('title', 'Tarifas')
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); @endphp
<div class="head">
  <div><h1>Tarifas</h1><div class="muted small">Las 6 tarifas de FactuSOL, qué clientes tienen cada una y cuánto se vende por tarifa. Los precios se cambian en FactuSOL (pronto desde aquí).</div></div>
</div>

<div class="card">
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Tarifa</th><th>A quién se aplica</th><th class="num">Margen</th><th class="num">Clientes</th><th class="num">Albaranes {{ date('Y') }}</th><th class="num">Ventas {{ date('Y') }}</th></tr></thead>
    <tbody>
    @foreach($tariffs as $t)@php $s = $sales[$t->code] ?? null; @endphp
      <tr>
        <td><strong>Tarifa {{ $t->code }}</strong></td><td>{{ $t->detail() }}</td>
        <td class="num">{{ $fmt($t->margin) }} %</td>
        <td class="num"><a href="/tarifas?clientes={{ $t->code }}">{{ $customers[$t->code] ?? 0 }}</a></td>
        <td class="num">{{ $s?->n ?? 0 }}</td><td class="num">{{ eur($s?->t ?? 0) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div>

@if($clientsByTariff->isNotEmpty())
<div class="card mt">
  <h2>Clientes en tarifa {{ request('clientes') }} <span class="muted small">({{ $clientsByTariff->count() }})</span></h2>
  <div class="scroll"><table class="tbl"><tbody>
    @foreach($clientsByTariff as $c)<tr><td><a href="/clientes/{{ $c->id }}">{{ $c->displayName() }}</a></td><td class="small muted">{{ $c->nif }}</td><td class="small">{{ $c->city }}</td><td class="small">{{ $c->bestPhone() }}</td></tr>@endforeach
  </tbody></table></div>
</div>
@endif

<div class="card mt">
  <h2>Comprobar precios de un artículo</h2>
  <form class="row" method="get" action="/tarifas" style="margin-bottom:10px">
    <div style="flex:3 1 260px"><label for="q">Artículo</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Referencia o descripción"></div>
    <button class="btn" type="submit">Ver precios</button>
  </form>
  @if($check->isNotEmpty())
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Artículo</th><th class="num">Coste</th>@foreach($tariffs as $t)<th class="num">T{{ $t->code }} <span class="small muted">{{ $fmt($t->margin) }}%</span></th>@endforeach</tr></thead>
    <tbody>
    @foreach($check as $row)@php $a = $row['a']; @endphp
      <tr><td><a href="/articulos/{{ rawurlencode($a->codart) }}"><code class="art">{{ $a->codart }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($a->description, 40) }}</div></td><td class="num">{{ eur($a->cost) }}</td>
        @foreach($tariffs as $t)@php $pv = (float) ($row['p'][$t->code]->price ?? 0); @endphp<td class="num {{ $pv > 0 && $pv < $a->cost ? 'bad' : '' }}">{{ eur($pv) }}</td>@endforeach</tr>
    @endforeach
    </tbody>
  </table></div>
  @elseif($q !== '')<p class="muted" style="margin:0">Nada con «{{ $q }}».</p>@endif
</div>

<div class="card mt">
  <h2>Artículos con stock vendidos por debajo del coste <span class="muted small">({{ $belowCount }})</span></h2>
  <p class="small muted" style="margin:0 0 8px">Precio de alguna tarifa menor que el coste actual. Suele pasar cuando sube el coste de compra y no se actualiza la tarifa.</p>
  @if($below->isEmpty())<p class="muted" style="margin:0">Ninguno. Todo se vende por encima del coste.</p>
  @else
  <div class="scroll"><table class="tbl"><thead><tr><th>Artículo</th><th class="num">Stock</th><th class="num">Coste</th><th>Tarifa</th><th class="num">Precio</th><th class="num">Pérdida/ud.</th></tr></thead><tbody>
    @foreach($below as $b)<tr><td><a href="/articulos/{{ rawurlencode($b->codart) }}"><code class="art">{{ $b->codart }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($b->description, 45) }}</div></td><td class="num">{{ $fmt($b->stock) }}</td><td class="num">{{ eur($b->cost) }}</td><td>Tarifa {{ $b->tariff }}</td><td class="num">{{ eur($b->price) }}</td><td class="num"><span class="badge bad">−{{ eur($b->cost - $b->price) }}</span></td></tr>@endforeach
  </tbody></table></div>
  @endif
</div>
@endsection
