@extends('layout')
@section('title', 'Pedido #'.$order['number'])
@section('content')
@php $b = $order['billing']; $s = $order['shipping']; @endphp
<div class="head">
  <div>
    <a class="small" href="/pedidos">← Pedidos</a>
    <h1>Pedido #{{ $order['number'] }} <span class="badge b-{{ $order['status'] }}">{{ estado_pedido($order['status']) }}</span></h1>
    <div class="muted small">{{ fecha($order['date_created']) }} · {{ $order['payment_method_title'] ?: 'Sin método de pago' }}</div>
  </div>
  <a class="btn ghost sm" target="_blank" rel="noopener" href="{{ \App\Models\Setting::get('woo_url') }}/wp-admin/post.php?post={{ $order['id'] }}&action=edit">Abrir en WooCommerce ↗</a>
</div>

<div class="grid g2">
  <div class="card">
    <h2>Cliente</h2>
    <p style="margin:0"><strong>{{ trim($b['first_name'].' '.$b['last_name']) }}</strong>@if($b['company'])<br>{{ $b['company'] }}@endif</p>
    @if($nif)<p style="margin:6px 0 0">NIF/CIF: <strong>{{ $nif }}</strong></p>@endif
    <p style="margin:6px 0 0">
      @if($b['phone'])📞 <a href="tel:{{ preg_replace('/\s+/', '', $b['phone']) }}">{{ $b['phone'] }}</a>
        · <a href="https://wa.me/{{ preg_replace('/\D+/', '', (str_starts_with(preg_replace('/\D+/', '', $b['phone']), '34') ? '' : '34').$b['phone']) }}" target="_blank" rel="noopener">WhatsApp</a><br>@endif
      @if($b['email'])✉️ <a href="mailto:{{ $b['email'] }}">{{ $b['email'] }}</a>@endif
    </p>
    @if(auth()->user()->canSeeSales())
    @php $nm = $b['company'] ?: (preg_match('/\b(S\.?L\.?U?|S\.?A\.?|SOCIEDAD|S\.?C\.?)\b/i', $b['first_name']) ? $b['first_name'] : trim($b['first_name'].' '.$b['last_name'])); @endphp
    <details style="margin-top:10px"><summary class="btn ghost sm" style="display:inline-block;cursor:pointer">＋ Crear cliente en FactuSOL</summary>
      <form method="post" action="/pedidos/{{ $order['id'] }}/cliente" class="row" style="margin-top:10px;gap:8px">@csrf
        <div style="flex:1 1 100%"><label>Nombre fiscal</label><input type="text" name="name" value="{{ $nm }}" required></div>
        <div style="flex:1 1 140px"><label>NIF/CIF</label><input type="text" name="nif" value="{{ $nif }}"></div>
        <div style="flex:1 1 140px"><label>Teléfono</label><input type="text" name="phone" value="{{ $b['phone'] }}"></div>
        <div style="flex:1 1 100%"><label>Email</label><input type="text" name="email" value="{{ $b['email'] }}"></div>
        <div style="flex:1 1 100%"><label>Dirección</label><input type="text" name="address" value="{{ trim($b['address_1'].' '.$b['address_2']) }}"></div>
        <div style="flex:1 1 90px"><label>C.P.</label><input type="text" name="postcode" value="{{ $b['postcode'] }}"></div>
        <div style="flex:2 1 140px"><label>Población</label><input type="text" name="city" value="{{ $b['city'] }}"></div>
        <div style="flex:2 1 140px"><label>Provincia</label><input type="text" name="province" value="{{ $b['state'] }}"></div>
        <button class="btn y" type="submit">Crear en FactuSOL</button>
      </form>
    </details>
    @endif
  </div>
  <div class="card">
    <h2>Envío</h2>
    @php $a = !empty($s['address_1']) ? $s : $b; @endphp
    <p style="margin:0">{{ trim($a['first_name'].' '.$a['last_name']) }}<br>
      {{ $a['address_1'] }} {{ $a['address_2'] }}<br>
      {{ $a['postcode'] }} {{ $a['city'] }} @if($a['state'])({{ $a['state'] }})@endif · {{ $a['country'] }}</p>
    @if($order['customer_note'])<div class="alert info mt" style="margin-bottom:0">Nota del cliente: {{ $order['customer_note'] }}</div>@endif
  </div>
</div>

<div class="card mt"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Producto</th><th>Ref.</th><th class="r">Uds.</th><th class="r">Precio</th><th class="r">Total</th></tr></thead>
    <tbody>
    @foreach($order['line_items'] as $li)
      <tr><td>{{ $li['name'] }}</td><td class="small muted">{{ $li['sku'] }}</td><td class="r">{{ $li['quantity'] }}</td><td class="r">{{ eur($li['price']) }}</td><td class="r">{{ eur($li['total']) }}</td></tr>
    @endforeach
    <tr><td colspan="4" class="r muted">Envío</td><td class="r">{{ eur($order['shipping_total']) }}</td></tr>
    <tr><td colspan="4" class="r muted">IVA</td><td class="r">{{ eur($order['total_tax']) }}</td></tr>
    <tr><td colspan="4" class="r"><strong>Total</strong></td><td class="r"><strong>{{ eur($order['total']) }}</strong></td></tr>
    </tbody>
  </table>
</div></div>
@endsection
