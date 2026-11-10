@extends('layout')
@section('title', 'Buscar')
@section('content')
@php use App\Services\Icons; $sales = auth()->user()->canSeeSales(); @endphp
<div class="head">
  <div><h1>Buscar</h1><div class="muted small">Clientes, máquinas, nº de serie, albaranes, artículos (también por referencia equivalente) y referencias de proveedor</div></div>
</div>
<form class="card row" method="get" action="/buscar" style="margin-bottom:14px">
  <div style="flex:3 1 260px"><label for="q">Qué buscas</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="«EC360B», «SO115», «5TJ1805», «2026/1764», «Áridos»…" autofocus></div>
  <button class="btn" type="submit">Buscar</button>
</form>

@if(mb_strlen($q) >= 2)
@php $total = $customers->count() + $machines->count() + $docs->count() + $articles->count() + $refs->count() + $catalog->count(); @endphp
@if($total === 0)<div class="card"><p class="muted" style="margin:0">Nada con «{{ $q }}».</p></div>@endif

@if($machines->isNotEmpty())
<div class="card mt">
  <h2>Máquinas <span class="muted small">({{ $machines->count() }})</span></h2>
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Máquina</th><th>Nº serie</th><th>Cliente</th><th class="hide-m">Piezas apuntadas</th></tr></thead>
    <tbody>
    @foreach($machines as $m)
      <tr>
        <td><span style="display:flex;align-items:center;gap:8px">{!! Icons::brand($m->brand, 'sm') !!}<strong>{{ $m->model }}</strong>@if($m->type)<span class="small muted">{{ $m->type }}</span>@endif</span></td>
        <td><code class="art">{{ $m->serial }}</code></td>
        <td><a href="/clientes/{{ $m->customer_id }}">{{ $m->customer?->displayName() }}</a><div class="small muted">{{ $m->customer?->city }}</div></td>
        <td class="small muted hide-m">{{ \Illuminate\Support\Str::limit($m->parts, 90) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endif

@if($customers->isNotEmpty())
<div class="card mt">
  <h2>Clientes <span class="muted small">({{ $customers->count() }})</span></h2>
  <div class="scroll"><table class="tbl">
    <tbody>
    @foreach($customers as $c)
      <tr><td><a href="/clientes/{{ $c->id }}">{{ $c->displayName() }}</a>@if($c->codcli) <span class="badge" title="Número de cliente en FactuSOL">nº {{ $c->codcli }}</span>@endif <span class="small muted">{{ $c->nif }}</span></td><td class="small">{{ $c->bestPhone() }}</td><td class="small muted">{{ $c->city }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endif

@if($docs->isNotEmpty())
<div class="card mt">
  <h2>Documentos de FactuSOL <span class="muted small">({{ $docs->count() }})</span></h2>
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Documento</th><th>Fecha</th><th>Cliente</th><th>Referencia</th><th>Estado</th><th class="num">Total</th></tr></thead>
    <tbody>
    @foreach($docs as $d)
      @php $cust = \App\Models\Customer::where('codcli', $d->codcli)->first(); @endphp
      <tr>
        <td><strong>{{ $d->typeLabel() }} {{ $d->number() }}</strong></td>
        <td class="small">{{ $d->date?->format('d/m/Y') }}</td>
        <td>@if($cust)<a href="/clientes/{{ $cust->id }}">{{ $cust->displayName() }}</a>@else{{ $d->customer_name }}@endif</td>
        <td class="small">{{ $d->ref }}</td>
        <td><span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span></td>
        <td class="num">{{ eur($d->total) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endif

@if($catalog->isNotEmpty())
<div class="card mt">
  <h2>Artículos <span class="muted small">({{ $catalog->count() }})</span></h2>
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Referencia</th><th>Descripción</th><th class="num">Stock</th>@if($sales)<th class="num">Coste</th>@endif<th class="num">PVP</th><th class="hide-m">Ubic.</th></tr></thead>
    <tbody>
    @foreach($catalog as $a)
      <tr><td><a href="/articulos/{{ rawurlencode($a->codart) }}"><code class="art">{{ $a->codart }}</code></a>@if(isset($xrefHits[$a->codart]))<div class="small muted">compatible con «{{ $q }}»</div>@endif</td><td class="small">{{ $a->description }}</td><td class="num">@if($a->stock > 0)<strong>{{ rtrim(rtrim(number_format($a->stock, 2, ',', '.'), '0'), ',') }}</strong>@else<span class="muted">0</span>@endif</td>@if($sales)<td class="num">{{ eur($a->cost) }}</td>@endif<td class="num">{{ eur($a->price) }}</td><td class="small muted hide-m">{{ $a->location }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endif

@if($articles->isNotEmpty())
<div class="card mt">
  <h2>Artículos vendidos <span class="muted small">({{ $articles->count() }})</span></h2>
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Referencia</th><th>Descripción</th><th class="num">Veces</th><th class="num">Unidades</th><th class="num">Último precio</th></tr></thead>
    <tbody>
    @foreach($articles as $a)
      <tr><td><code class="art">{{ $a->art }}</code></td><td class="small">{{ $a->description }}</td><td class="num">{{ $a->n }}</td><td class="num">{{ rtrim(rtrim(number_format($a->qty, 2, ',', '.'), '0'), ',') }}</td><td class="num">{{ eur($a->price) }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endif

@if($refs->isNotEmpty())
<div class="card mt">
  <h2>Referencias de proveedor</h2>
  <div class="scroll"><table class="tbl"><tbody>
    @foreach($refs as $r)
      <tr><td><a href="/proveedores/{{ $r->supplier_id }}/referencias/{{ $r->id }}"><code class="art">{{ $r->ref }}</code></a></td><td class="small">{{ $r->label }}</td><td class="small muted">{{ $r->supplier?->name }}</td></tr>
    @endforeach
  </tbody></table></div>
</div>
@endif
@endif
@endsection
