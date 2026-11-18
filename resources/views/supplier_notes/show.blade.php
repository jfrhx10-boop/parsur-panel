@extends('layout')
@section('title', $note->isInvoice() ? 'Factura de proveedor' : 'Albarán de proveedor')
@section('content')
@php
  $sales = auth()->user()->canSeeSales();
  $num = fn ($n) => $n === null ? '' : (rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0');
  $base = 0; foreach ($lines as $l) { $base += round((float) $l['qty'] * (float) ($l['cost'] ?? 0), 2); }
  $nowait = collect($plan)->filter(fn ($p) => empty($p['alloc']))->count();
@endphp
<div class="head">
  <div>
    <a class="small" href="/albaranes-proveedor">← Albaranes y facturas de proveedor</a>
    <h1>{{ $note->isInvoice() ? 'Factura' : 'Albarán' }} · {{ $note->supplier_name ?: 'Proveedor sin identificar' }} @if($note->their_no)· {{ $note->their_no }}@endif <span class="badge {{ $note->entry_code || $note->received_at ? 'ok' : 'warn' }}">{{ $note->stateLabel() }}</span></h1>
    <div class="muted small">Subido el {{ $note->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@if($note->user) por {{ $note->user->name }}@endif · {{ ['pdf' => 'leído del PDF', 'ocr' => 'leído de la imagen (revisa bien cantidades y referencias)', 'nada' => 'sin lectura automática'][$note->how] ?? '' }}</div>
  </div>
  <div class="qactions">
    <a class="btn" href="/albaranes-proveedor/{{ $note->id }}/archivo" target="_blank">📄 Ver el original</a>
    @if($note->entry_code && $sales)<form method="post" action="/albaranes-proveedor/{{ $note->id }}/entrada/quitar" onsubmit="return confirm('Se borra de FactuSOL: {{ $note->docLabel() }} {{ $note->entry_code }}. ¿Seguir?')">@csrf<button class="btn ghost" type="submit">Deshacer {{ mb_strtolower($note->docLabel()) }} {{ $note->entry_code }}</button></form>@endif
    <form method="post" action="/albaranes-proveedor/{{ $note->id }}/borrar" onsubmit="return confirm('¿Borrar este documento subido? (lo ya creado en FactuSOL no se toca)')">@csrf<button class="btn ghost" type="submit" style="color:var(--bad)">🗑 Borrar lo subido</button></form>
  </div>
</div>
@if($dup)<div class="alerts"><div class="al al-aviso"><span class="al-k">Ojo</span><span class="al-t">En FactuSOL ya hay {{ mb_strtolower($dup->typeLabel()) }} {{ $dup->year }}/{{ $dup->code }} de este proveedor con el nº {{ $note->their_no }} ({{ $dup->date?->format('d/m/Y') }}, {{ eur($dup->total) }}). Puede que este documento ya esté metido.</span></div></div>@endif

<div class="sn-grid">
  <div class="card sn-doc">
    @if($note->isImage())<a href="/albaranes-proveedor/{{ $note->id }}/archivo" target="_blank"><img src="/albaranes-proveedor/{{ $note->id }}/archivo" alt="Albarán" style="max-width:100%;display:block"></a>
    @else<iframe src="/albaranes-proveedor/{{ $note->id }}/archivo#view=FitH" title="Albarán" style="width:100%;height:78vh;border:0"></iframe>@endif
  </div>

  <form method="post" action="/albaranes-proveedor/{{ $note->id }}" class="sn-form" id="snf">
    @csrf
    <div class="card">
      <div class="row">
        <div style="flex:2 1 220px"><label>Proveedor</label>
          <select name="codpro"><option value="">— elegir —</option>@foreach($suppliers as $s)<option value="{{ $s->codpro }}" {{ (int) $note->codpro === (int) $s->codpro ? 'selected' : '' }}>{{ $s->displayName() }} ({{ $s->codpro }})</option>@endforeach</select></div>
        <div style="flex:1 1 130px"><label>{{ $note->isInvoice() ? 'Nº de su factura' : 'Nº de su albarán' }}</label><input type="text" name="their_no" value="{{ $note->their_no }}" maxlength="20"></div>
        <div style="flex:0 1 150px"><label>Fecha</label><input type="date" name="date" value="{{ $note->date?->toDateString() }}"></div>
      </div>
    </div>
    <div class="card mt">
      <h2>Líneas leídas</h2>
      <div class="scroll"><table class="tbl" id="snt">
        <thead><tr><th>Artículo</th><th class="num" style="width:74px">Cant.</th>@if($sales)<th class="num" style="width:118px">Coste ud.</th>@endif<th style="width:24%">Pedido al proveedor</th><th style="width:26%">Clientes que lo esperan</th><th style="width:26px"></th></tr></thead>
        <tbody>
        @foreach($lines as $i => $l)
          @php $p = $plan[$i] ?? null; @endphp
          <tr class="{{ ! empty($l['doubt']) ? 'sn-doubt' : '' }}">
            <td class="sn-art">
              <div class="sn-refrow"><input type="text" class="sn-ref" name="lines[{{ $i }}][ref]" value="{{ $l['ref'] }}" placeholder="Referencia" aria-label="Referencia">
              @if($l['art'])<a class="sn-ok" href="/articulos/{{ rawurlencode($l['art']) }}" target="_blank" title="Artículo de FactuSOL">✓ {{ $l['art'] !== $l['ref'] ? $l['art'] : 'en catálogo' }}</a>@else<span class="badge bad" title="Al validar se da de alta si tiene descripción y coste">nuevo</span>@endif</div>
              <input type="text" class="sn-desc" name="lines[{{ $i }}][desc]" value="{{ $l['desc'] }}" placeholder="Descripción" aria-label="Descripción">
              @if(! empty($l['doubt']))<div class="small" style="color:var(--bad)">Lectura dudosa: compruébala con el original</div>@endif</td>
            <td><input type="number" class="num" name="lines[{{ $i }}][qty]" value="{{ $num($l['qty']) }}" step="any" min="0" inputmode="decimal"></td>
            @if($sales)<td><input type="text" class="num" name="lines[{{ $i }}][cost]" value="{{ $l['cost'] === null ? '' : number_format((float) $l['cost'], 2, ',', '') }}" inputmode="decimal">
              @if($l['cat_cost'] !== null && $l['cost'] !== null && abs((float) $l['cost'] - (float) $l['cat_cost']) > 0.005)<div class="small muted" title="Coste que tiene ahora el artículo en FactuSOL">ficha: {{ eur($l['cat_cost']) }} @if((float) $l['cat_cost'] > 0)<b style="color:{{ $l['cost'] > $l['cat_cost'] ? 'var(--bad)' : 'var(--ok,#1a7f37)' }}">{{ $l['cost'] > $l['cat_cost'] ? '▲' : '▼' }} {{ number_format(abs($l['cost'] - $l['cat_cost']) / $l['cat_cost'] * 100, 1, ',', '') }} %</b>@endif</div>@elseif($l['cat_cost'] !== null && $l['cost'] !== null)<div class="small sn-good" title="Igual que el coste de la ficha">= ficha</div>@endif
              @if($l['cost'] !== null && (float) $l['qty'] != 1.0)<div class="small muted">total {{ eur(round((float) $l['qty'] * (float) $l['cost'], 2)) }}</div>@endif</td>@endif
            <td class="small">
              @php $o = $po['items'][$i] ?? null; @endphp
              @if($o && $o['alloc'])
                @foreach($o['alloc'] as $a)<div><a href="/documentos/pedidoprov?q={{ $a['year'] }}/{{ $a['code'] }}" target="_blank">pedido {{ $a['code'] }}</a>: pedidas {{ \App\Services\OrderProgress::fmt($a['ordered']) }}, faltaban {{ \App\Services\OrderProgress::fmt($a['pending']) }} @if($a['qty'] >= $a['pending'] - 0.0001)<b style="color:var(--ok,#1a7f37)">✓ completo</b>@else<b style="color:var(--bad)">llegan {{ \App\Services\OrderProgress::fmt($a['qty']) }}</b>@endif</div>@endforeach
                @if($o['rest'] > 0.0001)<div style="color:var(--bad)">{{ \App\Services\OrderProgress::fmt($o['rest']) }} de más (no pedidas)</div>@endif
              @elseif($o && $o['ordered'])<span class="muted">Pedido ya recibido o de otro proveedor</span>
              @else<span class="muted">No consta en ningún pedido abierto</span>@endif
            </td>
            <td class="small">
              @if($p && $p['alloc'])
                @foreach($p['alloc'] as $a)
                  @php $sl = $a['doc']->lines->firstWhere('pos', $a['pos']); $sp = $sl ? (float) $sl->price * (1 - (float) $sl->discount / 100) : 0; $c = (float) ($l['cost'] ?? 0); $mg = $c > 0 && $sp > 0 ? ($sp / $c - 1) * 100 : null; @endphp
                  <div class="sn-cli"><b>{{ \App\Services\OrderProgress::fmt($a['qty']) }}</b> → <a href="/documentos/pedido?q={{ $a['doc']->year }}/{{ $a['doc']->code }}" target="_blank">pedido {{ $a['doc']->code }}</a> · {{ $a['customer'] }}
                    @if($sales && $sp > 0)<div class="muted">vendido a {{ eur($sp) }} @if($mg !== null)· <b class="{{ $mg < 15 ? 'sn-bad' : 'sn-good' }}" title="Margen sobre el coste de este documento">margen {{ number_format($mg, 0, ',', '.') }} %</b>@endif</div>@endif
                    @if(isset($direct[$a['doc']->id]))<div><span class="badge warn" title="La dirección de entrega del documento es la de este cliente">🚚 envío directo al cliente</span></div>@endif
                  </div>
                @endforeach
                @if($p['rest'] > 0)<div class="muted">{{ \App\Services\OrderProgress::fmt($p['rest']) }} a stock</div>@endif
              @else<span class="muted">Ningún pedido lo espera · a stock</span>@endif
            </td>
            <td class="num"><button type="button" class="linkbtn sn-x" title="Quitar línea" style="color:var(--bad);font-size:18px">×</button></td>
          </tr>
        @endforeach
        </tbody>
        @if($sales)
          @php $db = $docTotals['base'] ?? null; $okb = $db !== null && abs($db - $base) < 0.02; @endphp
          <tfoot><tr><td colspan="2" class="num">Suma de las líneas (sin IVA)</td><td class="num"><strong>{{ eur($base) }}</strong></td><td colspan="3" class="small">
            @if($db === null)<span class="muted">No encuentro la base en el documento: compárala con el original.</span>
            @elseif($okb)<span class="sn-good">✓ Cuadra con el documento ({{ eur($db) }})</span>@if($docTotals['total'])<span class="muted"> · total con IVA {{ eur($docTotals['total']) }}</span>@endif
            @else<span class="sn-bad">✗ El documento dice {{ eur($db) }} (diferencia {{ eur($db - $base) }}): falta o sobra alguna línea o un porte.</span>@endif
          </td></tr></tfoot>
        @endif
      </table></div>
      @if($po['missing'])<div class="small" style="margin-top:10px"><b>Del pedido al proveedor sigue faltando:</b> @foreach($po['missing'] as $m)<span style="white-space:nowrap"><code class="art">{{ $m['art'] }}</code> ×{{ \App\Services\OrderProgress::fmt($m['left']) }} <span class="muted">(pedido {{ $m['code'] }})</span></span>@if(! $loop->last) · @endif @endforeach</div>@endif
      <div class="row" style="margin-top:10px;gap:8px"><button class="btn" type="button" id="snadd">＋ Añadir línea</button><button class="btn" type="submit" name="do" value="guardar">Guardar y recalcular</button></div>
    </div>

    <div class="card mt">
      <h2>Validar</h2>
      <label style="display:block;margin:6px 0"><input type="checkbox" name="recibir" value="1" {{ $note->received_at ? 'disabled' : 'checked' }}> Apuntar la llegada en los pedidos de clientes que lo esperan @if($note->received_at)<span class="badge ok">hecho el {{ $note->received_at->timezone('Europe/Madrid')->format('d/m H:i') }}</span>@endif</label>
      @if($sales)
      <label style="display:block;margin:6px 0"><input type="checkbox" name="entrada" value="1" {{ $note->entry_code ? 'disabled' : 'checked' }}> {{ $note->isInvoice() ? 'Crear la factura recibida en FactuSOL (hace también de entrada)' : 'Crear la entrada en FactuSOL' }} con estas líneas y costes @if($note->entry_code)<span class="badge ok">{{ mb_strtolower($note->docLabel()) }} {{ $note->entry_code }}</span>@endif</label>
      @if(collect($po['items'])->contains(fn ($x) => ! empty($x['alloc'])))<label style="display:block;margin:6px 0 6px 22px"><input type="checkbox" name="descontar" value="1" {{ $note->entry_code ? 'disabled' : 'checked' }}> y descontar lo recibido de los pedidos hechos a este proveedor (en FactuSOL)</label>@endif
      @php $chg = collect($lines)->filter(fn ($x) => $x['art'] && $x['cat_cost'] !== null && $x['cost'] !== null && (float) $x['cost'] > 0 && abs((float) $x['cost'] - (float) $x['cat_cost']) > 0.005); @endphp
      @if($chg->isNotEmpty())<label style="display:block;margin:6px 0"><input type="checkbox" name="coste" value="1" checked> Actualizar el coste en la ficha del artículo (solo el coste; las tarifas no se tocan): @foreach($chg as $x)<span style="white-space:nowrap"><code class="art">{{ $x['art'] }}</code> {{ eur($x['cat_cost']) }} → <b>{{ eur($x['cost']) }}</b></span>@if(! $loop->last), @endif @endforeach</label>@endif
      @if($dup && ! $note->entry_code)<label style="display:block;margin:6px 0 6px 22px" class="small"><input type="checkbox" name="forzar" value="1"> crear aunque esté repetido</label>@endif
      <label style="display:block;margin:6px 0"><input type="checkbox" name="albaranes" value="1" {{ $note->received_at ? 'disabled' : ($direct ? 'checked' : '') }}> Crear además los albaranes de cliente en FactuSOL con lo asignado @if($direct && ! $note->received_at)<span class="small" style="color:var(--warn)">— marcado porque va directo al cliente ({{ implode(', ', $direct) }})</span>@endif</label>
      @endif
      <div class="row" style="margin-top:10px"><button class="btn y" type="submit" name="do" value="validar" onclick="return confirm('Se valida con las líneas de la pantalla. ¿Seguir?')" @disabled($note->received_at && ($note->entry_code || ! $sales))>✓ Validar</button></div>
      <div class="small muted" style="margin-top:8px">Se guarda en FactuSOL con el nº de documento del proveedor. El stock de los artículos se suma solo.@if($note->isInvoice()) La factura recibida se crea sin vencimientos: ponlos en FactuSOL si los necesitas.@endif</div>
    </div>
    @if($note->text)<details class="card mt"><summary class="small" style="cursor:pointer">Texto leído del documento</summary><pre class="small" style="white-space:pre-wrap;overflow:auto;max-height:300px">{{ $note->text }}</pre></details>@endif
  </form>
</div>
<script>
(function(){
  var tb=document.querySelector('#snt tbody'),n={{ count($lines) }},sales={{ $sales ? 'true' : 'false' }};
  tb.addEventListener('click',function(e){var x=e.target.closest('.sn-x');if(x)x.closest('tr').remove();});
  document.getElementById('snadd').addEventListener('click',function(){
    var i=n++,tr=document.createElement('tr');
    tr.innerHTML='<td class="sn-art"><div class="sn-refrow"><input type="text" class="sn-ref" name="lines['+i+'][ref]" placeholder="Referencia"></div><input type="text" class="sn-desc" name="lines['+i+'][desc]" placeholder="Descripción"></td><td><input type="number" class="num" name="lines['+i+'][qty]" value="1" step="any" min="0"></td>'+(sales?'<td><input type="text" class="num" name="lines['+i+'][cost]" inputmode="decimal"></td>':'')+'<td class="small muted" colspan="2">se calcula al guardar</td><td class="num"><button type="button" class="linkbtn sn-x" style="color:var(--bad);font-size:18px">×</button></td>';
    tb.appendChild(tr);tr.querySelector('input').focus();
  });
})();
</script>
@endsection
