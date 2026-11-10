@extends('layout')
@section('title', $quote->number)
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); $c = $quote->customer; @endphp
<div class="head">
  <div>
    <a class="small" href="/clientes/{{ $c->id }}">← {{ $c->displayName() }}</a>
    <h1>{{ ['pedido' => 'Pedido nuevo', 'albaran' => 'Albarán nuevo'][$quote->kind] ?? 'Presupuesto' }} {{ $quote->number }} @if($quote->isDraftDoc())<span class="badge warn">Sin crear en FactuSOL</span>@else<span class="badge {{ $quote->statusClass() }}">{{ $quote->statusLabel() }}</span>@endif</h1>
    <div class="muted small">@if($quote->ref){{ $quote->ref }} · @endif Tarifa {{ $c->tariff ?? 1 }}@if($c->paymentTerms()) · {{ $c->paymentTerms() }}@endif · {{ $c->bestPhone() }}</div>
  </div>
  <div class="row" style="gap:8px">
    <a class="btn y" href="/presupuestos/{{ $quote->id }}/pdf" target="_blank">📄 PDF · WhatsApp · Outlook</a>
    @if($quote->isDraftDoc())
    <form method="post" action="/presupuestos/{{ $quote->id }}/crear" onsubmit="return confirm('Se crea {{ $quote->kind === 'pedido' ? 'el pedido' : 'el albarán' }} en FactuSOL con estas líneas. ¿Seguir?')">@csrf<button class="btn y" type="submit" @disabled($quote->lines->isEmpty())>✓ Crear {{ $quote->kind === 'pedido' ? 'pedido' : 'albarán' }} en FactuSOL</button></form>
    @else
    @if($quote->fs_code)<span class="badge ok" title="Ya está en FactuSOL">En FactuSOL · {{ $quote->fs_code }}/{{ $quote->fs_year }}</span><form method="post" action="/presupuestos/{{ $quote->id }}/factusol/quitar" onsubmit="return confirm('Se borra este presupuesto de FactuSOL (el del panel se queda). ¿Seguir?')">@csrf<button class="btn ghost sm" type="submit">Quitar de FactuSOL</button></form>@elseif($quote->customer?->codcli)<form method="post" action="/presupuestos/{{ $quote->id }}/factusol" onsubmit="return confirm('Se crea este presupuesto en FactuSOL. ¿Seguir?')">@csrf<button class="btn" type="submit">Enviar a FactuSOL</button></form>@endif
    @if($quote->fs_order_code)<span class="badge ok">Pedido FactuSOL · {{ $quote->fs_order_code }}</span><form method="post" action="/presupuestos/{{ $quote->id }}/pedido/quitar" onsubmit="return confirm('Se borra el pedido de FactuSOL. ¿Seguir?')">@csrf<button class="btn ghost sm" type="submit">Quitar pedido de FactuSOL</button></form>@elseif($quote->customer?->codcli)<form method="post" action="/presupuestos/{{ $quote->id }}/pedido" onsubmit="return confirm('Se crea un PEDIDO de cliente en FactuSOL con estas líneas. ¿Seguir?')">@csrf<button class="btn" type="submit">Pasar a pedido en FactuSOL</button></form>@endif
    @endif
    <form method="post" action="/presupuestos/{{ $quote->id }}/borrar" onsubmit="return confirm('¿Borrar este presupuesto?')">@csrf<button class="btn ghost" type="submit">Borrar</button></form>
  </div>
</div>
@unless($quote->isDraftDoc())
@include('quotes._steps')
@endunless

@if($missing->isNotEmpty())<div class="alerts"><div class="al al-aviso"><span class="al-k">Aviso</span><span class="al-t">{{ $missing->count() }} {{ $missing->count() === 1 ? 'línea no está' : 'líneas no están' }} en el catálogo de FactuSOL ({{ $missing->pluck('art')->join(', ') }}): revisa el precio a mano.</span></div></div>@endif

