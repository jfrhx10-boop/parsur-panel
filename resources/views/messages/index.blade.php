@extends('layout')
@section('title', 'Mensajes')
@section('content')
<div class="head"><div><h1>Mensajes de clientes</h1><div class="muted small">Chat del portal: lo que escriben los clientes y vuestras respuestas.</div></div></div>
<div class="card">
  @forelse($threads as $t)
    <a class="th {{ $t['unread'] ? 'unread' : '' }}" href="/mensajes/{{ $t['m']->customer_id }}">
      <div><b>{{ $t['m']->customer?->displayName() }}</b>@if($t['unread']) <span class="badge bad">{{ $t['unread'] }} sin leer</span>@endif<div class="small muted">{{ $t['m']->from === 'cliente' ? 'Cliente' : 'Parsur' }}: {{ \Illuminate\Support\Str::limit($t['m']->text, 90) }}</div></div>
      <div class="small muted">{{ $t['m']->created_at->format('d/m H:i') }}</div>
    </a>
  @empty<p class="muted">Todavía no hay conversaciones.</p>@endforelse
</div>
@endsection
