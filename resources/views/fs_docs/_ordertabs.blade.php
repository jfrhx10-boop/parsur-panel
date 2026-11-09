@include('partials_newbtns')
<div class="tabs" style="margin-bottom:12px;flex-wrap:wrap">
  <a href="/documentos/pedido" class="{{ request()->is('documentos/pedido') ? 'on' : '' }}">Pedidos de clientes (FactuSOL)</a>
  <a href="/pedidos" class="{{ request()->is('pedidos') || request()->is('pedidos/*') ? 'on' : '' }}">De la web</a>
  <a href="/pedidos-portal" class="{{ request()->is('pedidos-portal*') ? 'on' : '' }}">Del portal de clientes</a>
  <a href="/llegadas">➜ Llegada de mercancía</a>
</div>
