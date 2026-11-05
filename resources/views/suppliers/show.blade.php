@extends('layout')
@section('title', $supplier->name)
@section('content')
@php
  $uds = $metrica === 'uds';
  $ivaTxt = $supplier->vat === 'con' ? 'con IVA' : 'sin IVA';
  $fmt = fn($v) => $uds ? number_format($v, 0, ',', '.').' u' : eur($v);
  $max = max(1, max(array_map(fn($v) => $v['amount'], $byYear) ?: [1]));
  $totalAll = array_sum(array_map(fn($v) => $v['amount'], $byYear));
  $famNames = array_keys($famTop);
  $cA = $byYear[$cur]['amount'] ?? 0; $pA = $byYear[$prev]['amount'] ?? 0;
  $ytd = (float) $supplier->ytd_prev;
  $partial = $cur === $maxYear;
  $base = $partial ? ($uds ? 0 : $ytd) : $pA;
  $vs = $base > 0 ? ($cA - $base) / $base * 100 : null;
  $qsBase = ['familia' => $family, 'q' => $q, 'anio' => $focus, 'ca' => $ca, 'cb' => $cb];
@endphp
<div class="head">
  <div><a class="small" href="/proveedores">← Proveedores</a>
  <h1 style="margin:6px 0"><img src="{{ $logo }}" alt="{{ $supplier->name }}" style="max-width:240px;width:100%;height:auto;border-radius:6px;display:block" onerror="this.replaceWith(document.createTextNode(this.alt))"></h1>
  <div class="muted small">Código {{ $supplier->code }} · cuenta {{ $supplier->account }} · {{ $nRefs }} referencias{{ $family !== '' ? ' en la familia '.$family : '' }} · {{ $supplier->source }}</div>
  <form method="post" action="/proveedores/{{ $supplier->id }}/iva" class="vat">@csrf
    <span class="badge">Importes {{ $ivaTxt }}</span>
    <select name="vat" onchange="this.form.submit()" aria-label="Los importes incluyen IVA"><option value="sin" @selected($supplier->vat !== 'con')>Sin IVA (base imponible)</option><option value="con" @selected($supplier->vat === 'con')>Con IVA incluido</option></select>
  </form></div>
</div>

<div class="tabs">
  <a class="{{ $uds ? '' : 'on' }}" href="?{{ http_build_query($qsBase + ['metrica' => 'eur']) }}">Importe (€ {{ $ivaTxt }})</a>
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

<div class="card mt">
  <h2>Diagramas — {{ $focus ?: 'todo el histórico' }} · {{ $uds ? 'unidades' : 'importe '.$ivaTxt }}</h2>
  <div class="muted small">Pasa el ratón por un quesito para ver cuál es, cuánto suma y su porcentaje.</div>
  @php
    $pf = array_map(fn($f) => [$f['f'], $f['cur']], $famRows);
    if (count($pf) > 8) { $pf = array_merge(array_slice($pf, 0, 8), [['Otras familias', array_sum(array_map(fn($x) => $x[1], array_slice($pf, 8)))]]); }
    $py = array_map(fn($y) => [(string) $y, $byYear[$y]['amount']], $years);
  @endphp
  <div class="pies">
    @include('suppliers._pie', ['title' => 'Por familia', 'items' => $pf, 'fmt' => $fmt])
    @include('suppliers._pie', ['title' => 'Top referencias', 'items' => $pieRefs, 'fmt' => $fmt])
    @include('suppliers._pie', ['title' => 'Reparto por año', 'items' => $py, 'fmt' => $fmt])
  </div>
</div>
<script>
document.querySelectorAll('[data-pie]').forEach(function(box){
  var v=box.querySelector('.pc-v'), l=box.querySelector('.pc-l'), def=v&&v.getAttribute('data-def');
  box.querySelectorAll('.sl').forEach(function(p){
    p.addEventListener('mouseenter',function(){box.classList.add('hov');p.classList.add('on');l.textContent=p.dataset.l.length>16?p.dataset.l.slice(0,15)+'…':p.dataset.l;v.textContent=p.dataset.v;});
    p.addEventListener('mouseleave',function(){box.classList.remove('hov');p.classList.remove('on');l.textContent='Total';v.textContent=def;});
  });
});
</script>

<div class="grid g2 mt">
  <div class="card">
    <h2>{{ $uds ? 'Unidades compradas por año' : 'Compras por año (€ '.$ivaTxt.')' }}</h2>
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
    <h2>Por familia y año {{ $uds ? '(unidades)' : '(€ '.$ivaTxt.')' }}</h2>
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

