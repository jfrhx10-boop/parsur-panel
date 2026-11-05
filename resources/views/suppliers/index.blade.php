@extends('layout')
@section('title', 'Proveedores')
@section('content')
<div class="head"><div><h1>Proveedores</h1><div class="muted small">Histórico de compras y análisis por referencia</div></div></div>
<div class="grid g2">
@forelse($suppliers as $s)
  @php $ys = array_keys($s->byYear); $last = $ys ? max($ys) : null; @endphp
  <a class="card sup" href="/proveedores/{{ $s->id }}">
    <div class="sup-logo"><img src="{{ $s->logo }}" alt="{{ $s->name }}" onerror="var t=document.createElement('span');t.textContent=this.alt;this.replaceWith(t)"></div>
    <div class="sup-body">
      <div class="sup-meta"><span>{{ $s->name }}</span><span class="muted small">Cuenta {{ $s->account }} · {{ $s->nRefs }} referencias</span></div>
      <div class="sup-stats">
        <div><div class="sl">Compras históricas</div><div class="sv">{{ eur($s->total) }}</div></div>
        @if($last)
        <div><div class="sl">{{ $last }}</div><div class="sv">{{ eur($s->byYear[$last]) }}</div></div>
        @if(isset($s->byYear[$last - 1]))<div><div class="sl">{{ $last - 1 }}</div><div class="sv">{{ eur($s->byYear[$last - 1]) }}</div></div>@endif
        @endif
      </div>
      <div class="sup-go">Ver análisis →</div>
    </div>
  </a>
@empty
  <div class="card muted">Aún no hay proveedores cargados.</div>
@endforelse
</div>
@endsection
