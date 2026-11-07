{{-- Aviso "instala la app" (solo móvil, se puede cerrar) --}}
<div class="inst" id="inst" hidden>
  <div class="inst-t">📱 <b>Ponlo como app</b><span id="inst-txt">y entra con un toque.</span></div>
  <div class="inst-b"><button type="button" class="btn sm y" id="inst-go" hidden>Instalar</button><button type="button" class="linkbtn small" id="inst-x">Ahora no</button></div>
</div>
<script>
(function(){
  const box=document.getElementById('inst'), go=document.getElementById('inst-go'), x=document.getElementById('inst-x'), txt=document.getElementById('inst-txt');
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  let hidden=false; try{ hidden = localStorage.getItem('pt-inst') === '1'; }catch(e){}
  if (standalone || hidden || window.innerWidth > 860) return;
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  let deferred=null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); deferred=e; go.hidden=false; box.hidden=false; });
  if (ios) { txt.textContent = ': pulsa Compartir ⎋ y luego «Añadir a pantalla de inicio».'; box.hidden=false; }
  else { setTimeout(() => { if (!deferred) { txt.textContent = ': en el menú ⋮ del navegador, «Añadir a pantalla de inicio».'; box.hidden=false; } }, 1500); }
  go.addEventListener('click', async () => { if (!deferred) return; deferred.prompt(); await deferred.userChoice; box.hidden=true; try{ localStorage.setItem('pt-inst','1'); }catch(e){} });
  x.addEventListener('click', () => { box.hidden=true; try{ localStorage.setItem('pt-inst','1'); }catch(e){} });
})();
</script>
