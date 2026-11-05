@extends('layout')
@section('title', 'Recompra')
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); @endphp
<div class="head">
  <div><h1>Recompra de filtros</h1><div class="muted small">Clientes que compran un filtro o aceite con regularidad (3 o más veces) y a los que les toca según su ritmo. Calculado con los albaranes de 2025 y 2026.</div></div>
  <button class="btn ghost sm no-print" type="button" onclick="window.print()">🖨 Imprimir lista</button>
</div>
<div class="tabs">
  <a href="/recompra" class="{{ $only === 'toca' ? 'on' : '' }}">Les toca ya ({{ $counts['toca'] }})</a>
  <a href="/recompra?ver=pronto" class="{{ $only === 'pronto' ? 'on' : '' }}">Próximos 30 días ({{ $counts['pronto'] }})</a>
  <a href="/recompra?ver=todo" class="{{ $only === 'todo' ? 'on' : '' }}">Todo lo regular ({{ $counts['todo'] }})</a>
</div>
<div class="card">
  @if($byCustomer->isEmpty())<p class="muted" style="margin:0">Nada por ahora.</p>
  @else
  <div class="docs">
    @foreach($byCustomer as $codcli => $g)
      @php $c = $customers[$codcli] ?? null; $tel = $c ? preg_replace('/\D+/', '', (string) $c->bestPhone()) : ''; $maxLate = $g->max('late'); @endphp
      <details class="doc"{{ $loop->index < 5 ? ' open' : '' }}>
        <summary>
          <span class="doc-ic av">{{ mb_substr($c ? $c->displayName() : 'C', 0, 1) }}</span>
          <span class="doc-main">
            <span class="doc-n">@if($c){{ $c->displayName() }}@else Cliente {{ $codcli }}@endif</span>
            <span class="doc-m">{{ $g->count() }} {{ $g->count() === 1 ? 'referencia' : 'referencias' }}@if($c?->city) · {{ $c->city }}@endif @if($c?->bestPhone()) · {{ $c->bestPhone() }}@endif</span>
          </span>
          <span class="doc-r">
            @if($c && $tel)<a class="btn sm wa" target="_blank" rel="noopener" onclick="event.stopPropagation()" href="https://wa.me/{{ str_starts_with($tel, '34') ? $tel : '34'.$tel }}?text={{ rawurlencode('Hola, te escribimos de Recambios Parsur. Por las fechas te debe de tocar cambio de '.$g->map(fn ($r) => \Illuminate\Support\Str::limit(mb_strtolower($r->description), 30))->take(3)->join(', ').'. Lo tenemos en stock, ¿te lo preparamos?') }}">WhatsApp</a>@endif
            @if($maxLate > 0)<span class="badge {{ $maxLate > 60 ? 'bad' : 'warn' }}">{{ $maxLate }} días de retraso</span>@else<span class="badge ok">en {{ abs($maxLate) }} días</span>@endif
          </span>
        </summary>
        <div style="margin:0 0 10px 26px">
          <table class="tbl"><thead><tr><th>Artículo</th><th>Máquina</th><th class="num">Veces</th><th class="num">Uds./vez</th><th>Cada</th><th>Última</th><th>Le toca</th><th class="num">Stock</th></tr></thead><tbody>
            @foreach($g->sortByDesc('late') as $r)@php $a = $articles[$r->art] ?? null; @endphp
              <tr>
                <td><a href="/articulos/{{ rawurlencode($r->art) }}"><code class="art">{{ $r->art }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($r->description, 40) }}</div></td>
                <td class="small">{{ $r->ref }}</td>
                <td class="num">{{ $r->buys }}</td><td class="num">{{ $fmt($r->perBuy) }}</td>
                <td class="small">{{ $r->interval }} días</td><td class="small">{{ $r->last->format('d/m/Y') }}</td>
                <td>@if($r->late > 0)<span class="bad">hace {{ $r->late }} días</span>@else<span class="small">en {{ abs($r->late) }} días</span>@endif</td>
                <td class="num">@if($a)@if($a->stock > 0)<strong>{{ $fmt($a->stock) }}</strong>@else<span class="badge warn">0</span>@endif @else—@endif</td>
              </tr>
            @endforeach
          </tbody></table>
          @if($c)<a class="btn ghost sm" href="/clientes/{{ $c->id }}" style="margin-top:8px">Ficha del cliente</a>@endif
        </div>
      </details>
    @endforeach
  </div>
  @endif
</div>
@endsection
