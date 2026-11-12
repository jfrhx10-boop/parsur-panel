{{-- Botón flotante "Habla con Parsur" --}}
@php $wa = 'https://wa.me/34624172538?text='.rawurlencode('Hola, soy '.$pc->displayName().' (cliente '.$pc->codcli.'). '); @endphp
<details class="talk" id="talk">
  <summary class="talk-b" title="Habla con Parsur"><i class="ic ic-chat"></i> <span>Habla con Parsur</span>@if(!empty($pcUnread))<b class="nb">{{ $pcUnread }}</b>@endif</summary>
  <div class="talk-p">
    <div class="talk-h">¿Cómo quieres hablar con nosotros?</div>
    <a class="talk-o wa" href="{{ $wa }}" target="_blank" rel="noopener"><i class="ic ic-chat"></i> <b>WhatsApp</b><small>624 172 538 · lo más rápido</small></a>
    <a class="talk-o" href="tel:957287109"><i class="ic ic-phone"></i> <b>Llamar</b><small>957 287 109 · 8–14 y 16–18</small></a>
    <a class="talk-o chat-o" href="/portal/mensajes"><i class="ic ic-edit"></i> <b>Mensajes en la app</b><small>escríbenos aquí y te respondemos{{ !empty($pcUnread) ? ' · '.$pcUnread.' sin leer' : '' }}</small></a>
  </div>
</details>
<script>document.addEventListener('click',e=>{const t=document.getElementById('talk'); if(t&&t.open&&!t.contains(e.target)) t.removeAttribute('open');});</script>
