@extends('layout')
@section('title', 'Mantenimientos')
@section('content')
<div class="head"><div><h1>Mantenimientos próximos</h1><div class="muted small">Según las horas y el intervalo que los clientes apuntan en su portal ({{ $withHours }} máquinas con cuentahoras). Son ventas de filtros a punto de caer: adelántate.</div></div></div>
<div class="card">
  <h2>Por horas</h2>
  @forelse($machines as $m)
    <div class="pd" style="align-items:center">
      <div><a href="/clientes/{{ $m->customer_id }}/maquinas/{{ $m->id }}"><b>{{ trim(($m->brand ?? '').' '.$m->model) }}</b></a> <span class="muted">· <a href="/clientes/{{ $m->customer_id }}">{{ $m->customer?->displayName() }}</a></span><div class="small muted">{{ number_format($m->hours, 0, ',', '.') }} h · último mantenimiento {{ $m->last_service_hours !== null ? number_format($m->last_service_hours, 0, ',', '.').' h' : '—' }} · cada {{ $m->service_interval }} h @if($m->location) · 📍 {{ $m->location }}@endif</div></div>
      <div class="num"><span class="badge {{ $m->ss['state'] === 'vencido' ? 'bad' : 'warn' }}">{{ $m->ss['state'] === 'vencido' ? 'vencido hace '.(-$m->ss['left']).' h' : 'faltan '.$m->ss['left'].' h' }}</span><br><a class="small" href="/clientes/{{ $m->customer_id }}#flota">Presupuesto de filtros →</a></div>
    </div>
  @empty<p class="muted">Ninguna máquina con mantenimiento próximo (o los clientes aún no han apuntado horas).</p>@endforelse
</div>
@if($dates->isNotEmpty())
<div class="card mt"><h2>Seguros y revisiones que vencen</h2>
  @foreach($dates as $m)@foreach($m->da as $a)<div class="pd"><div><a href="/clientes/{{ $m->customer_id }}/maquinas/{{ $m->id }}"><b>{{ trim(($m->brand ?? '').' '.$m->model) }}</b></a> <span class="muted">· {{ $m->customer?->displayName() }}</span></div><div class="num"><span class="badge {{ $a['state'] === 'vencido' ? 'bad' : 'warn' }}">{{ $a['label'] }} {{ $a['days'] < 0 ? 'vencido' : 'vence en '.$a['days'].' d' }}</span></div></div>@endforeach @endforeach
</div>
@endif
@endsection
