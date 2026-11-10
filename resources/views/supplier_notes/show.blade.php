@extends('layout')
@section('title', 'Albarán de proveedor')
@section('content')
@php
  $sales = auth()->user()->canSeeSales();
  $num = fn ($n) => $n === null ? '' : (rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0');
  $base = 0; foreach ($lines as $l) { $base += round((float) $l['qty'] * (float) ($l['cost'] ?? 0), 2); }
  $nowait = collect($plan)->filter(fn ($p) => empty($p['alloc']))->count();
@endphp
<div class="head">
  <div>
    <a class="small" href="/albaranes-proveedor">← Albaranes de proveedor</a>
    <h1>{{ $note->supplier_name ?: 'Proveedor sin identificar' }} @if($note->their_no)· {{ $note->their_no }}@endif <span class="badge {{ $note->entry_code || $note->received_at ? 'ok' : 'warn' }}">{{ $note->stateLabel() }}</span></h1>
    <div class="muted small">Subido el {{ $note->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@if($note->user) por {{ $note->user->name }}@endif · {{ ['pdf' => 'leído del PDF', 'ocr' => 'leído de la imagen (revisa bien cantidades y referencias)', 'nada' => 'sin lectura automática'][$note->how] ?? '' }}</div>
  </div>
  <div class="qactions">
    <a class="btn" href="/albaranes-proveedor/{{ $note->id }}/archivo" target="_blank">📄 Ver el original</a>
    <form method="post" action="/albaranes-proveedor/{{ $note->id }}/borrar" onsubmit="return confirm('¿Borrar este albarán subido? (lo ya creado en FactuSOL no se toca)')">@csrf<button class="btn ghost" type="submit">Borrar</button></form>
  </div>
</div>
@if($errors->any())<div class="alerts">@foreach($errors->all() as $e)<div class="al al-aviso"><span class="al-k">Aviso</span><span class="al-t">{{ $e }}</span></div>@endforeach</div>@endif
@if($dup)<div class="alerts"><div class="al al-aviso"><span class="al-k">Ojo</span><span class="al-t">En FactuSOL ya hay {{ mb_strtolower($dup->typeLabel()) }} {{ $dup->year }}/{{ $dup->code }} de este proveedor con el nº {{ $note->their_no }} ({{ $dup->date?->format('d/m/Y') }}, {{ eur($dup->total) }}). Puede que este albarán ya esté metido.</span></div></div>@endif

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
        <div style="flex:1 1 130px"><label>Nº de su albarán</label><input type="text" name="their_no" value="{{ $note->their_no }}" maxlength="20"></div>
        <div style="flex:0 1 150px"><label>Fecha</label><input type="date" name="date" value="{{ $note->date?->toDateString() }}"></div>
      </div>
    </div>
    <div class="card mt">
      <h2>Líneas leídas</h2>
      <div class="scroll"><table class="tbl" id="snt">
        <thead><tr><th style="width:150px">Referencia</th><th>Descripción</th><th class="num" style="width:80px">Cant.</th>@if($sales)<th class="num" style="width:100px">Coste</th>@endif<th>Pedidos que lo esperan</th><th style="width:28px"></th></tr></thead>
        <tbody>
        @foreach($lines as $i => $l)
          @php $p = $plan[$i] ?? null; @endphp
          <tr class="{{ ! empty($l['doubt']) ? 'sn-doubt' : '' }}">
            <td><input type="text" name="lines[{{ $i }}][ref]" value="{{ $l['ref'] }}">
              @if($l['art'])<div class="small"><a href="/articulos/{{ rawurlencode($l['art']) }}" target="_blank"><code class="art">{{ $l['art'] }}</code></a></div>@else<div class="small"><span class="badge bad">no está en el catálogo</span></div>@endif</td>
            <td><input type="text" name="lines[{{ $i }}][desc]" value="{{ $l['desc'] }}">@if(! empty($l['doubt']))<div class="small" style="color:var(--bad)">Lectura dudosa: compruébala con el original</div>@endif</td>
            <td><input type="number" class="num" name="lines[{{ $i }}][qty]" value="{{ $num($l['qty']) }}" step="any" min="0" inputmode="decimal"></td>
            @if($sales)<td><input type="text" class="num" name="lines[{{ $i }}][cost]" value="{{ $l['cost'] === null ? '' : number_format((float) $l['cost'], 2, ',', '') }}" inputmode="decimal">
              @if($l['cat_cost'] !== null && $l['cost'] !== null && abs((float) $l['cost'] - (float) $l['cat_cost']) > 0.005)<div class="small muted" title="Coste que tiene ahora el artículo en FactuSOL">ficha: {{ eur($l['cat_cost']) }} @if((float) $l['cat_cost'] > 0)<b style="color:{{ $l['cost'] > $l['cat_cost'] ? 'var(--bad)' : 'var(--ok,#1a7f37)' }}">{{ $l['cost'] > $l['cat_cost'] ? '▲' : '▼' }} {{ number_format(abs($l['cost'] - $l['cat_cost']) / $l['cat_cost'] * 100, 1, ',', '') }} %</b>@endif</div>@endif</td>@endif
            <td class="small">
              @if($p && $p['alloc'])
                @foreach($p['alloc'] as $a)<div><b>{{ \App\Services\OrderProgress::fmt($a['qty']) }}</b> → <a href="/documentos/pedido?q={{ $a['doc']->year }}/{{ $a['doc']->code }}" target="_blank">pedido {{ $a['doc']->code }}</a> · {{ $a['customer'] }}</div>@endforeach
                @if($p['rest'] > 0)<div class="muted">{{ \App\Services\OrderProgress::fmt($p['rest']) }} a stock</div>@endif
              @else<span class="muted">Ningún pedido lo espera · a stock</span>@endif
            </td>
            <td class="num"><button type="button" class="linkbtn sn-x" title="Quitar línea" style="color:var(--bad);font-size:18px">×</button></td>
          </tr>
        @endforeach
        </tbody>
        @if($sales)<tfoot><tr><td colspan="3" class="num">Base (sin IVA)</td><td class="num"><strong>{{ eur($base) }}</strong></td><td colspan="2" class="small muted">Compárala con el total del albarán</td></tr></tfoot>@endif
      </table></div>
      <div class="row" style="margin-top:10px;gap:8px"><button class="btn" type="button" id="snadd">＋ Añadir línea</button><button class="btn" type="submit" name="do" value="guardar">Guardar y recalcular</button></div>
    </div>

    <div class="card mt">
      <h2>Validar</h2>
      <label style="display:block;margin:6px 0"><input type="checkbox" name="recibir" value="1" {{ $note->received_at ? 'disabled' : 'checked' }}> Apuntar la llegada en los pedidos de clientes que lo esperan @if($note->received_at)<span class="badge ok">hecho el {{ $note->received_at->timezone('Europe/Madrid')->format('d/m H:i') }}</span>@endif</label>
      @if($sales)
      <label style="display:block;margin:6px 0"><input type="checkbox" name="entrada" value="1" {{ $note->entry_code ? 'disabled' : 'checked' }}> Crear la entrada en FactuSOL con estas líneas y costes @if($note->entry_code)<span class="badge ok">entrada {{ $note->entry_code }}</span>@endif</label>
      @if($dup && ! $note->entry_code)<label style="display:block;margin:6px 0 6px 22px" class="small"><input type="checkbox" name="forzar" value="1"> crear aunque esté repetido</label>@endif
      <label style="display:block;margin:6px 0"><input type="checkbox" name="albaranes" value="1" {{ $note->received_at ? 'disabled' : '' }}> Crear además los albaranes de cliente en FactuSOL con lo asignado</label>
      @endif
      <div class="row" style="margin-top:10px"><button class="btn y" type="submit" name="do" value="validar" onclick="return confirm('Se valida este albarán con las líneas de la pantalla. ¿Seguir?')" @disabled($note->received_at && ($note->entry_code || ! $sales))>✓ Validar albarán</button></div>
      <div class="small muted" style="margin-top:8px">La entrada se guarda en FactuSOL con el nº de albarán del proveedor. FactuSOL no mueve el stock cuando la entrada se crea desde aquí.</div>
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
    tr.innerHTML='<td><input type="text" name="lines['+i+'][ref]" placeholder="Referencia"></td><td><input type="text" name="lines['+i+'][desc]"></td><td><input type="number" class="num" name="lines['+i+'][qty]" value="1" step="any" min="0"></td>'+(sales?'<td><input type="text" class="num" name="lines['+i+'][cost]" inputmode="decimal"></td>':'')+'<td class="small muted">se calcula al guardar</td><td class="num"><button type="button" class="linkbtn sn-x" style="color:var(--bad);font-size:18px">×</button></td>';
    tb.appendChild(tr);tr.querySelector('input').focus();
  });
})();
</script>
@endsection
