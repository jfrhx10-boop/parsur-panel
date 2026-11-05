@extends('layout')
@section('title', $supplier->name)
@section('content')
@php
  $max = max(1, max(array_map(fn($v) => $v['amount'], $byYear) ?: [1]));
  $totalAll = array_sum(array_map(fn($v) => $v['amount'], $byYear));
  $famNames = array_keys($famTop);
  $cA = $byYear[$cur]['amount'] ?? 0; $pA = $byYear[$prev]['amount'] ?? 0;
  $ytd = (float) $supplier->ytd_prev;
  $vs = $ytd > 0 ? ($cA - $ytd) / $ytd * 100 : null;
@endphp
<div class="head">
  <div><a class="small" href="/proveedores">← Proveedores</a><h1>{{ $supplier->name }}</h1>
  <div class="muted small">Código {{ $supplier->code }} · cuenta {{ $supplier->account }} · {{ $nRefs }} referencias · {{ $supplier->source }}</div></div>
</div>

<div class="grid g4">
  <div class="card kpi"><div class="l">Total histórico</div><div class="v">{{ eur($totalAll) }}</div></div>
  <div class="card kpi"><div class="l">{{ $prev }}</div><div class="v">{{ eur($pA) }}</div><div class="s">{{ $byYear[$prev]['refs'] ?? 0 }} referencias</div></div>
  <div class="card kpi"><div class="l">{{ $cur }} (hasta hoy)</div><div class="v">{{ eur($cA) }}</div><div class="s">{{ $byYear[$cur]['refs'] ?? 0 }} referencias</div></div>
  <div class="card kpi"><div class="l">{{ $cur }} vs mismo periodo {{ $prev }}</div>
    <div class="v {{ $vs !== null && $vs < 0 ? 'down' : 'up' }}">{{ $vs === null ? '—' : sprintf('%+.0f %%', $vs) }}</div>
    <div class="s">{{ $ytd > 0 ? 'antes: '.eur($ytd) : 'sin dato' }}</div></div>
</div>

<div class="grid g2 mt">
  <div class="card">
    <h2>Compras por año (€)</h2>
    <div class="bars">
      @foreach($years as $y)
        <div class="col"><div class="val">{{ eur($byYear[$y]['amount']) }}</div>
        <div class="bar" style="height:{{ round($byYear[$y]['amount'] / $max * 150) }}px"></div></div>
      @endforeach
    </div>
    <div class="bars-x">@foreach($years as $y)<div>{{ $y }}{{ $y == $cur ? '*' : '' }}</div>@endforeach</div>
    <div class="muted small mt">* año en curso, incompleto.</div>
  </div>
  <div class="card">
    <h2>Por familia y año</h2>
    <div class="bars">
      @foreach($years as $y)
        @php $tot = max(1, array_sum(array_map(fn($f) => $f[$y] ?? 0, $famTop))); @endphp
        <div class="col"><div class="bar" style="height:{{ round(($byYear[$y]['amount'] / $max) * 170) }}px">
          @foreach($famNames as $i => $f)
            <i class="c{{ min($i, 6) }}" style="height:{{ round(($famTop[$f][$y] ?? 0) / $tot * 100, 1) }}%"></i>
          @endforeach
        </div></div>
      @endforeach
    </div>
    <div class="bars-x">@foreach($years as $y)<div>{{ $y }}</div>@endforeach</div>
    <div class="legend">@foreach($famNames as $i => $f)<span><b class="c{{ min($i, 6) }}"></b>{{ $f }}</span>@endforeach</div>
  </div>
</div>

<div class="card mt">
  <h2>Top 15 referencias por importe histórico</h2>
  @php $tmax = max(1, collect($top)->max('total')); @endphp
  @foreach($top as $r)
    <div class="hb"><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $r['id'] }}">{{ $r['ref'] }}</a>
      <div class="t"><span style="width:{{ round($r['total'] / $tmax * 100) }}%"></span></div><div>{{ eur($r['total']) }}</div></div>
  @endforeach
</div>

@foreach([['Compradas en '.$prev.' y ninguna en '.$cur, $lost, 'Posible rotura de stock o cambio de proveedor'], ['Caída de más del 50 % en '.$cur, $drop, 'Compras que bajan respecto a '.$prev], ['Referencias dormidas', $dormant, 'Más de 100 € históricos y sin compras desde '.($prev - 1)]] as [$title, $rows, $hint])
<div class="card mt">
  <h2>{{ $title }} <span class="badge">{{ count($rows) }}</span></h2>
  <div class="muted small">{{ $hint }}</div>
  @if($rows)
  <div class="scroll"><table class="tbl"><thead><tr><th>Referencia</th><th class="num">Total histórico</th><th class="num">Impacto</th></tr></thead><tbody>
    @foreach(array_slice($rows, 0, 12) as $r)
      <tr><td><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $r['id'] }}">{{ $r['ref'] }}</a></td><td class="num">{{ eur($r['total']) }}</td><td class="num">{{ eur($r['impacto']) }}</td></tr>
    @endforeach
  </tbody></table></div>
  @endif
</div>
@endforeach

<div class="card mt">
  <h2>Todas las referencias</h2>
  <form class="row" method="get" style="margin-bottom:10px">
    <div style="flex:3 1 200px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="SO 242…"></div>
    <div style="flex:1 1 120px"><label for="f">Familia</label><select id="f" name="familia"><option value="">Todas</option>@foreach($families as $f)<option @selected($f === $family)>{{ $f }}</option>@endforeach</select></div>
    <button class="btn" type="submit">Filtrar</button>
  </form>
  <div class="muted small">{{ $totalList }} resultados</div>
  <div class="scroll"><table class="tbl"><thead><tr><th>Referencia</th><th class="num">Total</th>@foreach($years as $y)<th class="num hide-m">{{ $y }}</th>@endforeach</tr></thead><tbody>
  @foreach($list as $r)
    <tr><td><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $r['id'] }}">{{ $r['ref'] }}</a></td><td class="num">{{ eur($r['total']) }}</td>
    @foreach($years as $y)<td class="num hide-m">{{ isset($r['y'][$y]) ? $r['y'][$y][0].' u · '.eur($r['y'][$y][1]) : '—' }}</td>@endforeach</tr>
  @endforeach
  </tbody></table></div>
  @php $last = max(1, (int) ceil($totalList / $per)); $qs = ['q' => $q, 'familia' => $family]; @endphp
  @if($last > 1)
  <div class="pager">
    @if($page > 1)<a class="btn ghost sm" href="?{{ http_build_query($qs + ['page' => $page - 1]) }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $page }} de {{ $last }}</span>
    @if($page < $last)<a class="btn ghost sm" href="?{{ http_build_query($qs + ['page' => $page + 1]) }}">Siguientes →</a>@endif
  </div>
  @endif
</div>
@endsection
