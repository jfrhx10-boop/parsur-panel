@extends('layout')
@section('title', $codart)
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); $canSales = auth()->user()->canSeeSales(); @endphp
<div class="head">
  <div>
    <a class="small" href="/articulos">← Artículos</a>
    <h1><code class="art" style="font-size:22px">{{ $codart }}</code> <span style="font-weight:600">{{ $a?->description ?? $sales->first()?->description ?? $purchases->first()?->description }}</span></h1>
    <div class="muted small">@if($a?->family){{ $a->family }} · @endif @if($a?->category === 'aceites')<span class="oil oil-{{ \App\Services\ArticleCategories::oilType($a->description) }}">{{ \App\Services\ArticleCategories::OIL_TYPES[\App\Services\ArticleCategories::oilType($a->description)] }}</span> · @endif @if($a?->equivalent)Equivalencia: {{ $a->equivalent }} · @endif @if($a?->ean)EAN {{ $a->ean }} · @endif @if($a?->location)Ubicación {{ $a->location }} · @endif @if($hifi)<a href="/proveedores/{{ $hifi->supplier_id }}/referencias/{{ $hifi->id }}">Consumo HIFI</a> · @endif @if(!$a)<span class="badge warn">no está en el catálogo de FactuSOL</span>@endif</div>
  </div>
  <div>@include('articles._addq', ['codart' => $codart])</div>
</div>

<div class="grid g4">
  <div class="card kpi"><div class="l">Stock</div><div class="v">{{ $a ? $fmt($a->stock) : '—' }}</div><div class="s">@if($a && $a->stock_min > 0)mínimo {{ $fmt($a->stock_min) }}@elseif($a)disponible {{ $fmt($a->stock_available) }}@endif</div></div>
  @if($canSales)<div class="card kpi"><div class="l">Coste</div><div class="v">{{ $a ? eur($a->cost) : '—' }}</div><div class="s">@if($lastPurchase)última compra {{ eur($lastPurchase->price) }} · {{ $lastPurchase->doc->date?->format('d/m/Y') }}@endif</div>@if($a)@php $shCod = (int) ($a->codpro ?? 0); $shSup = $shCod > 0 ? \App\Models\FsSupplier::where('codpro', $shCod)->first() : null; @endphp<div class="s ce" data-art="{{ $a->codart }}"><button type="button" class="linkbtn ce-p" data-cod="{{ $shCod }}" title="Proveedor habitual del artículo en FactuSOL. Pulse para cambiarlo">Proveedor: <b class="ce-pv">{{ $shSup ? \Illuminate\Support\Str::limit($shSup->displayName(), 30) : 'sin asignar' }}</b></button></div>@endif</div>@else<div class="card kpi"><div class="l">Ubicación</div><div class="v">{{ $a?->location ?: '—' }}</div><div class="s">{{ $a?->family }}</div></div>@endif
  <div class="card kpi"><div class="l">PVP (tarifa 1, sin IVA)</div><div class="v">{{ $a ? eur($a->price) : '—' }}</div><div class="s">@if($canSales && $a && $a->marginPct() !== null)margen {{ $a->marginPct() }} %@endif</div></div>
  <div class="card kpi"><div class="l">Vendidos</div><div class="v">{{ $fmt($sales->sum('qty')) }}</div><div class="s">{{ $sales->count() }} líneas · {{ eur($sales->sum('total')) }}</div></div>
</div>

