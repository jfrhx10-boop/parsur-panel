@extends('layout')
@section('title', 'Parsur Flota')
@section('content')
<div class="head">
  <div><h1 style="display:flex;align-items:center;gap:12px"><img src="/img/parsur-flota.png" alt="" style="height:34px;background:#2a2a2a;border-radius:8px;padding:5px 9px">Parsur Flota</h1>
    <div class="muted small">Control de flota de los clientes. Desde aquí se abre la flota de cada uno, se copia su enlace privado o se le envía por WhatsApp.</div></div>
  <div class="row" style="gap:6px"><a class="btn ghost sm" href="/portal" target="_blank" rel="noopener">Entrada de clientes</a><a class="btn ghost sm" href="/flota/cruce">Máquinas que faltan</a><a class="btn ghost sm" href="https://recambiosparsur.com/control-de-flota/" target="_blank" rel="noopener">Página en la web</a></div>
</div>

<form method="get" action="/parsur-flota" class="row" style="gap:6px;margin:0 0 14px"><input type="search" name="q" value="{{ $q }}" placeholder="Buscar cliente…" style="flex:1 1 260px;max-width:420px"><button class="btn sm" type="submit">Buscar</button>@if($q !== '')<a class="btn ghost sm" href="/parsur-flota">Quitar filtro</a>@endif</form>

<div class="card">
  <h2>Con acceso activo <span class="muted small">· {{ $active->count() }}</span></h2>
  @forelse($active as $c)
    @php $ph = $c->bestPhone(); $wa = $ph ? preg_replace('/\D+/', '', '34'.ltrim($ph, '+34')) : null; $movil = $wa && preg_match('/^34[67]/', $wa); @endphp
    <div class="pf-r">
      <div class="pf-n"><a href="/clientes/{{ $c->id }}"><b>{{ $c->displayName() }}</b></a>
        <div class="small muted">{{ $c->machines_count }} {{ $c->machines_count === 1 ? 'máquina' : 'máquinas' }} · {{ $c->portal_pin ? 'con PIN' : 'sin PIN' }} · {{ $c->portal_last_at ? 'última entrada '.$c->portal_last_at->format('d/m/Y H:i') : 'aún no ha entrado' }}</div></div>
      <div class="row" style="gap:6px;flex-wrap:wrap;justify-content:flex-end">
        <a class="btn y sm" href="{{ $c->portalUrl() }}" target="_blank" rel="noopener">Abrir su flota</a>
        <button type="button" class="btn ghost sm" data-url="{{ $c->portalUrl() }}" onclick="var b=this;navigator.clipboard.writeText(b.dataset.url).then(function(){b.textContent='Copiado';setTimeout(function(){b.textContent='Copiar enlace'},1800)})">Copiar enlace</button>
        @if($movil)<a class="btn ghost sm" target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hola, este es tu acceso a Parsur Flota, el control de flota de Recambios Parsur: '.$c->portalUrl().' Ahí tienes tus máquinas, el mantenimiento por horas, los avisos y lo que llevas gastado, y puedes pedir piezas desde el móvil.') }}">WhatsApp</a>@endif
        <a class="btn ghost sm" href="/clientes/{{ $c->id }}">Ficha</a>
      </div>
    </div>
  @empty
    <p class="muted">{{ $q !== '' ? 'Ningún cliente con acceso activo coincide con la búsqueda.' : 'Todavía no hay ningún cliente con Parsur Flota activado.' }}</p>
  @endforelse
</div>

<div class="card" style="margin-top:14px">
  <h2>Con máquinas y sin acceso <span class="muted small">· {{ $q !== '' ? $pending->count() : 'los '.$pending->count().' con más máquinas' }}</span></h2>
  @forelse($pending as $c)
    <div class="pf-r">
      <div class="pf-n"><a href="/clientes/{{ $c->id }}"><b>{{ $c->displayName() }}</b></a><div class="small muted">{{ $c->machines_count }} {{ $c->machines_count === 1 ? 'máquina' : 'máquinas' }}@if($c->bestPhone()) · {{ $c->bestPhone() }}@endif</div></div>
      <form method="post" action="/clientes/{{ $c->id }}/portal/activar" style="margin:0">@csrf<button class="btn sm" type="submit">Activar Parsur Flota</button></form>
    </div>
  @empty
    <p class="muted">No hay clientes pendientes{{ $q !== '' ? ' que coincidan con la búsqueda' : '' }}.</p>
  @endforelse
</div>
<style>
.pf-r{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-top:1px solid #f0efeb;flex-wrap:wrap}
.pf-r:first-of-type{border-top:0}
.pf-n{min-width:220px;flex:1 1 260px}
.pf-n a{color:inherit;text-decoration:none}.pf-n a:hover{text-decoration:underline}
</style>
@endsection
