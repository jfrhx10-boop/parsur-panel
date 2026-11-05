@extends('layout')
@section('title', $supplier->name)
@section('content')
@php
  $uds = $metrica === 'uds';
  $fmt = fn($v) => $uds ? number_format($v, 0, ',', '.').' u' : eur($v);
  $max = max(1, max(array_map(fn($v) => $v['amount'], $byYear) ?: [1]));
  $totalAll = array_sum(array_map(fn($v) => $v['amount'], $byYear));
  $famNames = array_keys($famTop);
  $cA = $byYear[$cur]['amount'] ?? 0; $pA = $byYear[$prev]['amount'] ?? 0;
  $ytd = (float) $supplier->ytd_prev;
  $partial = $cur === $maxYear;
  $base = $partial ? ($uds ? 0 : $ytd) : $pA;
  $vs = $base > 0 ? ($cA - $base) / $base * 100 : null;
  $qsBase = ['familia' => $family, 'q' => $q, 'anio' => $focus];
@endphp
<div class="head">
  <div><a class="small" href="/proveedores">← Proveedores</a>
  @if($logo)<div style="margin:6px 0"><img src="{{ $logo }}" alt="{{ $supplier->name }}" style="max-width:240px;width:100%;height:auto;border-radius:6px;display:block"></div>@else<h1>{{ $supplier->name }}</h1>@endif
  <div class="muted small">Código {{ $supplier->code }} · cuenta {{ $supplier->account }} · {{ $nRefs }} referencias{{ $family !== '' ? ' en la familia '.$family : '' }} · {{ $supplier->source }}</div></div>
</div>

<div class="tabs">
  <a class="{{ $uds ? '' : 'on' }}" href="?{{ http_build_query($qsBase + ['metrica' => 'eur']) }}">Importe (€)</a>
  <a class="{{ $uds ? 'on' : '' }}" href="?{{ http_build_query($qsBase + ['metrica' => 'uds']) }}">Unidades (volumen)</a>
</div>
<form class="card row" method="get" style="margin-bottom:14px"><input type="hidden" name="metrica" value="{{ $metrica }}">
  <div style="flex:1 1 140px"><label for="anio">Año</label>
    <select id="anio" name="anio" onchange="this.form.submit()"><option value="">Todos / último</option>@foreach($years as $y)<option value="{{ $y }}" @selected($focus === $y)>{{ $y }}{{ $y == $maxYear ? ' (en curso)' : '' }}</option>@endforeach</select></div>
  <div style="flex:1 1 140px"><label for="fam">Familia</label>
    <select id="fam" name="familia" onchange="this.form.submit()"><option value="">Todas</option>@foreach($families as $f)<option @selected($f === $family)>{{ $f }}</option>@endforeach</select></div>
  <div style="flex:2 1 200px"><label for="q">Buscar referencia</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="SO 242…"></div>
  <button class="btn" type="submit">Aplicar</button>
  @if($focus || $family !== '' || $q !== '')<a class="btn ghost" href="/proveedores/{{ $supplier->id }}">Quitar filtros</a>@endif
</form>

<div class="grid g4">
  <div class="card kpi"><div class="l">Total histórico</div><div class="v">{{ $fmt($totalAll) }}</div></div>
  <div class="card kpi"><div class="l">{{ $prev }}</div><div class="v">{{ $fmt($pA) }}</div><div class="s">{{ $byYear[$prev]['refs'] ?? 0 }} referencias</div></div>
  <div class="card kpi"><div class="l">{{ $cur }}{{ $partial ? ' (hasta hoy)' : '' }}</div><div class="v">{{ $fmt($cA) }}</div><div class="s">{{ $byYear[$cur]['refs'] ?? 0 }} referencias</div></div>
  <div class="card kpi"><div class="l">{{ $cur }} vs {{ $partial ? 'mismo periodo de '.$prev : $prev }}</div>
    <div class="v {{ $vs !== null && $vs < 0 ? 'down' : 'up' }}">{{ $vs === null ? '—' : sprintf('%+.0f %%', $vs) }}</div>
    <div class="s">{{ $base > 0 ? 'antes: '.$fmt($base) : 'sin dato' }}</div></div>
</div>

