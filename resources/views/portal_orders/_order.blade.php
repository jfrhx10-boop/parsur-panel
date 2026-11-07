<details class="doc t-pedido op op-{{ ['nuevo' => 'pendiente', 'aceptado' => 'parcial', 'servido' => 'servido', 'rechazado' => 'anulado'][$o->status] ?? 'pendiente' }}" {{ $o->status === 'nuevo' ? 'open' : '' }}>
  <summary>
    <span class="doc-ic">{!! \App\Services\Icons::svg('globe') !!}</span>
    <span class="doc-main">
      <span class="doc-n">Portal <b>{{ $o->number }}</b> <span class="doc-d">{{ $o->created_at->format('d/m/Y H:i') }}</span>@if($o->machine) · <b>{{ trim(($o->machine->brand ?? '').' '.$o->machine->model) }}</b>@endif</span>
      @if(!empty($showCustomer))<span class="doc-p"><a href="/clientes/{{ $o->customer_id }}" onclick="event.stopPropagation()">{{ $o->customer?->displayName() }}</a></span>@endif
      <span class="small muted">{{ $o->lines->count() }} líneas @if($o->contact_name) · {{ $o->contact_name }}@endif @if($o->contact_phone) · {{ $o->contact_phone }}@endif</span>
    </span>
    <span class="doc-r"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span></span>
  </summary>
  <table class="tbl doc-lines"><thead><tr><th>Pieza (como la ve el cliente)</th><th>Ref. interna</th><th class="num">Cant.</th></tr></thead><tbody>
  @foreach($o->lines as $l)<tr><td>{{ $l->label }}</td><td>@if($l->ref)<a href="/articulos/{{ rawurlencode($l->ref) }}"><code class="art">{{ $l->ref }}</code></a>@else<span class="muted small">sin referencia · buscar</span>@endif</td><td class="num">{{ rtrim(rtrim(number_format((float) $l->qty, 2, ',', ''), '0'), ',') }}</td></tr>@endforeach
  </tbody>@if($o->notes)<tfoot><tr><td colspan="3" class="small">{{ $o->notes }}</td></tr></tfoot>@endif</table>
  <div class="doc-actions">
    @if($o->quote_id)<a class="btn sm" href="/presupuestos/{{ $o->quote_id }}">Ver presupuesto</a>@else<form method="post" action="/pedidos-portal/{{ $o->id }}/presupuesto">@csrf<button class="btn sm y" type="submit">Convertir en presupuesto</button></form>@endif
    <form method="post" action="/pedidos-portal/{{ $o->id }}/estado" class="row" style="gap:6px;align-items:center">@csrf
      <select name="status" class="sm">@foreach(\App\Models\PortalOrder::STATUSES as $k => $v)<option value="{{ $k }}" {{ $o->status === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select>
      <button class="btn ghost sm" type="submit">Cambiar estado</button>
    </form>
    @if($o->customer?->bestPhone())<a class="btn ghost sm" href="https://wa.me/{{ preg_replace('/\D+/', '', '34'.ltrim($o->customer->bestPhone(), '+34')) }}?text={{ rawurlencode('Hola, hemos recibido tu pedido '.$o->number.' desde el área de clientes. ') }}" target="_blank" rel="noopener">WhatsApp al cliente</a>@endif
  </div>
</details>
