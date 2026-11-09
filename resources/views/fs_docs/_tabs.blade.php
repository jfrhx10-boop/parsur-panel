@include('partials_newbtns')
<div class="tabs" style="flex-wrap:wrap">
  <a href="/presupuestos">Presupuestos</a>
  <a href="/documentos/pedido" class="{{ request()->is('documentos/pedido') ? 'on' : '' }}">Pedidos</a>
  <a href="/documentos/albaran" class="{{ request()->is('documentos/albaran') ? 'on' : '' }}">Albaranes</a>
  @if(auth()->user()->canSeeSales())
  <a href="/documentos/factura" class="{{ request()->is('documentos/factura') ? 'on' : '' }}">Facturas</a>
  <a href="/documentos/entrada" class="{{ request()->is('documentos/entrada') ? 'on' : '' }}">Entradas de proveedor</a>
  <a href="/cobros" class="{{ request()->is('cobros') ? 'on' : '' }}">Cobros</a>
  @endif
</div>
