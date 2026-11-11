@extends('layout')
@section('title', $quote->number)
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); $c = $quote->customer; $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0'; @endphp
<div class="head">
  <div>
    <a class="small" href="/clientes/{{ $c->id }}">← {{ $c->displayName() }}@if($c->codcli) · nº {{ $c->codcli }}@endif</a>
    @unless($quote->fs_order_code)<details class="chcli" style="display:inline-block;margin-left:8px;position:relative"><summary class="small" style="cursor:pointer;color:var(--mut,#666);text-decoration:underline">cambiar cliente</summary>
      <form method="post" action="/presupuestos/{{ $quote->id }}/cliente" class="card" style="position:absolute;z-index:40;left:0;top:22px;width:min(420px,90vw);box-shadow:0 12px 34px rgba(0,0,0,.18)">@csrf
        <input type="hidden" name="customer_id" id="chcli-id">
        <label>Cliente nuevo</label><input type="text" id="chcli-q" placeholder="Nombre, NIF, teléfono o nº de cliente…" autocomplete="off">
        <div id="chcli-list" class="small" style="margin-top:6px;max-height:260px;overflow:auto"></div>
        <label class="small" style="display:block;margin:8px 0"><input type="checkbox" name="precios" value="1"> Poner los precios de la tarifa del cliente nuevo</label>
        <button class="btn y sm" type="submit" id="chcli-ok" disabled>Cambiar cliente</button>
      </form></details>@endunless
    <h1>{{ ['pedido' => 'Pedido nuevo', 'albaran' => 'Albarán nuevo'][$quote->kind] ?? 'Presupuesto' }} {{ $quote->number }} @if($quote->isDraftDoc())<span class="badge warn">Sin crear en FactuSOL</span>@else<span class="badge {{ $quote->statusClass() }}">{{ $quote->statusLabel() }}</span>@endif</h1>
    <div class="muted small">@if($quote->ref){{ $quote->ref }} · @endif Tarifa {{ $c->tariff ?? 1 }}@if($c->paymentTerms()) · {{ $c->paymentTerms() }}@endif · {{ $c->bestPhone() }}</div>
  </div>
  <div class="qactions">
    <a class="btn y" href="/presupuestos/{{ $quote->id }}/pdf" target="_blank">📄 PDF · WhatsApp · Outlook</a>
    @if($quote->isDraftDoc())
    <form method="post" action="/presupuestos/{{ $quote->id }}/crear" onsubmit="return confirm('Se crea {{ $quote->kind === 'pedido' ? 'el pedido' : 'el albarán' }} en FactuSOL con estas líneas. ¿Seguir?')">@csrf<button class="btn y" type="submit" @disabled($quote->lines->isEmpty())>✓ Crear {{ $quote->kind === 'pedido' ? 'pedido' : 'albarán' }} en FactuSOL</button></form>
    @else
    @if($quote->fs_code)<span class="badge ok" title="Ya está en FactuSOL">En FactuSOL · {{ $quote->fs_code }}/{{ $quote->fs_year }}</span><span id="fsst" class="badge {{ $quote->fs_dirty ? 'warn' : ($quote->fs_error ? 'bad' : '') }}" data-dirty="{{ $quote->fs_dirty ? 1 : 0 }}" @if(! $quote->fs_dirty && ! $quote->fs_error) hidden @endif>{{ $quote->fs_dirty ? '⏳ Guardando en FactuSOL…' : '⚠ Sin guardar en FactuSOL' }}</span><form method="post" action="/presupuestos/{{ $quote->id }}/factusol/quitar" onsubmit="return confirm('Se borra este presupuesto de FactuSOL (el del panel se queda). ¿Seguir?')">@csrf<button class="btn ghost sm" type="submit">Quitar de FactuSOL</button></form>@elseif($quote->customer?->codcli)<form method="post" action="/presupuestos/{{ $quote->id }}/factusol" onsubmit="return confirm('Se crea este presupuesto en FactuSOL. ¿Seguir?')">@csrf<button class="btn" type="submit">Enviar a FactuSOL</button></form>@endif
    @if($quote->fs_order_code)<span class="badge ok">Pedido FactuSOL · {{ $quote->fs_order_code }}</span><form method="post" action="/presupuestos/{{ $quote->id }}/pedido/quitar" onsubmit="return confirm('Se borra el pedido de FactuSOL. ¿Seguir?')">@csrf<button class="btn ghost sm" type="submit">Quitar pedido de FactuSOL</button></form>@elseif($quote->customer?->codcli)<form method="post" action="/presupuestos/{{ $quote->id }}/pedido" onsubmit="return confirm('Se crea un PEDIDO de cliente en FactuSOL con estas líneas. ¿Seguir?')">@csrf<button class="btn" type="submit">Pasar a pedido en FactuSOL</button></form>@endif
    @endif
    <form method="post" action="/presupuestos/{{ $quote->id }}/borrar" onsubmit="return confirm('¿Borrar este presupuesto?')">@csrf<button class="btn ghost" type="submit">Borrar</button></form>
  </div>