<form method="post" action="/presupuestos/{{ $quote->id }}" id="qf">
  @csrf
  <div class="card">
    <div class="row">
      <div style="flex:0 1 150px"><label>Fecha</label><input type="date" name="date" value="{{ $quote->date->toDateString() }}" required></div>
      <div style="flex:0 1 150px"><label>Válido hasta</label><input type="date" name="valid_until" value="{{ $quote->valid_until?->toDateString() }}"></div>
      <div style="flex:1 1 180px"><label>Máquina / referencia</label><input type="text" name="ref" value="{{ $quote->ref }}"></div>
      <div style="flex:0 1 90px"><label>IVA %</label><input type="number" name="iva_pct" value="{{ $fmt($quote->iva_pct) }}" step="0.5" min="0" max="30"></div>
      <div style="flex:0 1 160px"><label>Estado</label><select name="status">@foreach(\App\Models\Quote::STATUSES as $k => $v)<option value="{{ $k }}" {{ $quote->status === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
    </div>
  </div>

  <div class="card mt">
    <h2>Líneas</h2>
    <div class="scroll"><table class="tbl ql">
      <thead><tr><th style="width:140px">Referencia</th><th>Descripción</th><th class="num" style="width:90px">Cant.</th><th class="num" style="width:110px">Precio</th><th class="num" style="width:80px">Dto. %</th><th class="num" style="width:110px">Importe</th><th></th></tr></thead>
      <tbody>
      @forelse($quote->lines as $l)
        <tr>
          <td>@if($l->art)<a href="/articulos/{{ rawurlencode($l->art) }}" target="_blank"><code class="art">{{ $l->art }}</code></a>@else<span class="muted">—</span>@endif</td>
          <td><input type="text" name="lines[{{ $l->id }}][description]" value="{{ $l->description }}">@include('partials_costedit', ['art' => $l->art, 'price' => $l->price])</td>
          <td><input type="number" class="num" name="lines[{{ $l->id }}][qty]" value="{{ $fmt($l->qty) }}" step="0.01" min="0"></td>
          <td><input type="number" class="num" name="lines[{{ $l->id }}][price]" value="{{ number_format($l->price, 2, '.', '') }}" step="0.01" min="0"></td>
          <td><input type="number" class="num" name="lines[{{ $l->id }}][discount]" value="{{ $fmt($l->discount) }}" step="0.5" min="0" max="100"></td>
          <td class="num"><strong>{{ eur($l->total) }}</strong></td>
          <td class="num"><button class="linkbtn" type="submit" form="rm-{{ $l->id }}" title="Quitar línea" style="color:var(--bad);font-size:18px">×</button></td>
        </tr>
      @empty<tr><td colspan="7" class="muted">Sin líneas. Añade abajo.</td></tr>@endforelse
      </tbody>
      <tfoot>
        <tr><td colspan="5" class="num">Base imponible</td><td class="num">{{ eur($quote->base) }}</td><td></td></tr>
        <tr><td colspan="5" class="num">IVA {{ $fmt($quote->iva_pct) }} %</td><td class="num">{{ eur($quote->iva) }}</td><td></td></tr>
        <tr><td colspan="5" class="num"><strong>Total</strong></td><td class="num"><strong style="font-size:17px">{{ eur($quote->total) }}</strong></td><td></td></tr>
      </tfoot>
    </table></div>
    <div class="row" style="margin-top:10px"><div style="flex:1 1 100%"><label>Observaciones (salen en el PDF)</label><input type="text" name="notes" value="{{ $quote->notes }}" placeholder="Plazo de entrega, portes, condiciones…"></div></div>
    <div class="row" style="margin-top:10px"><button class="btn" type="submit">Guardar cambios</button><span class="small muted" style="align-self:center">Los importes se recalculan al guardar.</span></div>
  </div>
</form>
@foreach($quote->lines as $l)<form method="post" action="/presupuestos/{{ $quote->id }}/lineas/{{ $l->id }}/quitar" id="rm-{{ $l->id }}">@csrf</form>@endforeach

<div class="card mt">
  <h2>Añadir línea</h2>
  <form method="post" action="/presupuestos/{{ $quote->id }}/lineas" class="row">
    @csrf
    <div style="flex:1 1 200px"><label>Referencia (del catálogo o equivalente)</label><input type="text" name="art" placeholder="SO115, P502161, 1R0716…" list="arts" id="artIn" autocomplete="off" autofocus></div>
    <div style="flex:2 1 240px"><label>Descripción (si no está en el catálogo)</label><input type="text" name="description" placeholder="Mano de obra, portes…"></div>
    <div style="flex:0 1 100px"><label>Cantidad</label><input type="number" name="qty" value="1" step="0.01" min="0.01"></div>
    <button class="btn y" type="submit">Añadir</button>
  </form>
  <script>
  (function(){var a=document.getElementById('artIn');if(!a)return;var f=a.form,busy=false;
    // Al confirmar la referencia (Tab, clic fuera o elegir del desplegable) se añade sola con la tarifa del cliente.
    a.addEventListener('change',function(){if(busy||!a.value.trim())return;busy=true;f.requestSubmit();});
  })();
  </script>
  <p class="small muted" style="margin:8px 0 0">Escribe la referencia y pulsa Tab (o elígela del desplegable): se añade sola. El precio sale solo de la tarifa {{ $c->tariff ?? 1 }} del cliente; si la referencia no está en el catálogo, se añade a 0 € para ponerlo a mano.</p>
</div>
@endsection
