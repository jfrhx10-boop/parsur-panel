@extends('layout')
@section('title', trim(($machine->brand ?? '').' '.$machine->model))
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); $sales = auth()->user()->canSeeSales(); $groups = ['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios']; @endphp
<div class="head">
  <div>
    <a class="small" href="/clientes/{{ $customer->id }}">← {{ $customer->displayName() }}</a>
    <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span class="fl-ico">{!! \App\Services\Icons::machine($machine->type, $machine->model) !!}</span>@if($machine->brand){!! \App\Services\Icons::brand($machine->brand, 'md') !!}@endif {{ $machine->model }}@if($machine->review) <span class="badge warn">por revisar</span>@endif @if($sz = $machine->size()) <span class="sz sz-{{ strtolower($sz) }}">{{ $sz }}</span>@endif</h1>
    <div class="muted small">{{ $machine->type ?: 'Máquina' }}@if($machine->tonsEstimate() !== null) · {{ $machine->tons === null ? '~' : '' }}{{ \App\Services\MachineSize::fmt($machine->tonsEstimate()) }} t @endif@if($machine->year) · {{ $machine->year }}@endif @if($machine->serial) · Nº serie <code class="art">{{ $machine->serial }}</code>@endif @if($machine->notes) · {{ $machine->notes }}@endif</div>
  </div>
  <form method="post" action="/presupuestos" id="sel-m">@csrf<input type="hidden" name="customer_id" value="{{ $customer->id }}"><input type="hidden" name="machine_id" value="{{ $machine->id }}"><button class="btn y" type="submit">Presupuesto con lo marcado</button></form>
</div>

<div class="grid g4">
  <div class="card kpi"><div class="l">Albaranes</div><div class="v">{{ $byType->get('albaran', collect())->count() }}</div><div class="s">@if($sales){{ eur($byType->get('albaran', collect())->sum('base')) }} sin IVA @else último {{ $byType->get('albaran', collect())->first()?->date?->format('d/m/Y') }}@endif</div></div>
  <div class="card kpi"><div class="l">Pedidos</div><div class="v">{{ $byType->get('pedido', collect())->count() }}</div><div class="s">{{ $byType->get('pedido', collect())->whereNotIn('status', [2, 4])->count() }} pendientes</div></div>
  <div class="card kpi"><div class="l">Presupuestos</div><div class="v">{{ $byType->get('presupuesto', collect())->count() + $quotes->count() }}</div><div class="s">{{ $byType->get('presupuesto', collect())->where('status', 0)->count() + $quotes->whereIn('status', ['borrador', 'enviado'])->count() }} sin respuesta</div></div>
  <div class="card kpi"><div class="l">Referencias distintas</div><div class="v">{{ $sold->count() }}</div><div class="s">vendidas a esta máquina</div></div>
</div>

<div class="card mt">
  <div class="head" style="margin-bottom:8px;align-items:center"><h2 style="margin:0">Piezas y filtros apuntados</h2><span class="small muted">Marca las que quieras y pulsa «Presupuesto con lo marcado»</span></div>
  @if(!$items)<p class="muted" style="margin:0">Sin piezas apuntadas. Edítalas desde la ficha del cliente.</p>@endif
  <div class="mc-b" style="border:0;padding:0;background:none">
  @foreach($groups as $gk => $gname)
    @php $gi = array_values(array_filter($items, fn ($x) => ($x['kind'] ?? 'recambio') === $gk)); @endphp
    @if($gi)
    <div class="pg"><span class="pg-l">{{ $gname }}</span><div class="pg-items">
      @foreach($gi as $it)@php $k = $known[$it['ref']] ?? null; @endphp
        <label class="pt {{ $gk }} sel"><input type="checkbox" name="refs[]" value="{{ $it['label'] }}|{{ $it['ref'] }}" form="sel-m">{!! \App\Services\Icons::part($it['label'], $it['filter']) !!}<em>{{ $it['label'] }}</em>@if($it['ref']) @if($k)<a href="/proveedores/{{ $k->supplier_id }}/referencias/{{ $k->id }}" onclick="event.stopPropagation()">{{ $it['ref'] }}</a>@else<b>{{ $it['ref'] }}</b>@endif @endif</label>
      @endforeach
    </div></div>
    @endif
  @endforeach
  </div>
</div>

<div class="grid g2 mt">
  <div class="card">
    <h2>Lo que se le ha vendido <span class="muted small">(por referencia)</span></h2>
    @if($sold->isEmpty())<p class="muted" style="margin:0">Sin albaranes con esta máquina en la referencia.</p>
    @else
    <div class="scroll"><table class="tbl"><thead><tr><th>Artículo</th><th class="num">Veces</th><th class="num">Uds.</th><th>Última</th><th class="num">Último precio</th><th class="num">Stock</th></tr></thead><tbody>
      @foreach($sold as $r)@php $a = $articles[$r->art] ?? null; @endphp
        <tr><td><a href="/articulos/{{ rawurlencode($r->art) }}"><code class="art">{{ $r->art }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($r->description, 40) }}</div></td><td class="num">{{ $r->n }}</td><td class="num">{{ $fmt($r->qty) }}</td><td class="small">{{ $r->last?->format('d/m/Y') }}</td><td class="num">{{ eur($r->lastPrice) }}</td><td class="num">@if($a)@if($a->stock > 0)<strong>{{ $fmt($a->stock) }}</strong>@else<span class="badge warn">0</span>@endif @else—@endif</td></tr>
      @endforeach
    </tbody></table></div>
    @endif
  </div>
  <div class="card">
    <h2>Ventas por año</h2>
    @if($byYear->isEmpty())<p class="muted" style="margin:0">Sin datos.</p>
    @else<table class="tbl"><thead><tr><th>Año</th><th class="num">Albaranes</th>@if($sales)<th class="num">Importe</th>@endif</tr></thead><tbody>
      @foreach($byYear as $y => $r)<tr><td><strong>{{ $y }}</strong></td><td class="num">{{ $r['n'] }}</td>@if($sales)<td class="num">{{ eur($r['total']) }}</td>@endif</tr>@endforeach
    </tbody></table>@endif
    @if($quotes->isNotEmpty())
      <h3 class="small muted" style="margin:14px 0 6px;text-transform:uppercase;letter-spacing:.4px">Presupuestos del panel</h3>
      <table class="tbl"><tbody>@foreach($quotes as $q)<tr><td><a href="/presupuestos/{{ $q->id }}"><strong>{{ $q->number }}</strong></a></td><td class="small">{{ $q->date->format('d/m/Y') }}</td><td><span class="badge {{ $q->statusClass() }}">{{ $q->statusLabel() }}</span></td><td class="num">{{ eur($q->total) }}</td></tr>@endforeach</tbody></table>
    @endif
  </div>
</div>

<div class="card mt">
  <h2>Documentos de esta máquina <span class="muted small">({{ $docs->count() }})</span></h2>
  @if($docs->isEmpty())<p class="muted" style="margin:0">Ninguno. Para que se crucen, la referencia del documento en FactuSOL debe llevar el modelo (por ejemplo «{{ $machine->model }}»).</p>
  @else
  <div class="docs">
    @foreach($docs->take(40) as $d)
      @include('fs_docs._row', ['d' => $d, 'party' => null, 'partyUrl' => null, 'hideParty' => true, 'open' => $loop->first])
    @endforeach
  </div>
  @endif
</div>
@endsection
