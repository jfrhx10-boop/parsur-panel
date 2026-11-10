@if(auth()->user()->canSeeSales())
<div class="row" style="gap:8px;margin:0 0 12px;flex-wrap:wrap">
  <a class="btn y" href="/nuevo/presupuesto">＋ Nuevo presupuesto</a>
  <a class="btn" href="/nuevo/pedido">＋ Nuevo pedido</a>
  <a class="btn" href="/nuevo/albaran">＋ Nuevo albarán</a>
  <a class="btn" href="/nueva-entrada">＋ Nueva entrada</a>
</div>
@endif
