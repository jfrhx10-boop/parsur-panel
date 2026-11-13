@php
  // $d: FsDoc (pedido, con lines) · $p: resultado de OrderProgress::of · $open: bool · $party/$partyUrl opcionales
  $open = $open ?? false; $hideParty = $hideParty ?? true; $party = $party ?? null; $partyUrl = $partyUrl ?? null;
  $f = fn ($n) => \App\Services\OrderProgress::fmt((float) $n);
  $ps = $p['pct_served']; $pr = $p['pct_received'];
@endphp
<details class="doc t-pedido op op-{{ $p['state'] }} st-{{ ['servido' => 'ok', 'pendiente' => 'bad'][$p['state']] ?? 'warn' }}"{{ $open ? ' open' : '' }} data-print="/documentos/{{ $d->type }}/{{ $d->year }}/{{ $d->code }}/pdf">
  <summary>
    <span class="doc-ic">{!! \App\Services\Icons::svg('doc-pedido') !!}</span>
    <span class="doc-main">
      <span class="doc-n">Pedido <b>{{ $d->number() }}</b> <span class="doc-d">{{ $d->date?->format('d/m/Y') }}</span>@if($d->ref) <span class="doc-chip" title="Máquina / referencia">{{ $d->ref }}</span>@endif</span>
      @if(!$hideParty)<span class="doc-p">@if($party)<a href="{{ $partyUrl }}" onclick="event.stopPropagation()">{{ $party->displayName() }}</a>@else{{ $d->customer_name }}@endif</span>@endif
      <span class="op-bar" title="Verde: servido · Ámbar: en almacén · Rojo: pendiente de recibir"><i class="op-s" style="width:{{ $ps }}%"></i><i class="op-r" style="width:{{ $pr }}%"></i></span>
      <span class="op-sum"><b class="op-c-servido">{{ $f($p['served']) }}</b> servidas · <b class="op-c-almacen">{{ $f($p['received']) }}</b> en almacén · <b class="op-c-pendiente">{{ $f($p['pending']) }}</b> pendientes de recibir <span class="muted">de {{ $f($p['qty']) }}</span></span>
    </span>
    <span class="doc-r">
      <span class="doc-t">{{ eur($d->total) }}</span>
      <span class="badge op-b-{{ $p['state'] }}">{{ $p['label'] }}</span>
    </span>
  </summary>
  <table class="tbl doc-lines op-lines">
    <thead><tr><th></th><th>Artículo</th><th>Descripción</th><th class="num">Pedido</th><th class="num">Servido</th><th class="num">Almacén</th><th class="num">Pendiente</th><th>Recepción</th></tr></thead>
    <tbody>
    @forelse($p['lines'] as $row)
      @php $l = $row['line']; @endphp
      <tr class="op-l-{{ $row['state'] }}">
        <td><span class="op-dot op-dot-{{ $row['state'] }}" title="{{ ['servido' => 'Servido', 'almacen' => 'En almacén, pendiente de servir', 'parcial' => 'Parcial', 'pendiente' => 'Pendiente de recibir'][$row['state']] }}"></span></td>
        <td>@if($l->art)<a href="/articulos/{{ rawurlencode($l->art) }}"><code class="art">{{ $l->art }}</code></a>@endif</td>
        <td class="small">{{ $l->description }}@if($row['receipt']?->note)<div class="small muted">{{ $row['receipt']->note }}</div>@endif @include('partials_costedit', ['art' => $l->art, 'price' => $l->price])</td>
        <td class="num">{{ $f($row['qty']) }}</td>
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
        </td>
      </tr>
    @empty
      <tr><td colspan="8" class="muted small">Sin líneas (todavía no sincronizadas).</td></tr>
    @endforelse
    </tbody>
    @if($d->notes)<tfoot><tr><td colspan="8" class="small muted">{{ $d->notes }}</td></tr></tfoot>@endif
  </table>
  <div class="doc-actions">
    <span class="small"><b>Siguiente:</b> @if($p['state'] === 'servido')servido; pendiente de facturar en FactuSOL @elseif($p['received'] > 0)generar el albarán con las unidades en almacén @else pendiente de recibir mercancía · <a href="/llegadas">registrar recepción</a>@endif</span>
    @if($p['pending'] > 0 && $d->status !== 4)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/recibido" onsubmit="return confirm('¿Marcar como recibidas todas las unidades pendientes de este pedido?')">@csrf<button class="btn sm" type="submit">Recepción completa</button></form>@endif
    @if($p['received'] > 0 && $d->status !== 4)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/albaran" onsubmit="return confirm('Se creará en FactuSOL un albarán con las unidades en almacén ({{ $f($p['received']) }} uds). ¿Seguir?')">@csrf<button class="btn y sm" type="submit" title="Genera el albarán en FactuSOL únicamente con las unidades recibidas">📄 Crear albarán de lo recibido ({{ $f($p['received']) }})</button></form>@endif
    @foreach(\App\Models\PanelAlbaran::where('year', $d->year)->where('pedido_code', $d->code)->whereNull('removed_at')->get() as $pa)<form method="post" action="/pedidos-factusol/{{ $d->year }}/{{ $d->code }}/albaran/{{ $pa->albaran_code }}/quitar" onsubmit="return confirm('Se borra el albarán {{ $pa->albaran_code }} de FactuSOL y el pedido vuelve a como estaba. ¿Seguir?')">@csrf<span class="small muted">Albarán {{ $pa->albaran_code }} (panel)</span> <button class="btn ghost sm" type="submit">Quitar de FactuSOL</button></form>@endforeach
    <a class="btn ghost sm" href="/documentos/pedido/{{ $d->year }}/{{ $d->code }}/pdf" target="_blank" rel="noopener">📄 PDF / Imprimir</a>
    <span class="small muted">«Servido» lo registra FactuSOL al emitir el albarán. «En almacén» se registra aquí al recibir la mercancía.</span>
  </div>
</details>
