@extends('layout')
@section('title', 'Cobros pendientes')
@section('content')
<div class="head">
  <div><h1>Cobros pendientes</h1><div class="muted small">Facturas de FactuSOL sin cobrar. El vencimiento se calcula con la forma de pago de cada cliente (contado = al día; transferencia 30 días = fecha + 30…).</div></div>
</div>
@include('fs_docs._tabs')
<div class="grid g4" style="margin-bottom:14px">
  <div class="card kpi"><div class="l">Pendiente total</div><div class="v">{{ eur($stats['total']) }}</div><div class="s">{{ $stats['n'] }} facturas</div></div>
  <div class="card kpi"><div class="l">Vencido</div><div class="v" style="color:var(--bad)">{{ eur($stats['overdue']) }}</div><div class="s">{{ $stats['nOverdue'] }} facturas pasadas de fecha</div></div>
  <div class="card kpi"><div class="l">Más de 30 días de retraso</div><div class="v">{{ eur($stats['over30']) }}</div><div class="s">prioridad de reclamación</div></div>
  <div class="card kpi"><div class="l">Clientes con deuda</div><div class="v">{{ $byCustomer->count() }}</div><div class="s">ordenados por retraso</div></div>
</div>
<div class="tabs">
  <a href="/cobros" class="{{ $only === 'todas' ? 'on' : '' }}">Todas</a>
  <a href="/cobros?ver=vencidas" class="{{ $only === 'vencidas' ? 'on' : '' }}">Solo vencidas</a>
</div>
<div class="card">
  @if($byCustomer->isEmpty())<p class="muted" style="margin:0">Nada pendiente de cobro. 🎉</p>
  @else
  <div class="docs">
    @foreach($byCustomer as $g)
      @php $c = $g->customer; $tel = $c ? preg_replace('/\D+/', '', (string) $c->bestPhone()) : ''; @endphp
      <details class="doc"{{ $loop->index < 3 ? ' open' : '' }}>
        <summary>
          <span class="doc-ic av">{{ mb_substr($c ? $c->displayName() : $g->name, 0, 1) }}</span>
          <span class="doc-main">
            <span class="doc-n">@if($c){{ $c->displayName() }}@else{{ $g->name }}@endif</span>
            <span class="doc-m">{{ $g->n }} {{ $g->n === 1 ? 'factura' : 'facturas' }}@if($c?->paymentTerms()) · {{ $c->paymentTerms() }}@endif @if($c?->city) · {{ $c->city }}@endif</span>
          </span>
          <span class="doc-r">
            <span class="doc-t">{{ eur($g->total) }}</span>
            @if($g->maxOverdue > 0)<span class="badge bad">{{ $g->maxOverdue }} días de retraso</span>@else<span class="badge ok">en plazo</span>@endif
          </span>
        </summary>
        <div style="margin:0 0 10px 26px">
          <table class="tbl"><thead><tr><th>Factura</th><th>Fecha</th><th>Vence</th><th>Retraso</th><th class="num">Importe</th></tr></thead><tbody>
            @foreach($g->rows as $r)
              <tr><td>{{ $r->doc->number() }}@if($r->doc->ref) <span class="small muted">· {{ $r->doc->ref }}</span>@endif</td><td class="small">{{ $r->doc->date?->format('d/m/Y') }}</td><td class="small">{{ $r->due?->format('d/m/Y') }}@if($r->terms) <span class="muted">({{ $r->terms }} d)</span>@endif</td><td>@if($r->overdue > 0)<span class="bad">{{ $r->overdue }} días</span>@else<span class="muted small">en plazo</span>@endif</td><td class="num">{{ eur($r->doc->total) }}</td></tr>
            @endforeach
          </tbody></table>
          @if($c)
          <div class="row" style="margin-top:8px">
            <a class="btn ghost sm" href="/clientes/{{ $c->id }}" style="flex:0 0 auto">Ficha</a>
            @if($tel)<a class="btn sm wa" style="flex:0 0 auto" target="_blank" rel="noopener" href="https://wa.me/{{ str_starts_with($tel, '34') ? $tel : '34'.$tel }}?text={{ rawurlencode('Hola, te escribimos de Recambios Parsur. Tenemos pendiente de cobro '.($g->n === 1 ? 'la factura '.$g->rows->first()->doc->number() : $g->n.' facturas').' por un total de '.eur($g->total).'. ¿Nos confirmas cuándo podremos recibir el pago? Gracias.') }}">WhatsApp recordatorio</a>@endif
            @if($c->email)<a class="btn ghost sm" style="flex:0 0 auto" href="mailto:{{ $c->email }}?subject={{ rawurlencode('Facturas pendientes de cobro · Recambios Parsur') }}">Email</a>@endif
          </div>
          @endif
        </div>
      </details>
    @endforeach
  </div>
  @endif
</div>
@endsection