<div class="grid g2 mt">
  <div class="card">
    <h2>{{ $uds ? 'Unidades compradas por año' : 'Compras por año (€)' }}</h2>
    <div class="bars">
      @foreach($years as $y)
        <div class="col"><div class="val">{{ $fmt($byYear[$y]['amount']) }}</div>
        <div class="bar" style="height:{{ round($byYear[$y]['amount'] / $max * 150) }}px"></div></div>
      @endforeach
    </div>
    <div class="bars-x">@foreach($years as $y)<div>{{ $y }}{{ $y == $cur ? '*' : '' }}</div>@endforeach</div>
    <div class="muted small mt">* año en curso, incompleto.</div>
  </div>
  <div class="card">
    <h2>Por familia y año {{ $uds ? '(unidades)' : '(€)' }}</h2>
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
  <h2>Top 15 referencias — {{ $focus ? 'compras de '.$focus : ($uds ? 'unidades históricas' : 'importe histórico') }}{{ $family !== '' ? ' · '.$family : '' }}</h2>
  @php $tmax = max(1, collect($top)->max('metric')); @endphp
  @foreach($top as $r)
    <div class="hb"><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $r['id'] }}">{{ $r['ref'] }}</a>
      <div class="t"><span style="width:{{ round($r['metric'] / $tmax * 100) }}%"></span></div><div>{{ $fmt($r['metric']) }}</div></div>
  @endforeach
</div>

<div class="card mt">
  <h2>Resumen por familia — {{ $focus ?: 'todo el histórico' }}</h2>
  <div class="scroll"><table class="tbl"><thead><tr><th>Familia</th><th class="num">Importe</th>@if($focus)<th class="num">{{ $prev }}</th><th class="num">Var.</th>@endif<th class="num">Refs</th></tr></thead><tbody>
  @foreach($famRows as $fr)
    <tr><td><a href="?{{ http_build_query(['anio' => $focus, 'familia' => $fr['f'], 'metrica' => $metrica]) }}">{{ $fr['f'] }}</a></td><td class="num">{{ $fmt($fr['cur']) }}</td>
    @if($focus)<td class="num">{{ $fmt($fr['prev']) }}</td><td class="num {{ $fr['prev'] > 0 && $fr['cur'] < $fr['prev'] ? 'down' : 'up' }}">{{ $fr['prev'] > 0 ? sprintf('%+.0f %%', ($fr['cur'] - $fr['prev']) / $fr['prev'] * 100) : '—' }}</td>@endif
    <td class="num">{{ $fr['refs'] }}</td></tr>
  @endforeach
  </tbody></table></div>
</div>

@foreach([['Compradas en '.$prev.' y ninguna en '.$cur, $lost, 'Posible rotura de stock o cambio de proveedor'], ['Caída de más del 50 % en '.$cur, $drop, 'Compras que bajan respecto a '.$prev], ['Referencias dormidas', $dormant, 'Más de 100 € históricos y sin compras desde '.($prev - 1)]] as [$title, $rows, $hint])
<div class="card mt">
  <h2>{{ $title }} <span class="badge">{{ count($rows) }}</span></h2>
  <div class="muted small">{{ $hint }}</div>
  @if($rows)
  <div class="scroll"><table class="tbl"><thead><tr><th>Referencia</th><th class="num">Total histórico</th><th class="num">Impacto</th></tr></thead><tbody>
    @foreach(array_slice($rows, 0, 12) as $r)
      <tr><td><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $r['id'] }}">{{ $r['ref'] }}</a></td><td class="num">{{ $fmt($r['tot']) }}</td><td class="num">{{ $fmt($r['impacto']) }}</td></tr>
    @endforeach
  </tbody></table></div>
  @endif
</div>
@endforeach

<div class="card mt">
  <h2>Todas las referencias {{ $focus ? '(ordenadas por '.$focus.')' : '' }}</h2>
  <div class="muted small">{{ $totalList }} resultados</div>
  <div class="scroll"><table class="tbl"><thead><tr><th>Referencia</th><th class="num">Total</th>@foreach($years as $y)<th class="num hide-m">{{ $y }}</th>@endforeach</tr></thead><tbody>
  @foreach($list as $r)
    <tr><td><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $r['id'] }}">{{ $r['ref'] }}</a></td><td class="num">{{ $fmt($r['tot']) }}</td>
    @foreach($years as $y)<td class="num hide-m">{{ isset($r['y'][$y]) ? $r['y'][$y][0].' u · '.eur($r['y'][$y][1]) : '—' }}</td>@endforeach</tr>
  @endforeach
  </tbody></table></div>
  @php $last = max(1, (int) ceil($totalList / $per)); $qs = ['q' => $q, 'familia' => $family, 'anio' => $focus, 'metrica' => $metrica]; @endphp
  @if($last > 1)
  <div class="pager">
    @if($page > 1)<a class="btn ghost sm" href="?{{ http_build_query($qs + ['page' => $page - 1]) }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $page }} de {{ $last }}</span>
    @if($page < $last)<a class="btn ghost sm" href="?{{ http_build_query($qs + ['page' => $page + 1]) }}">Siguientes →</a>@endif
  </div>
  @endif
</div>
@endsection