</div>

@unless($quote->isDraftDoc())
@php $payPaid = \App\Services\PayLink::isPaid($quote); $payOk = \App\Services\PayLink::current($quote); $payTel = preg_replace('/\D+/', '', (string) $c->bestPhone()); @endphp
<div class="card" id="paybox" style="margin-bottom:14px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px">
  <strong>💳 Pago con tarjeta</strong>
  @if($payPaid)
    <span class="badge ok">Pagado en la web · pedido nº {{ $quote->pay_order_id }}</span>
  @elseif($quote->pay_url && $payOk)
    <span class="badge warn">Pendiente de pago · {{ eur($quote->pay_total) }}</span>
    <input type="text" id="payurl" readonly value="{{ $quote->pay_url }}" style="flex:1 1 260px;min-width:0" onclick="this.select()">
    <button type="button" class="btn sm" onclick="navigator.clipboard.writeText(document.getElementById('payurl').value).then(()=>{this.textContent='Copiado ✓'})">Copiar enlace</button>
    <a class="btn sm" style="background:#25D366;color:#fff" target="_blank" rel="noopener" href="https://wa.me/{{ $payTel ? (str_starts_with($payTel, '34') ? $payTel : '34'.$payTel) : '' }}?text={{ rawurlencode('Hola, te paso el enlace para pagar con tarjeta el presupuesto '.$quote->number.' ('.eur($quote->total).', IVA incluido): '.$quote->pay_url) }}">Enviar por WhatsApp</a>
    <form method="post" action="/presupuestos/{{ $quote->id }}/pago/quitar" onsubmit="return confirm('Se quita el enlace de pago y el cliente ya no podrá pagar con él. ¿Seguir?')">@csrf<button class="btn ghost sm" type="submit">Quitar</button></form>
    <span class="muted small" style="flex:1 1 100%">El PDF del presupuesto lleva este enlace y su código QR.</span>
  @elseif($quote->pay_url)
    <span class="badge bad">El importe ha cambiado ({{ eur($quote->pay_total) }} → {{ eur($quote->total) }})</span>
    <form method="post" action="/presupuestos/{{ $quote->id }}/pago">@csrf<button class="btn y sm" type="submit">Rehacer el enlace con el importe nuevo</button></form>
    <form method="post" action="/presupuestos/{{ $quote->id }}/pago/quitar">@csrf<button class="btn ghost sm" type="submit">Quitar</button></form>
  @else
    <form method="post" action="/presupuestos/{{ $quote->id }}/pago">@csrf<button class="btn sm" type="submit" @disabled($quote->lines->isEmpty())>Crear enlace de pago y QR</button></form>
    <span class="muted small">El cliente paga {{ eur($quote->total) }} con tarjeta en recambiosparsur.com, sin registrarse.</span>
  @endif
  @error('pay')<span class="small" style="flex:1 1 100%;color:var(--bad)">{{ $message }}</span>@enderror
</div>
@endunless

@if($quote->fs_error && ! $quote->fs_dirty)<div class="alerts" id="fserr"><div class="al al-aviso"><span class="al-k">FactuSOL</span><span class="al-t">El último cambio está guardado en el panel pero no en FactuSOL: {{ $quote->fs_error }} — pulsa «Guardar cambios» para reintentarlo.</span></div></div>@endif
@if($missing->isNotEmpty())<div class="alerts">
@foreach($missing as $m)
  <div class="al al-aviso"><span class="al-k">Nueva</span><span class="al-t">
    <form method="post" action="/presupuestos/{{ $quote->id }}/lineas/{{ $m->id }}/articulo" class="newart">@csrf
      <span><strong>{{ $m->art }}</strong> no está en FactuSOL. Créala aquí:</span>
      <input type="text" name="description" value="{{ old('description', mb_strtoupper(trim($m->description)) === mb_strtoupper(trim($m->art)) ? '' : $m->description) }}" placeholder="Descripción" maxlength="50" required>
      <input type="text" name="cost" class="num newart-cost" value="{{ old('cost') }}" placeholder="Coste" inputmode="decimal" required @if(session('new_ref') == $m->id) data-focus="1" @endif>
      <button class="btn y sm" type="submit">Crear en FactuSOL · tarifa {{ $c->tariff ?? 1 }}</button>
    </form>
  </span></div>
@endforeach
</div>@endif

