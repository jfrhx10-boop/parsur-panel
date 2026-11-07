@extends('portal.layout')
@section('title', 'Pedido '.$order->number)
@section('content')
<a class="small" href="/portal/pedidos">← Mis pedidos</a>
<div class="head" style="margin-top:6px"><div><h1>Pedido {{ $order->number }} <span class="badge po-{{ $order->status }}">{{ $order->statusLabel() }}</span></h1><div class="muted small">{{ $order->created_at->format('d/m/Y H:i') }}@if($order->machine) · {{ trim(($order->machine->brand ?? '').' '.$order->machine->model) }}@endif</div></div></div>
<div class="card">
  <table class="tbl"><thead><tr><th>Pieza</th><th class="num">Cantidad</th></tr></thead><tbody>
  @foreach($order->lines as $l)<tr><td>{{ $l->label }}</td><td class="num">{{ rtrim(rtrim(number_format((float) $l->qty, 2, ',', ''), '0'), ',') }}</td></tr>@endforeach
  </tbody></table>
  @if($order->notes)<p class="small" style="margin:10px 0 0"><b>Observaciones:</b> {{ $order->notes }}</p>@endif
  @if($order->contact_name || $order->contact_phone)<p class="small muted" style="margin:6px 0 0">Contacto: {{ $order->contact_name }} {{ $order->contact_phone }}</p>@endif
  <p class="small muted" style="margin:10px 0 0">@if($order->status === 'nuevo')Lo estamos revisando; te confirmamos disponibilidad y precio.@elseif($order->status === 'aceptado')En preparación.@elseif($order->status === 'servido')Servido. ¡Gracias!@else No ha sido posible servirlo; te contactamos.@endif</p>
</div>
@endsection
