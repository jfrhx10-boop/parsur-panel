@extends('layout')
@section('title', $supplier->displayName())
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); @endphp
<div class="head">
  <div>
    <a class="small" href="/proveedores-factusol">← Proveedores</a>
    <h1>{{ $supplier->displayName() }}</h1>
    <div class="muted small">{{ $supplier->nif }} · Código FactuSOL {{ $supplier->codpro }}@if($supplier->name !== $supplier->displayName()) · {{ $supplier->name }}@endif</div>
  </div>
</div>
<div class="grid g2">
  <div class="card">
    <h2>Contacto</h2>
    <p style="margin:0">
      @if($supplier->phone)📞 <a href="tel:{{ preg_replace('/\D+/', '', $supplier->phone) }}">{{ $supplier->phone }}</a><br>@endif
      @if($supplier->email)✉️ <a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a><br>@endif
      @if($supplier->web)🌐 <a href="{{ str_starts_with($supplier->web, 'http') ? $supplier->web : 'https://'.$supplier->web }}" target="_blank" rel="noopener">{{ $supplier->web }}</a><br>@endif
      @if($supplier->address || $supplier->city)<span class="muted">{{ $supplier->address }} {{ $supplier->postcode }} {{ $supplier->city }} @if($supplier->province)({{ $supplier->province }})@endif</span>@endif
    </p>
    @if($supplier->notes)<p class="small" style="margin:10px 0 0;white-space:pre-line">{{ $supplier->notes }}</p>@endif
  </div>
  <div class="card">
    <h2>Compras por año <span class="muted small">(sin IVA)</span></h2>
    @if($byYear->isEmpty())<p class="muted" style="margin:0">Sin entradas en los ejercicios sincronizados.</p>
    @else<table class="tbl"><thead><tr><th>Año</th><th class="num">Entradas</th><th class="num">Importe</th></tr></thead><tbody>
      @foreach($byYear as $r)<tr><td><strong>{{ $r->year }}</strong></td><td class="num">{{ $r->n }}</td><td class="num">{{ eur($r->base) }}</td></tr>@endforeach
    </tbody></table>@endif
  </div>
</div>

<div class="card mt">
  <h2>Lo que más le compramos</h2>
  <div class="scroll"><table class="tbl"><thead><tr><th>Referencia</th><th>Descripción</th><th class="num">Uds.</th><th class="num">Importe</th><th class="num">Entradas</th><th>Última</th></tr></thead><tbody>
    @forelse($top as $t)<tr><td><a href="/articulos/{{ rawurlencode($t->art) }}"><code class="art">{{ $t->art }}</code></a></td><td class="small">{{ $t->description }}</td><td class="num">{{ $fmt($t->qty) }}</td><td class="num">{{ eur($t->total) }}</td><td class="num">{{ $t->n }}</td><td class="small">{{ \Carbon\Carbon::parse($t->last)->format('d/m/Y') }}</td></tr>
    @empty<tr><td colspan="6" class="muted">Sin líneas.</td></tr>@endforelse
  </tbody></table></div>
</div>

<div class="card mt">
  <h2>Últimas entradas</h2>
  @if($entries->isEmpty())<p class="muted" style="margin:0">Sin entradas.</p>
  @else<div class="docs">
    @foreach($entries as $d)
      <details class="doc"{{ $loop->first ? ' open' : '' }}>
        <summary><span class="doc-n">Entrada {{ $d->number() }}</span><span class="small muted">{{ $d->date?->format('d/m/Y') }}</span><span class="small">{{ $d->lines_count }} líneas @if($d->ext_ref)· su albarán {{ $d->ext_ref }}@endif</span><span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span><span class="doc-t">{{ eur($d->total) }}</span></summary>
        @include('customers._doclines', ['d' => $d])
      </details>
    @endforeach
  </div>@endif
</div>
@endsection
