<div class="tabs" style="flex-wrap:wrap">
  <a href="/pedidos" class="{{ request()->is('pedidos') || request()->is('pedidos/*') ? 'on' : '' }}">Web</a>
  <a href="/documentos/presupuesto" class="{{ request()->is('documentos/presupuesto') ? 'on' : '' }}">Presupuestos</a>
  <a href="/documentos/pedido" class="{{ request()->is('documentos/pedido') ? 'on' : '' }}">Pedidos FactuSOL</a>
  <a href="/documentos/albaran" class="{{ request()->is('documentos/albaran') ? 'on' : '' }}">Albaranes</a>
  @if(auth()->user()->canSeeSales())
  <a href="/documentos/factura" class="{{ request()->is('documentos/factura') ? 'on' : '' }}">Facturas</a>
  <a href="/documentos/entrada" class="{{ request()->is('documentos/entrada') ? 'on' : '' }}">Entradas de proveedor</a>
  <a href="/cobros" class="{{ request()->is('cobros') ? 'on' : '' }}">Cobros</a>
  @endif
</div>
