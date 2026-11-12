@extends('portal.layout')
@section('title', 'Avisos')
@section('content')
<h1>Avisos</h1>
<p class="muted small">Mantenimientos, seguros, revisiones y los avisos que te pongas tú. <i class="dot dot-vencido"></i> vencido · <i class="dot dot-proximo"></i> pronto · <i class="dot dot-ok"></i> más adelante.</p>
<div class="card">
  <h2>Toca atender</h2>
  @forelse($groups as $g)
    <div class="av-m">{!! \App\Services\Icons::machine($g['m']->type, $g['m']->model) !!} <b>{{ trim(($g['m']->brand ?? '').' '.$g['m']->model) }}</b>@if($g['m']->whereLabel()) <span class="muted small">· {{ $g['m']->whereLabel() }}</span>@endif</div>
    @foreach($g['alerts'] as $a)@include('portal._alert', ['a' => $a, 'm' => $g['m']])@endforeach
  @empty<p class="muted">Nada pendiente.</p>@endforelse
</div>
@if($upcoming->isNotEmpty())
<div class="card mt">
  <h2>Más adelante</h2>
  @foreach($upcoming as $g)
    <div class="av-m"><b>{{ trim(($g['m']->brand ?? '').' '.$g['m']->model) }}</b></div>
    @foreach($g['alerts'] as $a)@include('portal._alert', ['a' => $a, 'm' => $g['m']])@endforeach
  @endforeach
</div>
@endif
<p class="small muted" style="margin-top:12px">Para ponerte un aviso, entra en la máquina y pulsa <b>Ponerme un aviso</b>.</p>
@endsection
