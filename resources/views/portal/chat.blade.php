@extends('portal.layout')
@section('title', 'Mensajes')
@section('content')
<div class="head" style="margin-bottom:8px"><div><h1 style="margin:0">Mensajes con Parsur</h1><div class="muted small">Te respondemos aquí en horario de tienda (8–14 y 16–18).</div></div>
<div class="row" style="gap:6px"><a class="btn sm" style="background:#e9f8ee;color:#1f7a3e;border:1px solid #c6ecd3" href="https://wa.me/34624172538?text={{ rawurlencode('Hola, soy '.$c->displayName().' (cliente '.$c->codcli.'). ') }}" target="_blank" rel="noopener">💬 WhatsApp</a><a class="btn sm ghost" href="tel:957287109">📞 Llamar</a></div></div>
<div class="card chat" id="chat">
  <div class="chat-l" id="chat-l">
    @forelse($messages as $m)
      <div class="msg {{ $m->from === 'cliente' ? 'me' : 'them' }}">
        <div class="msg-b">{{ $m->text }}@if($m->photo)<a class="msg-ph" href="/portal/mensajes/{{ $m->id }}/foto" target="_blank">📷 Ver foto</a>@endif</div>
        <div class="msg-t">{{ $m->from === 'cliente' ? 'Tú' : 'Parsur' }} · {{ $m->created_at->format('d/m H:i') }}</div>
      </div>
    @empty
      <p class="muted" style="text-align:center;padding:20px 0">Aún no hay mensajes. Escríbenos lo que necesites 👇</p>
    @endforelse
  </div>
  <form method="post" action="/portal/mensaje" enctype="multipart/form-data" class="chat-f">@csrf
    <input type="hidden" name="from" value="mensajes">
    <textarea name="text" rows="2" placeholder="Escribe aquí…"></textarea>
    <div class="chat-a">
      <label class="chat-ph btn ghost sm" title="Adjuntar foto">📷 Foto<input type="file" name="photo" accept="image/*,.pdf" capture="environment"><span id="chat-phn"></span></label>
      <button class="btn y" type="submit">Enviar</button>
    </div>
  </form>
</div>
<script>
(function(){
  const l=document.getElementById('chat-l'); l.scrollTop=l.scrollHeight;
  const f=document.querySelector('.chat-ph input'); f.addEventListener('change',()=>{document.getElementById('chat-phn').textContent=f.files[0]?'1 foto':'';});
  const last={{ $messages->last()?->id ?? 0 }};
  setInterval(async()=>{ try{ const r=await fetch('/portal/mensajes/nuevos?desde='+last); const j=await r.json(); if(j.nuevos>0) location.reload(); }catch(e){} }, 20000);
})();
</script>
@endsection