<div class="card mt" id="comparador">
  <h2>Comparador de años</h2>
  <form class="row" method="get" action="#comparador" style="margin-bottom:10px">
    <input type="hidden" name="metrica" value="{{ $metrica }}"><input type="hidden" name="anio" value="{{ $focus }}"><input type="hidden" name="familia" value="{{ $family }}"><input type="hidden" name="q" value="{{ $q }}">
    <div style="flex:1 1 120px"><label for="ca">Año base</label><select id="ca" name="ca" onchange="this.form.submit()">@foreach($years as $y)<option @selected($y === $ca)>{{ $y }}</option>@endforeach</select></div>
    <div style="flex:1 1 120px"><label for="cb">Comparar con</label><select id="cb" name="cb" onchange="this.form.submit()">@foreach($years as $y)<option @selected($y === $cb)>{{ $y }}</option>@endforeach</select></div>
  </form>
  @php
    $var = fn($a, $b) => $a > 0 ? ($b - $a) / $a * 100 : null;
    $fv = function($v) { return $v === null ? '—' : sprintf('%+.0f %%', $v); };
    $cls = fn($v) => $v === null ? '' : ($v < 0 ? 'down' : 'up');
    $rowsK = [
      ['Unidades', $cmpTot['a']['q'], $cmpTot['b']['q'], fn($v) => number_format($v, 0, ',', '.')],
      ['Importe ('.$ivaTxt.')', $cmpTot['a']['a'], $cmpTot['b']['a'], fn($v) => eur($v)],
      ['Precio medio por unidad ('.$ivaTxt.')', $cmpTot['a']['p'], $cmpTot['b']['p'], fn($v) => eur($v)],
      ['Referencias distintas', $cmpTot['a']['n'], $cmpTot['b']['n'], fn($v) => number_format($v, 0, ',', '.')],
    ];
  @endphp
  @if($cb === $maxYear || $ca === $maxYear)<div class="muted small">{{ $maxYear }} está en curso: la comparación contra un año completo sale a la baja por falta de meses.</div>@endif
  <div class="scroll"><table class="tbl"><thead><tr><th></th><th class="num">{{ $ca }}</th><th class="num">{{ $cb }}</th><th class="num">Diferencia</th><th class="num">Var.</th></tr></thead><tbody>
  @foreach($rowsK as [$lab, $va, $vb, $f])
    @php $vv = $var($va, $vb); @endphp
    <tr><td>{{ $lab }}</td><td class="num">{{ $f($va) }}</td><td class="num">{{ $f($vb) }}</td><td class="num">{{ ($vb - $va) >= 0 ? '+' : '−' }}{{ $f(abs($vb - $va)) }}</td><td class="num {{ $cls($vv) }}">{{ $fv($vv) }}</td></tr>
  @endforeach
  </tbody></table></div>

  <h3 style="margin:18px 0 6px;font-size:15px">Por familia</h3>
  <div class="scroll"><table class="tbl"><thead><tr><th>Familia</th><th class="num">Uds {{ $ca }}</th><th class="num">Uds {{ $cb }}</th><th class="num">Var.</th><th class="num">€ {{ $ca }} ({{ $ivaTxt }})</th><th class="num">€ {{ $cb }}</th><th class="num">Var.</th></tr></thead><tbody>
  @foreach($cmpFam as $x)
    @php $v1 = $var($x['qa'], $x['qb']); $v2 = $var($x['aa'], $x['ab']); @endphp
    <tr><td>{{ $x['f'] }}</td><td class="num">{{ $x['qa'] }}</td><td class="num">{{ $x['qb'] }}</td><td class="num {{ $cls($v1) }}">{{ $fv($v1) }}</td><td class="num">{{ eur($x['aa']) }}</td><td class="num">{{ eur($x['ab']) }}</td><td class="num {{ $cls($v2) }}">{{ $fv($v2) }}</td></tr>
  @endforeach
  </tbody></table></div>

  <div class="grid g2 mt">
    @foreach([['Más suben', $risers, 'up'], ['Más bajan', $fallers, 'down']] as [$tt, $list2, $c2])
    <div>
      <h3 style="margin:8px 0 6px;font-size:15px">{{ $tt }} ({{ $uds ? 'unidades' : 'importe' }})</h3>
      @forelse($list2 as $m)
        <div class="hb" style="grid-template-columns:minmax(80px,1fr) auto auto"><a href="/proveedores/{{ $supplier->id }}/referencias/{{ $m['id'] }}">{{ $m['ref'] }}</a><span class="muted small">{{ $fmt($m['va']) }} → {{ $fmt($m['vb']) }}</span><b class="{{ $c2 }}">{{ $m['d'] > 0 ? '+' : '−' }}{{ $fmt(abs($m['d'])) }}</b></div>
      @empty<div class="muted small">Sin cambios.</div>@endforelse
    </div>
    @endforeach
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
