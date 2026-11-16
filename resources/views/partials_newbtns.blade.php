@if(auth()->user()->canSeeSales())
<div class="row nb-row" style="gap:8px;margin:0 0 12px;flex-wrap:wrap">
  <a class="btn nb nb-pre" href="/nuevo/presupuesto">＋ Nuevo presupuesto</a>
  <a class="btn nb nb-ped" href="/nuevo/pedido">＋ Nuevo pedido</a>
  <a class="btn nb nb-alb" href="/nuevo/albaran">＋ Nuevo albarán</a>
  <a class="btn nb nb-ent" href="/nueva-entrada">＋ Nueva entrada</a>
  <a class="btn nb nb-fre" href="/nueva-entrada?tipo=factura">＋ Nueva factura recibida</a>
</div>
@endif
