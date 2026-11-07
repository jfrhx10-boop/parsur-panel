@extends('portal.layout')
@section('title', trim(($machine->brand ?? '').' '.$machine->model))
@section('content')
<a class="small" href="/portal/inicio">← Mis máquinas</a>
<div class="head" style="margin-top:6px">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">@if($machine->brand){!! \App\Services\Icons::brand($machine->brand, 'md') !!}@endif {{ $machine->model }}@if($sz = $machine->size()) <span class="sz sz-{{ strtolower($sz) }}">{{ $sz }}</span>@endif</h1>
    <div class="muted small">{{ $machine->type ?: 'Máquina' }}@if($machine->serial) · nº serie <code>{{ $machine->serial }}</code>@endif @if($machine->year) · año {{ $machine->year }}@endif @if($machine->operator) · maquinista {{ $machine->operator }}@if($machine->operator_phone) (<a href="tel:{{ preg_replace('/\D+/', '', $machine->operator_phone) }}">{{ $machine->operator_phone }}</a>)@endif @endif</div>
  </div>
  <div class="row" style="gap:8px"><a class="btn y" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}">Pedir para esta máquina</a><a class="btn ghost" href="/portal/maquinas/{{ $machine->id }}/editar">✎ Editar</a></div>
</div>

<div class="pg">
  <div class="pg-l">
    <div class="card">
      <h2>Lo que lleva esta máquina</h2>
      @if($items)
        @foreach(['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios'] as $k => $t)
          @php $g = array_filter($items, fn ($i) => $i['kind'] === $k); @endphp
          @if($g)<div class="pi-g"><div class="pi-t">{{ $t }}</div><div class="pi-l">@foreach($g as $i)<span class="pi">{!! \App\Services\Icons::part($i['label'], $k === 'filtro') !!}{{ $i['label'] }}@if($i['qty']) <small>×{{ $i['qty'] }}</small>@endif</span>@endforeach</div></div>@endif
        @endforeach
        <p class="small muted" style="margin:10px 0 0">Tenemos apuntadas las referencias exactas: pide marcando lo que necesites y nosotros lo preparamos.</p>
      @else
        <p class="muted">Aún no tenemos apuntado lo que lleva. Haz un pedido describiendo lo que necesitas y lo registramos.</p>
      @endif
      @if($machine->portal_notes)<div class="small" style="margin-top:10px;white-space:pre-line"><b>Notas:</b> {{ $machine->portal_notes }}</div>@endif
    </div>
    @if($orders->isNotEmpty())
    <div class="card mt"><h2>Pedidos de esta máquina</h2>
      @foreach($orders as $o)<a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span> · {{ $o->lines->count() }} líneas</a>@endforeach
    </div>
    @endif
  </div>
  <div class="pg-r">
    <div class="card">
      <h2>Gasto en esta máquina</h2>
      @if($byYear)
        <table class="tbl"><thead><tr><th>Año</th><th class="num">Compras</th><th class="num">Importe</th></tr></thead><tbody>
        @foreach(array_reverse($byYear, true) as $y => $r)<tr><td>{{ $y }}</td><td class="num">{{ $r['n'] }}</td><td class="num"><b>{{ eur($r['total']) }}</b></td></tr>@endforeach
        </tbody></table>
        <p class="small muted" style="margin:6px 0 0">Importes sin IVA.</p>
      @else<p class="muted">Sin compras asociadas a esta máquina.</p>@endif
    </div>
    @if($docs->isNotEmpty())
    <div class="card mt">
      <h2>Compras</h2>
      @foreach($docs->where('type', 'albaran')->take(15) as $d)
        <div class="pd"><div><a href="/portal/documentos/albaran/{{ $d->year }}/{{ $d->code }}"><b>{{ $d->date?->format('d/m/Y') }}</b></a> <span class="muted small">albarán {{ $d->number() }}</span><div class="small muted">{{ $d->lines->take(3)->map(fn ($l) => \App\Services\Portal::clean($l->description))->filter()->implode(' · ') }}{{ $d->lines->count() > 3 ? ' …' : '' }}</div></div><div class="num"><b>{{ eur($d->base) }}</b><br><a class="small" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}&repetir={{ $d->year }}/{{ $d->code }}">Repetir</a></div></div>
      @endforeach
    </div>
    @endif
  </div>
</div>
@endsection