@if($a && $canSales)
<div class="card mt">
  <div class="head" style="margin-bottom:8px;align-items:center"><h2 style="margin:0">Precios por tarifa <span class="muted small">(sin IVA)</span></h2><a class="small" href="/tarifas">Ver tarifas →</a></div>
  <form method="post" action="/articulos/{{ rawurlencode($a->codart) }}/precios" class="pe" id="pe-{{ $a->id }}">
    @csrf
    <div class="pe-cost">
      <label for="pe-c">Coste</label>
      <div class="pe-in"><input id="pe-c" type="text" name="coste" inputmode="decimal" value="{{ number_format((float) $a->cost, 2, ',', '') }}"><span>€</span></div>
      <label class="pe-chk"><input type="checkbox" name="recalcular" value="1" checked> Recalcular los PVP de todas las tarifas con sus márgenes al cambiar el coste</label>
      @if($errors->any())<label class="pe-chk"><input type="checkbox" name="confirmar" value="1"> Confirmar cambio grande de coste</label>@endif
    </div>
    <div class="scroll"><table class="tbl"><thead><tr><th>Tarifa</th><th>A quién se aplica</th><th class="num">Clientes</th><th class="num">Margen</th><th class="num">Precio</th><th class="num">Beneficio/ud.</th></tr></thead><tbody>
    @foreach($tariffs as $t)@php $p = $prices[$t->code] ?? null; $pv = (float) ($p->price ?? ($t->code == 1 ? $a->price : 0)); $cost = (float) ($a->cost ?? 0); @endphp
      <tr><td><strong>Tarifa {{ $t->code }}</strong></td><td class="small">{{ $t->detail() }}</td><td class="num">{{ $tariffCustomers[$t->code] ?? 0 }}</td><td class="num small muted" data-margin>{{ $fmt($p->margin ?? $t->margin) }} %</td>
        <td class="num"><div class="pe-in"><input type="text" name="pvp[{{ $t->code }}]" inputmode="decimal" value="{{ number_format($pv, 2, ',', '') }}" data-tariff="{{ $t->code }}"><span>€</span></div></td>
        <td class="num">@if($cost > 0 && $pv > 0)<span class="{{ $pv < $cost ? 'badge bad' : '' }}">{{ eur($pv - $cost) }}</span> <span class="small muted">({{ round(($pv - $cost) / $pv * 100, 1) }} %)</span>@endif</td></tr>
    @endforeach
    </tbody></table></div>
    <div class="row" style="margin-top:10px;align-items:center;gap:10px">
      <button class="btn y" type="submit">Guardar precios en FactuSOL</button>
      <span class="small muted">Se escriben en FactuSOL al momento (coste del artículo y precio/margen de cada tarifa).</span>
    </div>
  </form>
</div>
@endif

@if($eqArticles->isNotEmpty() || $eqLoose->isNotEmpty())
<div class="card mt">
  <h2>Equivalencias y compatibles <span class="muted small">(de las observaciones y la descripción)</span></h2>
  @if($eqArticles->isNotEmpty())
  <div class="scroll"><table class="tbl"><thead><tr><th>Referencia</th><th>Descripción</th><th class="num">Stock</th>@if($canSales)<th class="num">Coste</th>@endif<th class="num">PVP</th><th class="hide-m">Ubic.</th></tr></thead><tbody>
    @foreach($eqArticles as $e)
      <tr><td><a href="/articulos/{{ rawurlencode($e->codart) }}"><code class="art">{{ $e->codart }}</code></a></td><td class="small">{{ $e->description }}</td><td class="num">@if($e->stock > 0)<strong>{{ $fmt($e->stock) }}</strong>@else<span class="muted">0</span>@endif</td>@if($canSales)<td class="num">{{ eur($e->cost) }}</td>@endif<td class="num">{{ eur($e->price) }}</td><td class="small muted hide-m">{{ $e->location }}</td></tr>
    @endforeach
  </tbody></table></div>
  @endif
  @if($eqLoose->isNotEmpty())
    <p class="small muted" style="margin:{{ $eqArticles->isNotEmpty() ? '10px' : '0' }} 0 6px">Otras referencias apuntadas que no están en el catálogo:</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach($eqLoose as $r)<a class="pt" href="/articulos?q={{ urlencode($r) }}"><b>{{ $r }}</b></a>@endforeach</div>
  @endif
</div>
@endif

<div class="grid g2 mt">
  <div class="card">
    <h2>Ventas por año</h2>
    @if($byYear->isEmpty())<p class="muted" style="margin:0">Sin ventas en los ejercicios sincronizados.</p>
    @else
    <table class="tbl"><thead><tr><th>Año</th><th class="num">Unidades</th><th class="num">Albaranes</th><th class="num">Importe</th><th class="num">Precio medio</th></tr></thead><tbody>
      @foreach($byYear as $y => $r)<tr><td><strong>{{ $y }}</strong></td><td class="num">{{ $fmt($r['qty']) }}</td><td class="num">{{ $r['n'] }}</td><td class="num">{{ eur($r['total']) }}</td><td class="num">{{ $r['qty'] > 0 ? eur($r['total'] / $r['qty']) : '—' }}</td></tr>@endforeach
    </tbody></table>
    @endif
    @if($machines->isNotEmpty())
      <h3 class="small muted" style="margin:14px 0 6px;text-transform:uppercase;letter-spacing:.4px">Máquinas a las que se ha vendido</h3>
      <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach($machines as $m => $n)<a class="pt" href="/buscar?q={{ urlencode($m) }}"><b>{{ $m }}</b><small>×{{ $n }}</small></a>@endforeach</div>
    @endif
  </div>
  <div class="card">
    <h2>Clientes que lo compran</h2>
    @if($topCustomers->isEmpty())<p class="muted" style="margin:0">Todavía nadie.</p>
    @else
    <table class="tbl"><thead><tr><th>Cliente</th><th class="num">Uds.</th><th class="num">Importe</th><th>Última</th></tr></thead><tbody>
      @foreach($topCustomers as $t)@php $c = $customers[$t['codcli']] ?? null; @endphp
        <tr><td>@if($c)<a href="/clientes/{{ $c->id }}">{{ $c->displayName() }}</a>@else<span class="muted">Cliente {{ $t['codcli'] }}</span>@endif</td><td class="num">{{ $fmt($t['qty']) }}</td><td class="num">{{ eur($t['total']) }}</td><td class="small">{{ $t['last']?->format('d/m/Y') }}</td></tr>
      @endforeach
    </tbody></table>
    @endif
  </div>
