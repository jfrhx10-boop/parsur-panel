@php
  // Atajos de fechas para los filtros de las listas. $base: ruta de la lista. Conserva el resto de filtros.
  $h = now('Europe/Madrid'); $keep = collect(request()->query())->except(['desde', 'hasta', 'page', 'anio'])->all();
  $rng = [
    'Hoy' => [$h->toDateString(), $h->toDateString()],
    'Ayer' => [$h->copy()->subDay()->toDateString(), $h->copy()->subDay()->toDateString()],
    'Esta semana' => [$h->copy()->startOfWeek()->toDateString(), $h->toDateString()],
    'Este mes' => [$h->copy()->startOfMonth()->toDateString(), $h->toDateString()],
    'Mes pasado' => [$h->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $h->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    'Este año' => [$h->copy()->startOfYear()->toDateString(), $h->toDateString()],
  ];
@endphp
<div class="small no-print" style="flex:1 1 100%;display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center">
  <span class="muted">Fechas rápidas:</span>
  @foreach($rng as $name => $r)<a href="{{ $base }}?{{ http_build_query($keep + ['desde' => $r[0], 'hasta' => $r[1]]) }}" @if(request('desde') === $r[0] && request('hasta') === $r[1]) style="font-weight:800" @endif>{{ $name }}</a>@endforeach
  @if(request('desde') || request('hasta'))<a href="{{ $base }}{{ $keep ? '?'.http_build_query($keep) : '' }}" class="muted">✕ quitar fechas</a>@endif
</div>
