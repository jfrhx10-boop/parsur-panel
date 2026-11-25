@php
  // $d: FsDoc (pedido, con lines) · $p: resultado de OrderProgress::of · $open: bool · $party/$partyUrl opcionales
  $open = $open ?? false; $hideParty = $hideParty ?? true; $party = $party ?? null; $partyUrl = $partyUrl ?? null;
  $f = fn ($n) => \App\Services\OrderProgress::fmt((float) $n);
  $ps = $p['pct_served']; $pr = $p['pct_received'];
  // Se puede corregir también un pedido ya servido (por si hay errores); el anulado no.
  $canEdit = in_array((int) $d->status, [0, 1, 2], true) && (bool) auth()->user()?->canSeeSales();
  $canCli = in_array((int) $d->status, [0, 1], true) && (bool) auth()->user()?->canSeeSales();
  // Existencias actuales en FactuSOL de cada artículo del pedido (para saber qué se puede servir ya del almacén)
  $stk = \App\Models\Article::whereIn('codart', collect($p['lines'])->map(fn ($r) => $r['line']->art)->filter()->unique()->values())->pluck('stock', 'codart');
@endphp
<details class="doc t-pedido op op-{{ $p['state'] }} st-{{ ['servido' => 'ok', 'pendiente' => 'bad'][$p['state']] ?? 'warn' }}"{{ $open ? ' open' : '' }} data-print="/documentos/{{ $d->type }}/{{ $d->year }}/{{ $d->code }}/pdf">
  <summary>
    <span class="doc-ic">{!! \App\Services\Icons::svg('doc-pedido') !!}</span>
    <span class="doc-main">
      <span class="doc-n">Pedido <b>{{ $d->number() }}</b> <span class="doc-d">{{ $d->date?->format('d/m/Y') }}</span>@if($d->ref) <span class="doc-chip" title="Máquina / referencia">{{ $d->ref }}</span>@endif</span>
      @if(!$hideParty)<span class="doc-p">@if($party){{ $party->displayName() }} <a class="doc-pl" href="{{ $partyUrl }}" onclick="event.stopPropagation()" title="Abrir la ficha del cliente">ficha ↗</a>@else{{ $d->customer_name }}@endif</span>@endif
      <span class="op-bar" title="Verde: servido · Ámbar: en almacén · Rojo: pendiente de recibir"><i class="op-s" style="width:{{ $ps }}%"></i><i class="op-r" style="width:{{ $pr }}%"></i></span>
      <span class="op-sum"><b class="op-c-servido">{{ $f($p['served']) }}</b> servidas · <b class="op-c-almacen">{{ $f($p['received']) }}</b> en almacén · <b class="op-c-pendiente">{{ $f($p['pending']) }}</b> pendientes de recibir <span class="muted">de {{ $f($p['qty']) }}</span></span>
    </span>
    <span class="doc-r">
      <span class="doc-t">{{ eur($d->total) }}</span>
      <span class="badge op-b-{{ $p['state'] }}">{{ $p['label'] }}</span>
    </span>
  </summary>
  <div style="padding:4px 14px 6px">@if(auth()->user()?->canSeeSales())@include('partials_docmachine', ['doc' => $d, 'action' => '/pedidos-factusol/'.$d->year.'/'.$d->code.'/maquina', 'customer' => $d->codcli ? \App\Models\Customer::where('codcli', $d->codcli)->first() : null])@endif</div>
  <table class="tbl doc-lines op-lines">
    <thead><tr><th></th><th>Artículo</th><th>Descripción</th><th class="num">Pedido</th><th class="num">Servido</th><th class="num">Almacén</th><th class="num">Pendiente</th><th>Recepción</th></tr></thead>
    <tbody>
    @forelse($p['lines'] as $row)
      @php $l = $row['line']; @endphp
      <tr class="op-l-{{ $row['state'] }}">
        <td><span class="op-dot op-dot-{{ $row['state'] }}" title="{{ ['servido' => 'Servido', 'almacen' => 'En almacén, pendiente de servir', 'parcial' => 'Parcial', 'pendiente' => 'Pendiente de recibir'][$row['state']] }}"></span></td>
        <td>@if($l->art)<a href="/articulos/{{ rawurlencode($l->art) }}"><code class="art">{{ $l->art }}</code></a>
          @if($stk->has($l->art) && $row['state'] !== 'servido')@php $sv = (float) $stk[$l->art]; $need = max(0, (float) $row['qty'] - (float) $row['served']); @endphp
            <div class="op-stk {{ $sv <= 0 ? 'no' : ($sv >= $need ? 'si' : 'poco') }}" title="Existencias actuales en FactuSOL">{{ $sv <= 0 ? 'Sin stock' : ($sv >= $need ? 'En stock: '.$f($sv) : 'Solo '.$f($sv).' en stock') }}</div>
          @endif
        @endif</td>
        <td class="small">{{ $l->description }}@if($canEdit)<details class="op-ed"><summary title="Corregir descripción o precio de esta línea en FactuSOL">✏️</summary>
            <form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/lineas/{{ $l->pos }}/editar" class="op-ed-pop">@csrf
              <label>Descripción<input type="text" name="description" value="{{ $l->description }}" maxlength="100"></label>
              <label>Precio ud. (sin IVA)<input type="text" name="price" value="{{ number_format((float) $l->price, 2, ',', '') }}" inputmode="decimal" class="num"></label>
              <div class="op-ed-b"><button class="btn y sm" type="submit">Guardar en FactuSOL</button>@if((int) $d->status === 2 || $row['served'] > 0)<span class="small muted">El albarán ya hecho no cambia.</span>@endif</div>
            </form></details>@endif
          @if($row['receipt']?->note)<div class="small muted">{{ $row['receipt']->note }}</div>@endif @include('partials_costedit', ['art' => $l->art, 'price' => $l->price])</td>
        <td class="num">
          @if($canEdit)
            <form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/lineas/{{ $l->pos }}/cantidad" class="op-q">@csrf
              <input type="text" name="qty" inputmode="decimal" value="{{ $f($row['qty']) }}" data-o="{{ $f($row['qty']) }}" title="Cantidad pedida. Cámbiala y pulsa Intro o el botón para guardarla en FactuSOL{{ $row['served'] > 0 ? ' (mínimo '.$f($row['served']).', ya servidas)' : '' }}" oninput="this.form.classList.toggle('ch',this.value.trim()!==this.dataset.o)">
              <button class="btn sm y" type="submit" title="Guardar la cantidad en FactuSOL">Cambiar</button>
            </form>
          @else
            {{ $f($row['qty']) }}
          @endif
        </td>
        <td class="num op-c-servido">{{ $row['served'] > 0 ? $f($row['served']) : '–' }}</td>
        <td class="num op-c-almacen">{{ $row['received'] > 0 ? $f($row['received']) : '–' }}</td>
        <td class="num op-c-pendiente">{{ $row['missing'] > 0 ? $f($row['missing']) : '–' }}</td>
        <td>
          @if($row['pending'] > 0 && $d->status !== 4)
            <form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/lineas/{{ $l->pos }}/recibido" class="op-f">
              @csrf
              <input type="text" name="recibido" inputmode="decimal" value="{{ $row['received'] > 0 ? $f($row['received']) : '' }}" placeholder="0" title="Unidades recibidas en almacén (de {{ $f($row['pending']) }} pendientes)">
              <button class="btn sm ghost" type="submit">Guardar</button>
              @if($row['missing'] > 0)<button class="btn sm" type="submit" name="todo" value="1" title="Marcar como recibidas las {{ $f($row['pending']) }} pendientes">Completa</button>@endif
            </form>
            @if($row['received'] > 0)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/lineas/{{ $l->pos }}/quitar" class="op-f">@csrf<button class="linkbtn small" type="submit">quitar</button></form>@endif
          @elseif($row['state'] === 'servido')
            <span class="small op-c-servido">✓ servido</span>
          @endif
          @if($canEdit && $row['served'] <= 0 && count($p['lines']) > 1)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/lineas/{{ $l->pos }}/borrar" class="op-f" onsubmit="return confirm('Se borra del pedido {{ $d->code }} en FactuSOL la línea {{ $l->art }} ({{ $f($row['qty']) }} uds). No se puede deshacer. ¿Seguir?')">@csrf<button class="linkbtn small op-del" type="submit" title="Borrar esta línea del pedido en FactuSOL">Borrar línea</button></form>@endif
        </td>
      </tr>
    @empty
      <tr><td colspan="8" class="muted small">Sin líneas (todavía no sincronizadas).</td></tr>
    @endforelse
    </tbody>
    @if($d->notes)<tfoot><tr><td colspan="8" class="small muted">{{ $d->notes }}</td></tr></tfoot>@endif
  </table>
  @if($canEdit)
  <form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/lineas" class="op-add" data-tariff="{{ (int) (($party?->tariff ?? null) ?: (\App\Models\Customer::where('codcli', $d->codcli)->value('tariff') ?: 1)) }}">@csrf
    <b class="small">Añadir línea</b>
    <input type="text" name="art" placeholder="Referencia" autocomplete="off" style="width:150px" title="Referencia del artículo en FactuSOL">
    <input type="text" name="description" placeholder="Descripción (si no está en el catálogo)" style="flex:1 1 220px;min-width:160px">
    <input type="text" name="qty" value="1" inputmode="decimal" class="num" style="width:70px" title="Cantidad" required>
    <input type="text" name="price" placeholder="Precio" inputmode="decimal" class="num" style="width:90px" title="Déjalo vacío para usar el precio de la tarifa del cliente">
    <button class="btn y sm" type="submit">＋ Añadir al pedido</button>
    <span class="small muted op-add-i">Escribe la referencia o parte de la descripción: te salen los artículos con su stock y su precio. Sin precio, se usa la tarifa del cliente.</span>
  </form>
  @if(empty($GLOBALS['psOpAddJs']))
  @php $GLOBALS['psOpAddJs'] = true; @endphp
  <script src="/js/art-suggest.js?v=1"></script>
  <script>
  document.addEventListener('DOMContentLoaded',function(){
    function eur(n){return Number(n).toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';}
    document.querySelectorAll('form.op-add').forEach(function(f){
      var a=f.querySelector('[name=art]'),de=f.querySelector('[name=description]'),q=f.querySelector('[name=qty]'),pr=f.querySelector('[name=price]'),info=f.querySelector('.op-add-i');
      if(!a||!window.artSuggest)return;
      window.artSuggest(a,{tariff:parseInt(f.dataset.tariff||'1',10)||1,onPick:function(it){
        de.value='';de.placeholder=it.desc||de.placeholder;pr.value='';pr.placeholder=eur(it.price);
        var st=Number(it.stock)||0;
        info.innerHTML='<b>'+it.art.replace(/[&<>]/g,'')+'</b> · '+(st>0?'<span style="color:#1c7a43;font-weight:700">hay '+st.toLocaleString('es-ES')+' en stock</span>':'<span style="color:#a32020;font-weight:700">sin stock</span>')+' · tarifa del cliente '+eur(it.price)+(it.cost!==null&&it.cost!==undefined?' · coste '+eur(it.cost):'');
        q.focus();q.select();
      }});
      a.addEventListener('input',function(){if(!a.value.trim()){info.textContent='Escribe la referencia o parte de la descripción: te salen los artículos con su stock y su precio.';pr.placeholder='Precio';}});
    });
  });
  </script>
  @endif
  @endif
  <div class="doc-actions">
    @if($canCli)<details class="chcli2" style="position:relative"><summary class="btn ghost sm" style="list-style:none">👤 Cambiar cliente</summary>
      <form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/cliente" class="card" style="position:absolute;z-index:40;left:0;bottom:40px;width:min(420px,90vw);box-shadow:0 12px 34px rgba(0,0,0,.18)" onsubmit="return confirm('Se cambia el cliente del pedido {{ $d->code }} en FactuSOL. Los precios no cambian. ¿Seguir?')">@csrf
        <input type="hidden" name="customer_id" class="chc-id">
        <label>Cliente nuevo</label><input type="text" class="chc-q" placeholder="Nombre, NIF, teléfono o nº de cliente…" autocomplete="off">
        <div class="chc-list small" style="margin-top:6px;max-height:240px;overflow:auto"></div>
        <button class="btn y sm chc-ok" type="submit" disabled style="margin-top:8px">Cambiar cliente</button>
      </form></details>@endif
    <span class="small"><b>Siguiente:</b> @if($p['state'] === 'servido')servido; pendiente de facturar en FactuSOL @elseif($p['received'] > 0)generar el albarán con las unidades en almacén @else pendiente de recibir mercancía · <a href="/llegadas">registrar recepción</a>@endif</span>
    @if($p['pending'] > 0 && $d->status !== 4 && auth()->user()?->canSeeSales())<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/albaran-todo" onsubmit="return confirm('Se pasa el pedido {{ $d->code }} entero a albarán en FactuSOL (todo lo pendiente: {{ $f($p['pending'] + $p['received']) }} uds). ¿Seguir?')">@csrf<button class="btn y sm" type="submit" title="Da por recibido todo lo pendiente y crea el albarán en FactuSOL en un solo paso">📄 Pasar a albarán</button></form>@endif
    @if($p['pending'] > 0 && $d->status !== 4)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/recibido" onsubmit="return confirm('¿Marcar como recibidas todas las unidades pendientes de este pedido?')">@csrf<button class="btn sm" type="submit">Recepción completa</button></form>@endif
    @if($p['received'] > 0 && $d->status !== 4)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/albaran" onsubmit="return confirm('Se creará en FactuSOL un albarán con las unidades en almacén ({{ $f($p['received']) }} uds). ¿Seguir?')">@csrf<button class="btn y sm" type="submit" title="Genera el albarán en FactuSOL únicamente con las unidades recibidas">📄 Crear albarán de lo recibido ({{ $f($p['received']) }})</button></form>@endif
    @foreach(\App\Models\PanelAlbaran::where('year', $d->year)->where('pedido_code', $d->code)->whereNull('removed_at')->get() as $pa)@php $ad = \App\Models\FsDoc::where('type', 'albaran')->where('year', $d->year)->where('code', $pa->albaran_code)->first(); @endphp @continue($ad && (int) $ad->codcli !== (int) $d->codcli)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/albaran/{{ $pa->albaran_code }}/quitar" onsubmit="return confirm('Se borra el albarán {{ $pa->albaran_code }} de FactuSOL y el pedido vuelve a como estaba. ¿Seguir?')">@csrf<span class="small muted">Albarán {{ $pa->albaran_code }} (panel)</span> <button class="btn ghost sm" type="submit">Quitar de FactuSOL</button></form>@endforeach
    <a class="btn ghost sm" href="/documentos/pedido/{{ $d->year }}/{{ $d->code }}/pdf" target="_blank" rel="noopener">📄 PDF / Imprimir</a>
    <span class="small muted">«Servido» lo registra FactuSOL al emitir el albarán. «En almacén» se registra aquí al recibir la mercancía.</span>
  </div>
</details>
<script>
if(!window.__chc){window.__chc=1;
// Cambiar el cliente de un pedido: buscador por nombre, NIF, teléfono o número (uno por pedido)
document.addEventListener('input',function(e){var q=e.target;if(!q.classList||!q.classList.contains('chc-q'))return;var f=q.form,list=f.querySelector('.chc-list'),id=f.querySelector('.chc-id'),ok=f.querySelector('.chc-ok');id.value='';ok.disabled=true;clearTimeout(q._t);var v=q.value.trim();if(!v){list.innerHTML='';return;}
  q._t=setTimeout(function(){var my=q._s=(q._s||0)+1;fetch('/clientes-sugerir?q='+encodeURIComponent(v),{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(j){if(my!==q._s)return;var esc=function(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});};
    list.innerHTML=j.length?j.map(function(c){return '<div class="chc-i" data-id="'+c.id+'" data-name="'+esc(c.name)+'" style="padding:7px 8px;border-bottom:1px solid #eee;cursor:pointer"><b>'+esc(c.name)+'</b> <span class="muted">nº '+c.cod+'</span><div class="muted">'+esc(c.nif)+' · '+esc(c.city)+'</div></div>';}).join(''):'<span class="muted">Ningún cliente con eso.</span>';}).catch(function(){});},180);});
document.addEventListener('click',function(e){var el=e.target.closest&&e.target.closest('.chc-i');if(!el)return;var f=el.closest('form');f.querySelector('.chc-id').value=el.dataset.id;f.querySelector('.chc-q').value=el.dataset.name;f.querySelector('.chc-list').innerHTML='';f.querySelector('.chc-ok').disabled=false;});
document.addEventListener('toggle',function(e){var d=e.target;if(d.classList&&d.classList.contains('chcli2')&&d.open){var q=d.querySelector('.chc-q');if(q)q.focus();}},true);
}
</script>