</div>

<div class="grid g2 mt">
  <div class="card">
    <h2>Últimas ventas <span class="muted small">(albaranes)</span></h2>
    <div class="scroll"><table class="tbl"><thead><tr><th>Fecha</th><th>Albarán</th><th>Cliente</th><th>Máquina</th><th class="num">Uds.</th><th class="num">Precio</th></tr></thead><tbody>
      @forelse($sales->take(25) as $l)@php $c = $customers[$l->doc->codcli] ?? null; @endphp
        <tr><td class="small">{{ $l->doc->date?->format('d/m/Y') }}</td><td class="small">{{ $l->doc->number() }}</td><td>@if($c)<a href="/clientes/{{ $c->id }}">{{ \Illuminate\Support\Str::limit($c->displayName(), 28) }}</a>@else{{ $l->doc->customer_name }}@endif</td><td class="small">{{ $l->doc->ref }}</td><td class="num">{{ $fmt($l->qty) }}</td><td class="num">{{ eur($l->price) }}@if($l->discount > 0)<span class="small muted"> −{{ $fmt($l->discount) }}%</span>@endif</td></tr>
      @empty<tr><td colspan="6" class="muted">Sin ventas.</td></tr>@endforelse
    </tbody></table></div>
  </div>
  @if($canSales)
  <div class="card">
    <h2>Compras <span class="muted small">(entradas de proveedor)</span></h2>
    <div class="scroll"><table class="tbl"><thead><tr><th>Fecha</th><th>Proveedor</th><th>Su albarán</th><th class="num">Uds.</th><th class="num">Coste</th></tr></thead><tbody>
      @forelse($purchases->take(25) as $l)@php $s = $suppliers[$l->doc->codpro] ?? null; @endphp
        <tr><td class="small">{{ $l->doc->date?->format('d/m/Y') }}</td><td>@if($s)<a href="/proveedores-factusol/{{ $s->id }}">{{ \Illuminate\Support\Str::limit($s->displayName(), 30) }}</a>@else{{ $l->doc->customer_name }}@endif</td><td class="small muted">{{ $l->doc->ext_ref }}</td><td class="num">{{ $fmt($l->qty) }}</td><td class="num">{{ eur($l->price) }}@if($l->discount > 0)<span class="small muted"> −{{ $fmt($l->discount) }}%</span>@endif</td></tr>
      @empty<tr><td colspan="5" class="muted">Sin compras en los ejercicios sincronizados.</td></tr>@endforelse
    </tbody></table></div>
  </div>
  @endif
</div>

@if($alsoIn->isNotEmpty())
<div class="card mt">
  <h2>Apuntado en flotas</h2>
  <p class="small muted" style="margin:0 0 8px">Máquinas de clientes que llevan esta referencia en sus piezas o filtros.</p>
  <div class="scroll"><table class="tbl"><tbody>
    @foreach($alsoIn as $m)<tr><td>{!! \App\Services\Icons::brand($m->brand, 'xs') !!} <strong>{{ $m->model }}</strong> <span class="small muted">{{ $m->type }}</span></td><td class="small"><code class="art">{{ $m->serial }}</code></td><td><a href="/clientes/{{ $m->customer_id }}">{{ $m->customer?->displayName() }}</a></td></tr>@endforeach
  </tbody></table></div>
</div>
@endif
@php $cleanNotes = $a?->notes ? trim(preg_replace('/(\r?\n\s*){2,}/', "\n", $a->notes)) : ''; @endphp
@if($cleanNotes !== '')<div class="card mt"><h2>Observaciones de FactuSOL</h2><p style="margin:0;white-space:pre-line">{{ $cleanNotes }}</p></div>@endif
@include('articles._addq_assets')
@endsection
