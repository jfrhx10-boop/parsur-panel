@extends('layout')
@section('title', 'Mensajes · '.$customer->displayName())
@section('content')
<a class="small" href="/mensajes">← Mensajes</a>
<div class="head" style="margin-top:6px"><div><h1>{{ $customer->displayName() }}</h1><div class="muted small"><a href="/clientes/{{ $customer->id }}">Ver ficha</a>@if($customer->bestPhone()) · <a href="tel:{{ preg_replace('/\D+/', '', $customer->bestPhone()) }}">{{ $customer->bestPhone() }}</a>@endif</div></div></div>
<div class="card chat">
  <div class="chat-l" id="chat-l">
    @forelse($messages as $m)
      <div class="msg {{ $m->from === 'parsur' ? 'me' : 'them' }}">
        <div class="msg-b">{{ $m->text }}@if($m->photo)<a class="msg-ph" href="/mensajes/{{ $customer->id }}/foto/{{ $m->id }}" target="_blank">📷 Ver foto</a>@endif</div>
        <div class="msg-t">{{ $m->from === 'parsur' ? ($m->user?->name ?? 'Parsur') : 'Cliente' }} · {{ $m->created_at->format('d/m/Y H:i') }}@if($m->context && $m->from === 'cliente') · desde {{ $m->context }}@endif @if($m->from === 'parsur') · {{ $m->read_at ? 'leído' : 'sin leer' }}@endif</div>
      </div>
    @empty<p class="muted" style="text-align:center;padding:20px">Sin mensajes.</p>@endforelse
  </div>
  <form method="post" action="/mensajes/{{ $customer->id }}" class="chat-f">@csrf
    <textarea name="text" rows="2" required placeholder="Responder al cliente… (lo verá en su app)"></textarea>
    <div class="chat-a"><span class="small muted">El cliente lo ve al entrar en el portal (la pantalla se le actualiza sola).</span><button class="btn y" type="submit">Enviar</button></div>
  </form>
</div>
<script>const l=document.getElementById('chat-l'); l.scrollTop=l.scrollHeight;</script>
@endsection
