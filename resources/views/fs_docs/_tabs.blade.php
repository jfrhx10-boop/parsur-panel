<div class="tabs" style="flex-wrap:wrap">
  <a href="/pedidos" class="{{ request()->is('pedidos') || request()->is('pedidos/*') ? 'on' : '' }}">Web</a>
  <a href="/documentos/pedido" class="{{ request()->is('documentos/pedido') ? 'on' : '' }}">Pedidos FactuSOL</a>
  <a href="/documentos/albaran" class="{{ request()->is('documentos/albaran') ? 'on' : '' }}">Albaranes</a>
  <a href="/documentos/factura" class="{{ request()->is('documentos/factura') ? 'on' : '' }}">Facturas</a>
  <a href="/documentos/entrada" class="{{ request()->is('documentos/entrada') ? 'on' : '' }}">Entradas de proveedor</a>
</div>
