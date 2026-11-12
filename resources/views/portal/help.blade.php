@extends('portal.layout')
@section('title', 'Ayuda')
@section('content')
<h1>Ayuda</h1>
<div class="card">
  <div class="hp"><div class="hp-e"><i class="ic ic-machine"></i></div><div><h2>Mis máquinas</h2><p>En <b>Máquinas</b> ves las tuyas. Pulsa una para ver lo que lleva, las horas, dónde está y lo que has gastado en ella. <b>Añadir máquina</b>: marca, modelo y nº de serie (está en la placa).</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-cart"></i></div><div><h2>Pedir piezas</h2><p>Pulsa <b>Pedir piezas</b>, elige la máquina y marca lo que necesitas. Si no está en la lista, escríbelo tal cual ("2 filtros de aire"). También puedes <b>repetir</b> una compra anterior desde Gastos. Te confirmamos precio y plazo.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-clock"></i></div><div><h2>Horas y mantenimiento</h2><p>En la máquina, pulsa <b>Poner horas</b> y escribe lo que marca el cuentahoras. Dinos cada cuántas horas haces el mantenimiento (250, 500, 1000) y te avisaremos cuando toque. Cuando lo hagas, pulsa <b>Ya le he hecho el mantenimiento</b>.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-bell"></i></div><div><h2>Avisos</h2><p>En <b>Avisos</b> tienes todo lo que toca atender: mantenimientos, seguro, revisión y los avisos que te pongas tú ("revisar ruedas el día 10"). Pulsa <b>Hecho</b> cuando lo resuelvas.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-pin"></i></div><div><h2>Dónde está cada máquina</h2><p>En la máquina, en <b>¿Dónde está la máquina?</b>, apunta la obra, el lugar y para qué cliente trabaja. Así lo sabéis todos los de la empresa.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-user"></i></div><div><h2>Maquinistas y contactos</h2><p>En <b>Contactos</b> guarda maquinistas, talleres y proveedores con su teléfono. En cada máquina puedes poner quién la lleva.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-euro"></i></div><div><h2>Gastos</h2><p>En <b>Gastos</b> ves lo que llevas por año y por máquina, y cada albarán con sus conceptos. Importes sin IVA.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-mobile"></i></div><div><h2>Ponlo como una app en el móvil</h2><p>En el iPhone: botón Compartir → <b>Añadir a pantalla de inicio</b>. En Android: menú ⋮ del navegador → <b>Añadir a pantalla de inicio</b>. Así entras con un toque.</p></div></div>
  <div class="hp"><div class="hp-e"><i class="ic ic-lock"></i></div><div><h2>Tu acceso</h2><p>Entras con tu teléfono y tu PIN. No compartas el PIN. Si crees que alguien lo tiene, llámanos y te damos otro.</p></div></div>
  <form method="post" action="/portal/tutorial-visto" style="margin-top:14px">@csrf<input type="hidden" name="otra_vez" value="1"><button class="btn y" type="submit">Volver a ver el tutorial</button></form>
</div>
@endsection
