{{-- Aviso "instala la app" (solo móvil, se puede cerrar) y botón fijo «Añadir como app» al final de cada página --}}
<div class="inst" id="inst" hidden>
  <div class="inst-t"><i class="ic ic-mobile"></i> <b>Ponlo como app</b><span id="inst-txt">y entra con un toque.</span></div>
  <div class="inst-b"><button type="button" class="btn sm y" id="inst-go" hidden>Instalar</button><button type="button" class="linkbtn small" id="inst-x">Ahora no</button></div>
</div>
<div id="inst-fix" style="text-align:center;margin:22px 0 4px">
  <button type="button" class="btn ghost" id="inst-btn"><i class="ic ic-mobile"></i> Añadir como app</button>
  <div class="small muted" id="inst-how" hidden style="margin:8px auto 0;max-width:440px"></div>
</div>
<script>
(function(){
  const box=document.getElementById('inst'), go=document.getElementById('inst-go'), x=document.getElementById('inst-x'), txt=document.getElementById('inst-txt');
  const fix=document.getElementById('inst-fix'), btn=document.getElementById('inst-btn'), how=document.getElementById('inst-how');
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (standalone) { fix.hidden = true; return; } // ya está abierta como app
  const ua = navigator.userAgent, ios = /iphone|ipad|ipod/i.test(ua), android = /android/i.test(ua);
  let hidden=false; try{ hidden = localStorage.getItem('pt-inst') === '1'; }catch(e){}
  const banner = !hidden && window.innerWidth <= 860;
  let deferred=null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); deferred=e; if (banner) { go.hidden=false; box.hidden=false; } });
  window.addEventListener('appinstalled', () => { box.hidden=true; fix.hidden=true; });
  if (banner) {
    if (ios) { txt.textContent = ': pulsa Compartir ⎋ y luego «Añadir a pantalla de inicio».'; box.hidden=false; }
    else { setTimeout(() => { if (!deferred) { txt.textContent = ': en el menú ⋮ del navegador, «Añadir a pantalla de inicio».'; box.hidden=false; } }, 1500); }
  }
  function recordar(){ try{ localStorage.setItem('pt-inst','1'); }catch(e){} }
  async function instalar(){
    if (deferred) { const d=deferred; deferred=null; d.prompt(); try{ await d.userChoice; }catch(e){} box.hidden=true; recordar(); return; }
    how.textContent = ios ? 'En iPhone o iPad: pulsa el botón Compartir de Safari y elige «Añadir a pantalla de inicio».'
      : android ? 'En el móvil: abre el menú ⋮ del navegador y elige «Instalar aplicación» o «Añadir a pantalla de inicio».'
      : 'En el ordenador: pulsa el icono de instalar que sale a la derecha de la barra de direcciones, o en el menú del navegador elige «Instalar Parsur Flota».';
    how.hidden = false;
  }
  go.addEventListener('click', instalar);
  btn.addEventListener('click', instalar);
  x.addEventListener('click', () => { box.hidden=true; recordar(); });
})();
</script>
