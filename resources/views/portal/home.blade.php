@extends('portal.layout')
@section('title', 'Mis máquinas')
@section('content')
<h1 style="margin-bottom:2px">Hola</h1>
<div class="small" style="margin-bottom:12px"><b>{{ \Illuminate\Support\Str::of($c->displayName())->title() }}</b> <span class="muted">· {{ $machines->count() }} máquinas · este año llevas <b>{{ eur($byYear[(int) date('Y')]['total'] ?? 0) }}</b> en recambios</span></div>

<div class="big3">
  <a class="bigb y" href="/portal/pedidos/nuevo"><i class="ic ic-cart"></i><span>Pedir piezas</span><small>filtros, cuchillas, lo que sea</small></a>
  <a class="bigb" href="/portal/maquinas/nueva"><i class="ic ic-plus"></i><span>Añadir máquina</span><small>marca, modelo y nº de serie</small></a>
  <a class="bigb {{ $alerts->isNotEmpty() ? 'red' : '' }}" href="/portal/avisos"><i class="ic ic-bell"></i><span>Avisos</span><small>{{ $alerts->sum(fn ($g) => count($g['alerts'])) ?: 'nada pendiente' }}{{ $alerts->isNotEmpty() ? ' por atender' : '' }}</small></a>
</div>

@if($alerts->isNotEmpty())
<div class="card" style="margin-bottom:14px;border-left:5px solid #e0433a">
  <h2>Toca atender</h2>
  @foreach($alerts as $g)@foreach($g['alerts'] as $a)@include('portal._alert', ['a' => $a, 'm' => $g['m'], 'showMachine' => true])@endforeach @endforeach
</div>
@endif

<div class="card">
  <h2>Mis máquinas</h2>
  @forelse($machines as $m)
    @include('portal._machine_card', ['m' => $m])
  @empty
    <p class="muted">Todavía no hay máquinas. Pulsa <b>Añadir máquina</b> arriba.</p>
  @endforelse
</div>

@if($orders->isNotEmpty())
<div class="card mt">
  <h2>Mis últimos pedidos</h2>
  @foreach($orders as $o)
    <a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span>@if($o->machine) · {{ trim(($o->machine->brand ?? '').' '.$o->machine->model) }}@endif</a>
  @endforeach
</div>
@endif
<div class="card mt" id="ofertas">
  <h2>Ofertas por correo</h2>
  @if($c->offers_at)
    <p class="muted" style="margin:0 0 10px">Recibes nuestras ofertas y novedades por correo desde el {{ \Illuminate\Support\Carbon::parse($c->offers_at)->format('d/m/Y') }}.</p>
    <form method="post" action="/portal/ofertas">@csrf<button class="btn ghost sm" type="submit">No quiero recibir ofertas</button></form>
  @else
    <p class="muted" style="margin:0 0 10px">Si quieres, te avisamos por correo de ofertas y novedades de recambios para tus máquinas. Pocos correos y te das de baja cuando quieras.</p>
    <form method="post" action="/portal/ofertas">@csrf<input type="hidden" name="acepto" value="1"><button class="btn y sm" type="submit">Sí, quiero recibir ofertas</button></form>
    <p class="small muted" style="margin:8px 0 0">Responsable: Recambios Parsur. Finalidad: enviarte comunicaciones comerciales. Más información en la <a href="https://recambiosparsur.com/politica-de-privacidad/" target="_blank" rel="noopener">política de privacidad</a>.</p>
  @endif
</div>
@endsection
