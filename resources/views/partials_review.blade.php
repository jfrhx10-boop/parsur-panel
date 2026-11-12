{{-- Recuadro de reseña en Google (QR + botón) para los documentos de cliente --}}
@php $revUrl = 'https://g.page/r/CanNJKcmzf2wEBM/review'; @endphp
<div class="rev" style="display:flex;align-items:center;gap:16px;margin:14px 0 4px;padding:14px 16px;border-radius:10px;background:#2a2a2a;color:#fff;page-break-inside:avoid">
  <div style="flex:0 0 96px;background:#fff;border-radius:8px;padding:5px;line-height:0"><img id="revqr" alt="Código QR para dejar una reseña en Google" width="86" height="86" style="width:86px;height:86px;image-rendering:pixelated"></div>
  <div style="min-width:0">
    <div style="font-size:17px;letter-spacing:3px;color:#f9b21c;line-height:1">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
    <div style="font-weight:800;font-size:14.5px;margin:5px 0 3px">¿Contento con nuestro servicio? Cuéntalo en Google</div>
    <div style="font-size:11.5px;color:#ddd;margin:0 0 8px">Es un minuto y nos ayuda mucho. Escanea el código con la cámara del móvil o pulsa el botón.</div>
    <a href="{{ $revUrl }}" style="display:inline-block;padding:8px 16px;border-radius:8px;background:#f9b21c;color:#111;font-weight:800;font-size:12.5px;text-decoration:none">Dejar mi reseña en Google &rarr;</a>
  </div>
</div>
<script>
(function(){
  function pinta(){try{var q=qrcode(0,'M');q.addData(@json($revUrl));q.make();document.getElementById('revqr').src=q.createDataURL(6,1);}catch(e){document.getElementById('revqr').parentNode.style.display='none';}}
  if(typeof qrcode==='function'){pinta();}else{var s=document.createElement('script');s.src='/js/qrcode.js?v=1';s.onload=pinta;s.onerror=pinta;document.head.appendChild(s);}
})();
</script>
