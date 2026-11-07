{{-- Botón flotante "Habla con Parsur" --}}
@php $wa = 'https://wa.me/34624172538?text='.rawurlencode('Hola, soy '.$pc->displayName().' (cliente '.$pc->codcli.'). '); @endphp
<details class="talk" id="talk">
  <summary class="talk-b" title="Habla con Parsur">💬 <span>Habla con Parsur</span></summary>
  <div class="talk-p">
    <div class="talk-h">¿Cómo quieres hablar con nosotros?</div>
    <a class="talk-o wa" href="{{ $wa }}" target="_blank" rel="noopener">💬 <b>WhatsApp</b><small>624 172 538 · lo más rápido</small></a>
    <a class="talk-o" href="tel:957287109">📞 <b>Llamar</b><small>957 287 109 · 8–14 y 16–18</small></a>
    <form method="post" action="/portal/mensaje" class="talk-f">@csrf
      <input type="hidden" name="from" value="{{ request()->path() }}">
      <label>✍️ <b>Escríbenos aquí</b></label>
      <textarea name="text" rows="3" required placeholder="Cuéntanos qué necesitas…"></textarea>
      <input type="text" name="phone" inputmode="tel" value="{{ $pc->bestPhone() }}" placeholder="Tu teléfono">
      <button class="btn y" type="submit" style="width:100%">Enviar</button>
    </form>
  </div>
</details>
<script>document.addEventListener('click',e=>{const t=document.getElementById('talk'); if(t&&t.open&&!t.contains(e.target)) t.removeAttribute('open');});</script>
