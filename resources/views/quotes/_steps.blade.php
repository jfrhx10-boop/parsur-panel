@php
  // Recorrido de un presupuesto: Presupuesto → Aceptado → Pedido → Mercancía → Servido
  $inFs = (bool) $quote->fs_code; $acc = $quote->status === 'aceptado' || $order; $hasOrder = (bool) $quote->fs_order_code;
  $st = $progress['state'] ?? null;
  $arrived = $st && in_array($st, ['almacen', 'parcial', 'servido'], true);
  $served = $st === 'servido';
  $steps = [
    ['Presupuesto', $inFs ? 'done' : 'cur', $inFs ? 'En FactuSOL' : 'Sin enviar a FactuSOL'],
    ['Aceptado', $acc ? 'done' : ($inFs ? 'cur' : ''), $acc ? 'Sí' : 'Esperando al cliente'],
    ['Pedido', $hasOrder ? 'done' : ($acc ? 'cur' : ''), $hasOrder ? 'Pedido '.$quote->fs_order_code : 'Pasar a pedido'],
    ['Mercancía', $served || ($progress && $progress['pending'] <= 0 && $progress['received'] > 0) ? 'done' : ($hasOrder ? 'cur' : ''), $progress ? ($progress['pending'] <= 0 ? 'Toda recibida' : $progress['received'] > 0 ? 'Llegada en parte' : 'Pendiente de recibir') : '—'],
    ['Albarán', $served ? 'done' : ($arrived ? 'cur' : ''), $served ? 'Servido' : ($arrived ? 'Crear albarán' : '—')],
  ];
  $next = ! $inFs ? 'Envíalo a FactuSOL para que quede guardado allí.' : (! $acc ? 'Cuando el cliente acepte, márcalo como Aceptado y pásalo a pedido.' : (! $hasOrder ? 'Pásalo a pedido en FactuSOL.' : ($served ? 'Todo servido. Queda facturar en FactuSOL.' : ($arrived ? 'Ha llegado mercancía: crea el albarán con lo recibido.' : 'Esperando la mercancía: cuando llegue, regístrala en Llegada.'))));
@endphp
<div class="card" style="margin:10px 0">
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    @foreach($steps as $i => [$name, $state, $sub])
      <div style="flex:1 1 120px;padding:8px 10px;border-radius:8px;border:1px solid var(--line);{{ $state === 'done' ? 'background:rgba(34,160,90,.12);border-color:#22a05a' : ($state === 'cur' ? 'border-color:var(--yel);background:rgba(255,200,0,.14)' : 'opacity:.6') }}">
        <div class="small" style="font-weight:700">{{ $i + 1 }}. {{ $name }} @if($state === 'done')✓@endif</div>
        <div class="small muted">{{ $sub }}</div>
      </div>
    @endforeach
  </div>
  <div class="small" style="margin-top:8px"><b>Siguiente:</b> {{ $next }} @if($hasOrder && ! $served)<a href="/llegadas">→ Llegada de mercancía</a>@endif</div>
</div>