<form method="post" action="/presupuestos/{{ $quote->id }}" id="qf">
  @csrf
  <div class="card">
    <h2>Líneas</h2>
    <div class="scroll"><table class="tbl ql">
      <thead><tr><th style="width:140px">Referencia</th><th>Descripción</th><th class="num" style="width:90px">Cant.</th><th class="num" style="width:110px">Precio</th><th class="num" style="width:80px">Dto. %</th><th class="num" style="width:110px">Importe</th><th></th></tr></thead>
      <tbody>
      @forelse($quote->lines as $l)
        <tr>
          <td>@if($l->art)<a href="/articulos/{{ rawurlencode($l->art) }}" target="_blank"><code class="art">{{ $l->art }}</code></a>@else<input type="text" class="ql-art" name="lines[{{ $l->id }}][art]" placeholder="Ref.…" autocomplete="off" title="Esta línea no tiene referencia: escríbela para enlazarla con el artículo">@endif</td>
          <td><input type="text" name="lines[{{ $l->id }}][description]" value="{{ $l->description }}">@include('partials_costedit', ['art' => $l->art, 'price' => $l->price])</td>
          <td><input type="number" class="num" name="lines[{{ $l->id }}][qty]" value="{{ $num($l->qty) }}" step="any" min="0" inputmode="decimal"></td>
          <td><input type="number" class="num" name="lines[{{ $l->id }}][price]" value="{{ number_format($l->price, 2, '.', '') }}" step="any" min="0" inputmode="decimal"></td>
          <td><input type="number" class="num" name="lines[{{ $l->id }}][discount]" value="{{ $num($l->discount) }}" step="any" min="0" max="100" inputmode="decimal"></td>
          <td class="num"><strong>{{ eur($l->total) }}</strong></td>
          <td class="num"><button class="linkbtn" type="submit" form="rm-{{ $l->id }}" title="Quitar línea" style="color:var(--bad);font-size:18px">×</button></td>
        </tr>
      @empty<tr><td colspan="7" class="muted">Sin líneas. Añade la primera en la fila de abajo.</td></tr>@endforelse
      <tr class="ql-add">
        <td><input type="text" name="art" form="addf" placeholder="Referencia o descripción…" id="artIn" autocomplete="off"></td>
        <td><input type="text" name="description" form="addf" placeholder="Descripción (si no está en el catálogo) · Mano de obra, portes…"></td>
        <td><input type="number" class="num" name="qty" form="addf" value="1" step="any" min="0.01" inputmode="decimal"></td>
        <td class="num small muted" colspan="2">precio de la tarifa {{ $c->tariff ?? 1 }}</td>
        <td class="num"><button class="btn y sm" type="submit" form="addf">＋ Añadir</button></td>
        <td></td>
      </tr>
      </tbody>
      <tfoot>
        <tr><td colspan="5" class="num">Base imponible</td><td class="num">{{ eur($quote->base) }}</td><td></td></tr>
        <tr><td colspan="5" class="num">IVA {{ $fmt($quote->iva_pct) }} %</td><td class="num">{{ eur($quote->iva) }}</td><td></td></tr>
        <tr><td colspan="5" class="num"><strong>Total</strong></td><td class="num"><strong style="font-size:17px">{{ eur($quote->total) }}</strong></td><td></td></tr>
      </tfoot>
    </table></div>
    <div class="row" style="margin-top:10px"><div style="flex:1 1 100%"><label>Observaciones (salen en el PDF)</label><input type="text" name="notes" value="{{ $quote->notes }}" placeholder="Plazo de entrega, portes, condiciones…"></div></div>
    <div class="row" style="margin-top:10px"><button class="btn" type="submit">Guardar cambios</button><span class="small muted" style="align-self:center">Los cambios se guardan solos al salir de cada casilla.</span></div>
  </div>
  <div class="card mt">
    <div class="row">
      <div style="flex:0 1 150px"><label>Fecha</label><input type="date" name="date" value="{{ $quote->date->toDateString() }}" required></div>
      <div style="flex:0 1 150px"><label>Válido hasta</label><input type="date" name="valid_until" value="{{ $quote->valid_until?->toDateString() }}"></div>
      <div style="flex:1 1 180px"><label>Máquina / referencia</label><input type="text" name="ref" value="{{ $quote->ref }}"></div>
      <div style="flex:0 1 90px"><label>IVA %</label><input type="number" name="iva_pct" value="{{ $num($quote->iva_pct) }}" step="any" min="0" max="30"></div>
      <div style="flex:0 1 160px"><label>Estado</label><select name="status">@foreach(\App\Models\Quote::STATUSES as $k => $v)<option value="{{ $k }}" {{ $quote->status === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
    </div>
  </div>

</form>
@unless($quote->isDraftDoc())
<div style="margin-top:14px">@include('quotes._steps')</div>
@endunless
@foreach($quote->lines as $l)<form method="post" action="/presupuestos/{{ $quote->id }}/lineas/{{ $l->id }}/quitar" id="rm-{{ $l->id }}">@csrf</form>@endforeach

<form method="post" action="/presupuestos/{{ $quote->id }}/lineas" id="addf">@csrf</form>
<script src="/js/art-suggest.js?v=1"></script>
<script>
(function(){
  var qf=document.getElementById('qf'),addf=document.getElementById('addf'),a=document.getElementById('artIn'),K='qfocus:{{ $quote->id }}';
  function go(form,next){try{sessionStorage.setItem(K,next||'');}catch(e){}form.requestSubmit();}
  // Dónde seguir escribiendo al volver: cantidad de la línea recién añadida, la casilla a la que ibas, o la referencia nueva.
  var want='';try{want=sessionStorage.getItem(K)||'';sessionStorage.removeItem(K);}catch(e){}
  var nl=@json(session('new_line')),el=null;
  if(nl)el=document.querySelector('[name="lines['+nl+'][qty]"]');
  if(!el&&want)el=document.querySelector('[name="'+want.replace(/"/g,'')+'"]');
  var nr=document.querySelector('.newart-cost[data-focus]');if(nr){var d=nr.form.querySelector('[name=description]');el=(d&&!d.value)?d:nr;}
  if(!el)el=a;
  if(el){el.focus();if(el.select)el.select();}
  if(a){
    // Al confirmar la referencia (Intro, Tab o clic fuera) se añade sola con la tarifa del cliente.
    // Al elegir una sugerencia se añade directamente; si se sale del campo con la lista abierta, se añade lo tecleado.
    window.artSuggest(a,{tariff:{{ (int) ($c->tariff ?? 1) }},onPick:function(){go(addf,'');}});
    a.addEventListener('change',function(){if(a.value.trim())go(addf,'');});
    a.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();if(a.value.trim())go(addf,'');}});
  }
  // Líneas sin referencia: sugerencias; al elegir una se guarda
  document.querySelectorAll('.ql-art').forEach(function(i){window.artSuggest(i,{tariff:{{ (int) ($c->tariff ?? 1) }},onPick:function(){go(qf,'');}});});
  if(qf){
    // Cualquier cambio en una casilla se guarda solo; el cursor sigue en la casilla a la que hayas pasado.
    qf.addEventListener('change',function(e){if(!e.target.name||e.target.form!==qf)return;setTimeout(function(){var n=document.activeElement;go(qf,(n&&n.name&&n.form===qf)?n.name:'');},0);});
    qf.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.tagName==='INPUT'&&e.target.form===qf){e.preventDefault();var t=e.target;if(t.value!==t.defaultValue){go(qf,'');}else if(a){a.focus();}}});
  }
  // Cambiar de cliente: buscador por nombre, NIF, teléfono o número
  (function(){var q=document.getElementById('chcli-q');if(!q)return;var list=document.getElementById('chcli-list'),id=document.getElementById('chcli-id'),ok=document.getElementById('chcli-ok'),t,seq=0;
    function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    q.addEventListener('input',function(){id.value='';ok.disabled=true;clearTimeout(t);var v=q.value.trim();if(!v){list.innerHTML='';return;}
      t=setTimeout(function(){var my=++seq;fetch('/clientes-sugerir?q='+encodeURIComponent(v),{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(j){if(my!==seq)return;
        list.innerHTML=j.length?j.map(function(c){return '<div class="chcli-i" data-id="'+c.id+'" data-name="'+esc(c.name)+'" style="padding:7px 8px;border-bottom:1px solid #eee;cursor:pointer"><b>'+esc(c.name)+'</b> <span class="muted">nº '+c.cod+'</span><div class="muted">'+esc(c.nif)+' · '+esc(c.city)+' · tarifa '+c.tariff+'</div></div>';}).join(''):'<span class="muted">Ningún cliente con eso.</span>';}).catch(function(){});},180);});
    list.addEventListener('click',function(e){var el=e.target.closest('.chcli-i');if(!el)return;id.value=el.dataset.id;q.value=el.dataset.name;list.innerHTML='';ok.disabled=false;});
    q.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();var f=list.querySelector('.chcli-i');if(f&&!id.value)f.click();}});
    q.closest('details').addEventListener('toggle',function(){if(this.open)q.focus();});
  })();
  // Estado del guardado en FactuSOL
  var st=document.getElementById('fsst');
  if(st&&st.dataset.dirty==='1'){
    var n=0,tm=setInterval(function(){
      if(++n>60){clearInterval(tm);return;}
      fetch('/presupuestos/{{ $quote->id }}/estado',{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(j){
        if(j.dirty)return;
        clearInterval(tm);
        if(j.error){st.className='badge bad';st.textContent='⚠ Sin guardar en FactuSOL';st.title=j.error;}
        else{st.className='badge ok';st.textContent='✓ Guardado en FactuSOL';setTimeout(function(){st.hidden=true;},2500);}
      }).catch(function(){});
    },1500);
  }
})();
</script>

@endsection
