{{-- Tutorial de primera vez: 4 pantallas sencillas --}}
<div class="tour" id="tour" role="dialog" aria-modal="true">
  <div class="tour-c">
    <div class="tour-s on">
      <div class="tour-e">👋</div>
      <h2>Bienvenido, {{ \Illuminate\Support\Str::of($pc->displayName())->title() }}</h2>
      <p>Esta es tu área en Recambios Parsur. Aquí tienes <b>tus máquinas</b>, lo que llevan, lo que gastas y puedes <b>pedirnos piezas</b> sin llamar. Te lo enseñamos en 4 pasos.</p>
    </div>
    <div class="tour-s">
      <div class="tour-e">🚜</div>
      <h2>1. Tus máquinas</h2>
      <p>En <b>Máquinas</b> están las que conocemos. Pulsa una para ver lo que lleva (filtros, cuchillas…), sus horas y su gasto. Si falta alguna, pulsa <b>Añadir máquina</b> y pon marca, modelo y nº de serie.</p>
    </div>
    <div class="tour-s">
      <div class="tour-e">🛒</div>
      <h2>2. Pedir piezas</h2>
      <p>Pulsa <b>Pedir piezas</b>, elige la máquina, <b>marca lo que necesitas</b> y pon la cantidad. Si no está en la lista, escríbelo con tus palabras. Nosotros buscamos la referencia y te confirmamos precio y plazo.</p>
    </div>
    <div class="tour-s">
      <div class="tour-e">🔔</div>
      <h2>3. Horas y avisos</h2>
      <p>Pon las <b>horas</b> del cuentahoras de vez en cuando y te avisaremos cuando toque el <b>mantenimiento</b>. También puedes ponerte avisos ("revisar ruedas el día 10") y apuntar dónde está cada máquina.</p>
    </div>
    <div class="tour-s">
      <div class="tour-e">📞</div>
      <h2>4. Cualquier duda</h2>
      <p>Abajo del todo tienes nuestro teléfono y WhatsApp. Y en <b>Ayuda</b> puedes volver a ver esto cuando quieras.</p>
    </div>
    <div class="tour-b">
      <button type="button" class="linkbtn" id="tour-skip">Saltar</button>
      <span class="tour-d"></span>
      <button type="button" class="btn y" id="tour-next">Siguiente</button>
    </div>
  </div>
</div>
<script>
(function(){
  const t=document.getElementById('tour'), steps=[...t.querySelectorAll('.tour-s')], next=document.getElementById('tour-next'), skip=document.getElementById('tour-skip'), dots=t.querySelector('.tour-d');
  let i=0; dots.innerHTML=steps.map((_,k)=>'<i'+(k===0?' class="on"':'')+'></i>').join('');
  function show(){ steps.forEach((s,k)=>s.classList.toggle('on',k===i)); dots.querySelectorAll('i').forEach((d,k)=>d.classList.toggle('on',k===i)); next.textContent=i===steps.length-1?'¡Empezar!':'Siguiente'; }
  function done(){ t.remove(); fetch('/portal/tutorial-visto',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}); }
  next.addEventListener('click',()=>{ if(i<steps.length-1){i++;show();} else done(); });
  skip.addEventListener('click',done);
})();
</script>
